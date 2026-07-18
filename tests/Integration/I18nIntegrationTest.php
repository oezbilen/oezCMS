<?php

declare(strict_types=1);

namespace OezCMS\Tests\Integration;

use OezCMS\Core\DatabaseException;

final class I18nIntegrationTest extends SchemaDeploymentTestCase
{
    private function localeId(string $code): int
    {
        $row = $this->database->fetchOne('SELECT id FROM locales WHERE code = :code', ['code' => $code]);
        self::assertNotNull($row);
        self::assertIsInt($row['id']);

        return $row['id'];
    }

    private function createLocale(string $code, ?int $fallbackLocaleId = null): void
    {
        $this->database->execute(
            'INSERT INTO locales (code, english_name, native_name, fallback_locale_id, sort_order)
                VALUES (:code, :english_name, :native_name, :fallback_locale_id, 99)',
            [
                'code' => $code,
                'english_name' => $code,
                'native_name' => $code,
                'fallback_locale_id' => $fallbackLocaleId,
            ],
        );
    }

    private function createTranslationKey(string $domain, string $key): string
    {
        $this->database->execute(
            'INSERT INTO translation_keys (domain, translation_key) VALUES (:domain, :translation_key)',
            ['domain' => $domain, 'translation_key' => $key],
        );

        return $this->database->lastInsertId();
    }

    private function addTranslation(string $keyId, string $localeCode, string $value): void
    {
        $this->database->execute(
            'INSERT INTO translation_values (translation_key_id, locale_id, value)
                SELECT :translation_key_id, id, :value FROM locales WHERE code = :code',
            ['translation_key_id' => $keyId, 'value' => $value, 'code' => $localeCode],
        );
    }

    private function translate(string $domain, string $key, string $localeCode): string
    {
        $row = $this->database->fetchOne(
            'SELECT fn_i18n_translate(:domain, :translation_key, :code) AS translated',
            ['domain' => $domain, 'translation_key' => $key, 'code' => $localeCode],
        );

        self::assertNotNull($row);
        self::assertIsString($row['translated']);

        return $row['translated'];
    }

    public function testDeploySeedsActiveEnglishAndGerman(): void
    {
        $rows = $this->database->fetchAll(
            'SELECT code FROM locales WHERE is_active = TRUE ORDER BY sort_order',
        );

        self::assertSame([['code' => 'en'], ['code' => 'de']], $rows);
    }

    public function testRejectsInvalidLocaleCode(): void
    {
        $this->createLocale('xa');

        $this->expectException(DatabaseException::class);

        $this->createLocale('DE');
    }

    public function testRejectsSelfFallback(): void
    {
        $this->createLocale('xa');

        $this->expectException(DatabaseException::class);

        $this->database->execute(
            'UPDATE locales SET fallback_locale_id = id WHERE code = :code',
            ['code' => 'xa'],
        );
    }

    public function testRejectsBlankTranslationValue(): void
    {
        $keyId = $this->createTranslationKey('core', 'welcome');

        $this->expectException(DatabaseException::class);

        $this->addTranslation($keyId, 'de', '   ');
    }

    public function testTranslateReturnsRequestedLocale(): void
    {
        $keyId = $this->createTranslationKey('core', 'welcome');
        $this->addTranslation($keyId, 'de', 'Willkommen');
        $this->addTranslation($keyId, 'en', 'Welcome');

        self::assertSame('Willkommen', $this->translate('core', 'welcome', 'de'));
    }

    public function testTranslateWalksFallbackChain(): void
    {
        $this->createLocale('de-at', $this->localeId('de'));
        $keyId = $this->createTranslationKey('core', 'welcome');
        $this->addTranslation($keyId, 'de', 'Willkommen');

        self::assertSame('Willkommen', $this->translate('core', 'welcome', 'de-at'));
    }

    public function testTranslateWalksChainAcrossTwoHops(): void
    {
        $this->createLocale('de-at', $this->localeId('de'));
        $this->createLocale('de-ch', $this->localeId('de-at'));
        $keyId = $this->createTranslationKey('core', 'welcome');
        $this->addTranslation($keyId, 'de', 'Willkommen');

        self::assertSame('Willkommen', $this->translate('core', 'welcome', 'de-ch'));
    }

    public function testTranslateSkipsInactiveLocale(): void
    {
        $this->createLocale('de-at', $this->localeId('de'));
        $keyId = $this->createTranslationKey('core', 'welcome');
        $this->addTranslation($keyId, 'de-at', 'Servus');
        $this->addTranslation($keyId, 'de', 'Willkommen');

        $this->database->execute(
            'UPDATE locales SET is_active = FALSE WHERE code = :code',
            ['code' => 'de-at'],
        );

        self::assertSame('Willkommen', $this->translate('core', 'welcome', 'de-at'));
    }

    public function testTranslateFallsBackToEnglish(): void
    {
        $keyId = $this->createTranslationKey('core', 'welcome');
        $this->addTranslation($keyId, 'en', 'Welcome');

        self::assertSame('Welcome', $this->translate('core', 'welcome', 'de'));
    }

    public function testTranslateFallsBackToKeyIdentifier(): void
    {
        self::assertSame('core.missing', $this->translate('core', 'missing', 'de'));
    }

    public function testTranslateSurvivesFallbackCycle(): void
    {
        $this->createLocale('xa');
        $this->createLocale('xb', $this->localeId('xa'));
        $this->database->execute(
            'UPDATE locales SET fallback_locale_id = :fallback_locale_id WHERE code = :code',
            ['fallback_locale_id' => $this->localeId('xb'), 'code' => 'xa'],
        );

        self::assertSame('core.missing', $this->translate('core', 'missing', 'xa'));
    }

    public function testTranslateIgnoresSoftDeletedKeys(): void
    {
        $keyId = $this->createTranslationKey('core', 'welcome');
        $this->addTranslation($keyId, 'de', 'Willkommen');

        $this->database->execute(
            'UPDATE translation_keys SET deleted_at = NOW(3) WHERE id = :id',
            ['id' => $keyId],
        );

        self::assertSame('core.welcome', $this->translate('core', 'welcome', 'de'));
    }

    public function testAllowsRecreatingSoftDeletedKey(): void
    {
        $keyId = $this->createTranslationKey('core', 'welcome');
        $this->database->execute(
            'UPDATE translation_keys SET deleted_at = NOW(3) WHERE id = :id',
            ['id' => $keyId],
        );

        $newKeyId = $this->createTranslationKey('core', 'welcome');
        $this->addTranslation($newKeyId, 'de', 'Willkommen');

        self::assertSame('Willkommen', $this->translate('core', 'welcome', 'de'));
    }

    public function testProtectsSystemKeysFromSoftDelete(): void
    {
        $keyId = $this->createTranslationKey('core', 'welcome');
        $this->database->execute(
            'UPDATE translation_keys SET is_system = TRUE WHERE id = :id',
            ['id' => $keyId],
        );

        $this->expectException(DatabaseException::class);

        $this->database->execute(
            'UPDATE translation_keys SET deleted_at = NOW(3) WHERE id = :id',
            ['id' => $keyId],
        );
    }

    public function testMissingTranslationsViewListsGaps(): void
    {
        $keyId = $this->createTranslationKey('core', 'welcome');
        $this->addTranslation($keyId, 'de', 'Willkommen');

        $rows = $this->database->fetchAll(
            "SELECT domain, translation_key, locale_code FROM v_i18n_missing_translations WHERE domain = 'core'",
        );

        self::assertSame([['domain' => 'core', 'translation_key' => 'welcome', 'locale_code' => 'en']], $rows);
    }

    public function testLocaleIdFromCodeResolvesActiveLocale(): void
    {
        $row = $this->database->fetchOne("SELECT fn_i18n_locale_id_from_code('de') AS id");

        self::assertNotNull($row);
        self::assertSame(2, $row['id']);
    }

    public function testLocaleIdFromCodeNormalizesInput(): void
    {
        $row = $this->database->fetchOne("SELECT fn_i18n_locale_id_from_code('  DE ') AS id");

        self::assertNotNull($row);
        self::assertSame(2, $row['id']);
    }

    public function testLocaleIdFromCodeReturnsNullForUnknownOrInactiveLocale(): void
    {
        $unknown = $this->database->fetchOne("SELECT fn_i18n_locale_id_from_code('xx') AS id");
        $inactive = $this->database->fetchOne("SELECT fn_i18n_locale_id_from_code('fr') AS id");

        self::assertNotNull($unknown);
        self::assertNull($unknown['id']);
        self::assertNotNull($inactive);
        self::assertNull($inactive['id']);
    }

    public function testLocaleResolveFollowsChainToFinalLocale(): void
    {
        $this->createLocale('de-at', $this->localeId('de'));

        $row = $this->database->fetchOne(
            'SELECT fn_i18n_locale_resolve(:id, 3) AS resolved',
            ['id' => $this->localeId('de-at')],
        );

        self::assertNotNull($row);
        self::assertSame(1, $row['resolved']);
    }
}
