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

    public function testTranslateStopsAfterThreeLocales(): void
    {
        // A legitimate chain deeper than the read cap: xa -> xb -> xc -> xd,
        // with the value only on the fourth locale. Translation must stop after
        // three locales and never reach it — the guarantee that keeps any
        // pathological chain from looping.
        $this->createLocale('xd');
        $this->createLocale('xc', $this->localeId('xd'));
        $this->createLocale('xb', $this->localeId('xc'));
        $this->createLocale('xa', $this->localeId('xb'));

        $keyId = $this->createTranslationKey('core', 'deep');
        $this->addTranslation($keyId, 'xd', 'Too deep');

        self::assertSame('core.deep', $this->translate('core', 'deep', 'xa'));
    }

    public function testAllowsLegitimateChainExtensionOnUpdate(): void
    {
        $this->createLocale('xa');
        $this->createLocale('xb');
        $this->createLocale('xc');

        // Each update extends an acyclic chain (xb -> xa, then xc -> xb); the
        // cycle guard must walk them without rejecting a legitimate chain.
        $this->database->execute(
            'UPDATE locales SET fallback_locale_id = :fb WHERE code = :code',
            ['fb' => $this->localeId('xa'), 'code' => 'xb'],
        );
        $this->database->execute(
            'UPDATE locales SET fallback_locale_id = :fb WHERE code = :code',
            ['fb' => $this->localeId('xb'), 'code' => 'xc'],
        );

        $row = $this->database->fetchOne(
            'SELECT fallback_locale_id FROM locales WHERE code = :code',
            ['code' => 'xc'],
        );
        self::assertNotNull($row);
        self::assertSame($this->localeId('xb'), $row['fallback_locale_id']);
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

    public function testRejectsTwoNodeFallbackCycle(): void
    {
        $this->createLocale('xa');
        $this->createLocale('xb', $this->localeId('xa'));

        $this->expectException(DatabaseException::class);

        $this->database->execute(
            'UPDATE locales SET fallback_locale_id = :fallback_locale_id WHERE code = :code',
            ['fallback_locale_id' => $this->localeId('xb'), 'code' => 'xa'],
        );
    }

    public function testRejectsThreeNodeFallbackCycle(): void
    {
        $this->createLocale('xa');
        $this->createLocale('xb', $this->localeId('xa'));
        $this->createLocale('xc', $this->localeId('xb'));

        $this->expectException(DatabaseException::class);

        $this->database->execute(
            'UPDATE locales SET fallback_locale_id = :fallback_locale_id WHERE code = :code',
            ['fallback_locale_id' => $this->localeId('xc'), 'code' => 'xa'],
        );
    }

    public function testRejectsSelfFallbackOnInsert(): void
    {
        // The foreign key accepts a row referencing its own id, so a
        // self-referencing insert needs a BEFORE INSERT trigger of its own.
        $this->expectException(DatabaseException::class);

        $this->database->execute(
            'INSERT INTO locales (id, code, english_name, native_name, fallback_locale_id, sort_order)
                VALUES (900, :code, :english_name, :native_name, 900, 99)',
            ['code' => 'xz', 'english_name' => 'xz', 'native_name' => 'xz'],
        );
    }

    public function testEnglishIsTheSeededDefault(): void
    {
        $rows = $this->database->fetchAll('SELECT code FROM locales WHERE is_default = TRUE');

        self::assertSame([['code' => 'en']], $rows);
    }

    public function testSecondDefaultLocaleIsRejected(): void
    {
        $this->createLocale('xa');

        // Reading the new column makes this fail on the missing schema in RED,
        // so the expectException below cannot pass for the wrong reason.
        $defaults = $this->database->fetchAll('SELECT code FROM locales WHERE is_default = TRUE');
        self::assertSame([['code' => 'en']], $defaults);

        $this->expectException(DatabaseException::class);

        $this->database->execute(
            'UPDATE locales SET is_default = TRUE WHERE code = :code',
            ['code' => 'xa'],
        );
    }

    public function testDefaultLocaleMustStayActive(): void
    {
        $defaults = $this->database->fetchAll('SELECT code FROM locales WHERE is_default = TRUE');
        self::assertSame([['code' => 'en']], $defaults);

        $this->expectException(DatabaseException::class);

        $this->database->execute(
            'UPDATE locales SET is_active = FALSE WHERE code = :code',
            ['code' => 'en'],
        );
    }

    public function testDefaultLocaleCannotBeDeleted(): void
    {
        // Make a reference-free locale the default so the foreign key does not
        // stand in for the delete trigger under test.
        $this->createLocale('xa');
        $this->database->execute('UPDATE locales SET is_default = FALSE WHERE is_default = TRUE');
        $this->database->execute(
            'UPDATE locales SET is_default = TRUE WHERE code = :code',
            ['code' => 'xa'],
        );

        $this->expectException(DatabaseException::class);

        $this->database->execute('DELETE FROM locales WHERE code = :code', ['code' => 'xa']);
    }

    public function testSetDefaultLocaleSwitchesDefault(): void
    {
        $this->database->callProcedure('sp_i18n_set_default_locale', ['code' => 'de']);

        $rows = $this->database->fetchAll('SELECT code FROM locales WHERE is_default = TRUE');
        self::assertSame([['code' => 'de']], $rows);
    }

    public function testSetDefaultLocaleRejectsInactiveLocale(): void
    {
        $defaults = $this->database->fetchAll('SELECT code FROM locales WHERE is_default = TRUE');
        self::assertSame([['code' => 'en']], $defaults);

        $this->expectException(DatabaseException::class);

        // fr is seeded but inactive.
        $this->database->callProcedure('sp_i18n_set_default_locale', ['code' => 'fr']);
    }

    public function testTranslateUsesSwitchedDefaultLocale(): void
    {
        $this->createLocale('xa'); // active, no fallback, no translation, not default
        $keyId = $this->createTranslationKey('core', 'welcome');
        $this->addTranslation($keyId, 'de', 'Willkommen');

        $this->database->callProcedure('sp_i18n_set_default_locale', ['code' => 'de']);

        // xa has no value and no chain, so resolution falls through to the
        // default locale — now German, not the previously hardcoded English.
        self::assertSame('Willkommen', $this->translate('core', 'welcome', 'xa'));
    }

    public function testTranslateWithoutDefaultReturnsKey(): void
    {
        $this->createLocale('xa');
        $keyId = $this->createTranslationKey('core', 'welcome');
        $this->addTranslation($keyId, 'de', 'Willkommen');

        $this->database->execute('UPDATE locales SET is_default = FALSE WHERE is_default = TRUE');

        self::assertSame('core.welcome', $this->translate('core', 'welcome', 'xa'));
    }
}
