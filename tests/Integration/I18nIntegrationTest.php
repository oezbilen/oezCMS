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

    private function createSystemTranslationKey(string $domain, string $key): string
    {
        $keyId = $this->createTranslationKey($domain, $key);

        $this->database->execute(
            'UPDATE translation_keys SET is_system = TRUE WHERE id = :id',
            ['id' => $keyId],
        );

        return $keyId;
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

    private function softDeleteTranslationKey(string $keyId): void
    {
        $this->database->execute(
            'UPDATE translation_keys SET deleted_at = NOW(3) WHERE id = :id',
            ['id' => $keyId],
        );
    }

    private function createOrRestoreTranslationKey(string $domain, string $key): int
    {
        $resultSets = $this->database->callProcedure(
            'sp_i18n_create_translation_key',
            ['domain' => $domain, 'translation_key' => $key],
        );

        self::assertArrayHasKey(0, $resultSets);
        self::assertArrayHasKey(0, $resultSets[0]);
        self::assertArrayHasKey('id', $resultSets[0][0]);
        self::assertIsInt($resultSets[0][0]['id']);

        return $resultSets[0][0]['id'];
    }

    public function testDeploySeedsActiveEnglishAndGerman(): void
    {
        $rows = $this->database->fetchAll(
            'SELECT code FROM locales WHERE is_active = TRUE ORDER BY sort_order',
        );

        self::assertSame([['code' => 'en'], ['code' => 'de']], $rows);
    }

    public function testSeededLocalesReachTheDefaultLocale(): void
    {
        // Reachability, not adjacency: a regional locale may sit behind its parent
        // (de-at -> de -> en) and still satisfy this. What it does catch is a locale
        // with no fallback at all, a chain rooted somewhere other than the default,
        // and a chain so deep that v_i18n_locale_chains stops before reaching it.
        $rows = $this->database->fetchAll(
            'SELECT l.code
               FROM locales AS l
              WHERE l.is_default = FALSE
                AND NOT EXISTS (
                    SELECT 1
                      FROM v_i18n_locale_chains AS c
                      JOIN locales AS d ON d.id = c.locale_id AND d.is_default = TRUE
                     WHERE c.root_locale_id = l.id
                )
              ORDER BY l.sort_order',
        );

        self::assertSame([], $rows);
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

        $this->softDeleteTranslationKey($keyId);

        self::assertSame('core.welcome', $this->translate('core', 'welcome', 'de'));
    }

    public function testRejectsASecondRowForTheSameKey(): void
    {
        // A soft-deleted key is a hidden row, not a released name. A second row
        // under the same identity is how a key's translations became unreachable:
        // the values stayed behind on the old id.
        $keyId = $this->createTranslationKey('core', 'welcome');
        $this->softDeleteTranslationKey($keyId);

        $this->expectException(DatabaseException::class);

        // Deliberately a raw insert rather than a helper. This is the schema's
        // own guarantee and has to hold for a writer that knows nothing about
        // the procedure that will front it.
        $this->database->execute(
            'INSERT INTO translation_keys (domain, translation_key) VALUES (:domain, :translation_key)',
            ['domain' => 'core', 'translation_key' => 'welcome'],
        );
    }

    public function testProtectsSystemKeysFromSoftDelete(): void
    {
        $keyId = $this->createSystemTranslationKey('core', 'welcome');

        $this->expectException(DatabaseException::class);

        $this->softDeleteTranslationKey($keyId);
    }

    public function testProtectsSystemKeysFromRenaming(): void
    {
        $keyId = $this->createSystemTranslationKey('core', 'welcome');

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessageMatches('/A system translation key cannot be renamed/');

        $this->database->execute(
            'UPDATE translation_keys SET translation_key = :key WHERE id = :id',
            ['key' => 'greeting', 'id' => $keyId],
        );
    }

    public function testProtectsSystemKeysFromChangingDomain(): void
    {
        $keyId = $this->createSystemTranslationKey('core', 'welcome');

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessageMatches('/A system translation key cannot be renamed/');

        $this->database->execute(
            'UPDATE translation_keys SET domain = :domain WHERE id = :id',
            ['domain' => 'other', 'id' => $keyId],
        );
    }

    public function testProtectsSystemKeysFromLosingProtection(): void
    {
        // Without this rule the protection is a two-step bypass, and the CHECK
        // constraint cannot see it: clearing the flag and setting deleted_at in
        // one statement leaves a row state the constraint accepts.
        $keyId = $this->createSystemTranslationKey('core', 'welcome');

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessageMatches('/A system translation key cannot lose its protection/');

        $this->database->execute(
            'UPDATE translation_keys SET is_system = FALSE, deleted_at = NOW(3) WHERE id = :id',
            ['id' => $keyId],
        );
    }

    public function testProtectsSystemKeysFromHardDelete(): void
    {
        $keyId = $this->createSystemTranslationKey('core', 'welcome');

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessageMatches('/A system translation key cannot be deleted/');

        $this->database->execute('DELETE FROM translation_keys WHERE id = :id', ['id' => $keyId]);
    }

    public function testAllowsReorderingSystemKeys(): void
    {
        // The boundary: a system key is protected in its identity, not frozen.
        // Sort order is presentation.
        $keyId = $this->createSystemTranslationKey('core', 'welcome');

        $this->database->execute(
            'UPDATE translation_keys SET sort_order = 5 WHERE id = :id',
            ['id' => $keyId],
        );

        $row = $this->database->fetchOne(
            'SELECT sort_order FROM translation_keys WHERE id = :id',
            ['id' => $keyId],
        );

        self::assertNotNull($row);
        self::assertSame(5, $row['sort_order']);
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
        self::assertSame($this->localeId('de'), $row['id']);
    }

    public function testLocaleIdFromCodeNormalizesInput(): void
    {
        $row = $this->database->fetchOne("SELECT fn_i18n_locale_id_from_code('  DE ') AS id");

        self::assertNotNull($row);
        self::assertSame($this->localeId('de'), $row['id']);
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
            'SELECT fn_i18n_locale_resolve(:id) AS resolved',
            ['id' => $this->localeId('de-at')],
        );

        self::assertNotNull($row);
        self::assertSame($this->localeId('en'), $row['resolved']);
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

    public function testRejectsInactiveDefaultLocaleOnInsert(): void
    {
        $this->database->execute('UPDATE locales SET is_default = FALSE WHERE is_default = TRUE');

        $cleared = $this->database->fetchOne('SELECT id FROM locales WHERE is_default = TRUE');

        self::assertNull($cleared);

        $this->expectException(DatabaseException::class);

        $this->database->execute(
            'INSERT INTO locales (code, english_name, native_name, sort_order, is_active, is_default)
                VALUES (:code, :english_name, :native_name, 99, FALSE, TRUE)',
            ['code' => 'xd', 'english_name' => 'xd', 'native_name' => 'xd'],
        );
    }

    public function testAllowsActiveDefaultLocaleOnInsert(): void
    {
        $this->database->execute('UPDATE locales SET is_default = FALSE WHERE is_default = TRUE');

        $this->database->execute(
            'INSERT INTO locales (code, english_name, native_name, sort_order, is_active, is_default)
                VALUES (:code, :english_name, :native_name, 99, TRUE, TRUE)',
            ['code' => 'xd', 'english_name' => 'xd', 'native_name' => 'xd'],
        );

        $row = $this->database->fetchOne('SELECT code FROM locales WHERE is_default = TRUE');

        self::assertNotNull($row);
        self::assertSame('xd', $row['code']);
    }

    public function testMaxDepthIsThree(): void
    {
        $row = $this->database->fetchOne('SELECT fn_i18n_max_depth() AS max_depth');

        self::assertNotNull($row);
        self::assertSame(3, $row['max_depth']);
    }

    public function testLocaleChainListsRequestedLocaleFirst(): void
    {
        $this->createLocale('de-at', $this->localeId('de'));

        $rows = $this->database->fetchAll(
            'SELECT l.code, c.depth
                FROM v_i18n_locale_chains AS c
                JOIN locales AS l ON l.id = c.locale_id
                WHERE c.root_locale_id = :root
                ORDER BY c.depth',
            ['root' => $this->localeId('de-at')],
        );

        self::assertSame(
            [
                ['code' => 'de-at', 'depth' => 1],
                ['code' => 'de', 'depth' => 2],
                ['code' => 'en', 'depth' => 3],
            ],
            $rows,
        );
    }

    public function testLocaleChainCapsDepthAtMaxDepth(): void
    {
        // xa -> xb -> xc -> xd is one hop deeper than the cap; xd must not appear,
        // which is what keeps any pathological chain from being walked forever.
        $this->createLocale('xd');
        $this->createLocale('xc', $this->localeId('xd'));
        $this->createLocale('xb', $this->localeId('xc'));
        $this->createLocale('xa', $this->localeId('xb'));

        $rows = $this->database->fetchAll(
            'SELECT l.code
                FROM v_i18n_locale_chains AS c
                JOIN locales AS l ON l.id = c.locale_id
                WHERE c.root_locale_id = :root
                ORDER BY c.depth',
            ['root' => $this->localeId('xa')],
        );

        self::assertSame([['code' => 'xa'], ['code' => 'xb'], ['code' => 'xc']], $rows);
    }

    public function testLocaleChainStartsAtInactiveLocale(): void
    {
        // fr is seeded inactive with English as its fallback. A disabled locale
        // must still expose its chain, otherwise translation cannot fall through it.
        $rows = $this->database->fetchAll(
            'SELECT l.code
                FROM v_i18n_locale_chains AS c
                JOIN locales AS l ON l.id = c.locale_id
                WHERE c.root_locale_id = :root
                ORDER BY c.depth',
            ['root' => $this->localeId('fr')],
        );

        self::assertSame([['code' => 'fr'], ['code' => 'en']], $rows);
    }

    public function testLocaleResolveReturnsNullForUnknownLocale(): void
    {
        $row = $this->database->fetchOne('SELECT fn_i18n_locale_resolve(999) AS resolved');

        self::assertNotNull($row);
        self::assertNull($row['resolved']);
    }

    public function testCreatesATranslationKeyThatDoesNotExistYet(): void
    {
        $keyId = $this->createOrRestoreTranslationKey('core', 'welcome');

        $rows = $this->database->fetchAll(
            'SELECT domain, translation_key, deleted_at FROM translation_keys WHERE id = :id',
            ['id' => $keyId],
        );

        self::assertSame([['domain' => 'core', 'translation_key' => 'welcome', 'deleted_at' => null]], $rows);
    }

    public function testCreatingAnExistingKeyReturnsItsRow(): void
    {
        $keyId = $this->createTranslationKey('core', 'welcome');

        self::assertSame((int) $keyId, $this->createOrRestoreTranslationKey('core', 'welcome'));
    }

    public function testRestoringASoftDeletedKeyKeepsItsTranslations(): void
    {
        $keyId = $this->createTranslationKey('core', 'welcome');
        $this->addTranslation($keyId, 'de', 'Willkommen');
        $this->softDeleteTranslationKey($keyId);

        // Gone as far as translation is concerned...
        self::assertSame('core.welcome', $this->translate('core', 'welcome', 'de'));

        $restoredId = $this->createOrRestoreTranslationKey('core', 'welcome');

        // ...and creating it again returns the row itself, not a replacement, so
        // the values it collected come back with it. This is the whole point.
        self::assertSame((int) $keyId, $restoredId);
        self::assertSame('Willkommen', $this->translate('core', 'welcome', 'de'));
    }

    public function testCreatingAnExistingSystemKeyReturnsItsRow(): void
    {
        // The conflict path is an UPDATE and therefore passes through the trigger
        // that guards system keys. Nothing about the row changes, so nothing may
        // be refused.
        $keyId = $this->createSystemTranslationKey('core', 'welcome');

        self::assertSame((int) $keyId, $this->createOrRestoreTranslationKey('core', 'welcome'));
    }
}
