<?php

declare(strict_types=1);

namespace OezCMS\Tests\Integration;

use OezCMS\Core\DatabaseException;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The rules the media schema holds on its own, whatever writes to it.
 *
 * Every refusal is matched by the name of the constraint or the text of the
 * trigger that raises it. Before the schema exists, each of these statements
 * fails on a missing table, and a bare expectException would pass for that.
 */
final class MediaSchemaIntegrationTest extends SchemaDeploymentTestCase
{
    private function mediaTypeId(string $mimeType): int
    {
        $row = $this->database->fetchOne(
            'SELECT id FROM media_types WHERE mime_type = :mime_type',
            ['mime_type' => $mimeType],
        );

        self::assertNotNull($row);
        self::assertIsInt($row['id']);

        return $row['id'];
    }

    private function maxBytes(string $mimeType): int
    {
        $row = $this->database->fetchOne(
            'SELECT max_bytes FROM media_types WHERE mime_type = :mime_type',
            ['mime_type' => $mimeType],
        );

        self::assertNotNull($row);
        self::assertIsInt($row['max_bytes']);

        return $row['max_bytes'];
    }

    private function deactivateMediaType(string $mimeType): void
    {
        $this->database->execute(
            'UPDATE media_types SET is_active = FALSE WHERE mime_type = :mime_type',
            ['mime_type' => $mimeType],
        );
    }

    private function storeFile(
        string $sha256,
        string $mimeType = 'image/png',
        int $sizeBytes = 1024,
        ?int $width = 640,
        ?int $height = 480,
    ): string {
        $this->database->execute(
            'INSERT INTO media_files (sha256, media_type_id, size_bytes, width, height)
                VALUES (:sha256, :media_type_id, :size_bytes, :width, :height)',
            [
                'sha256' => $sha256,
                'media_type_id' => $this->mediaTypeId($mimeType),
                'size_bytes' => $sizeBytes,
                'width' => $width,
                'height' => $height,
            ],
        );

        return $this->database->lastInsertId();
    }

    private function createMedia(string $fileId, string $originalName = 'photo.png'): string
    {
        $this->database->execute(
            'INSERT INTO media (public_id, media_file_id, original_name)
                VALUES (:public_id, :media_file_id, :original_name)',
            [
                'public_id' => self::uuid(),
                'media_file_id' => $fileId,
                'original_name' => $originalName,
            ],
        );

        return $this->database->lastInsertId();
    }

    private function addText(string $mediaId, string $localeCode, ?string $title, ?string $altText): void
    {
        $this->database->execute(
            'INSERT INTO media_texts (media_id, locale_id, title, alt_text)
                SELECT :media_id, id, :title, :alt_text FROM locales WHERE code = :code',
            [
                'media_id' => $mediaId,
                'title' => $title,
                'alt_text' => $altText,
                'code' => $localeCode,
            ],
        );
    }

    private function countRows(string $table, string $column, string $value): int
    {
        // Table and column come from the test itself, never from a caller.
        $row = $this->database->fetchOne(
            sprintf('SELECT COUNT(*) AS n FROM %s WHERE %s = :value', $table, $column),
            ['value' => $value],
        );

        self::assertNotNull($row);
        self::assertIsInt($row['n']);

        return $row['n'];
    }

    private static function sha256(string $content): string
    {
        return hash('sha256', $content);
    }

    /**
     * A random version 4 UUID, as the application will supply one.
     */
    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20),
        );
    }

    public function testDeploySeedsTheAcceptedMediaTypes(): void
    {
        // The exact list, so a type added without review fails here, and so
        // does image/svg+xml, which stays out until it has a decision of its own.
        $rows = $this->database->fetchAll(
            'SELECT mime_type FROM media_types WHERE is_active = TRUE ORDER BY mime_type',
        );

        self::assertSame(
            [
                ['mime_type' => 'application/pdf'],
                ['mime_type' => 'image/avif'],
                ['mime_type' => 'image/gif'],
                ['mime_type' => 'image/jpeg'],
                ['mime_type' => 'image/png'],
                ['mime_type' => 'image/webp'],
            ],
            $rows,
        );
    }

    public function testStoresAFileOfAnActiveType(): void
    {
        $sha256 = self::sha256('a');

        $this->storeFile($sha256);

        self::assertSame(1, $this->countRows('media_files', 'sha256', $sha256));
    }

    public function testStoresAFileWithoutDimensions(): void
    {
        $sha256 = self::sha256('a');

        $this->storeFile($sha256, 'application/pdf', 1024, null, null);

        self::assertSame(1, $this->countRows('media_files', 'sha256', $sha256));
    }

    public function testRejectsAnUpperCaseHash(): void
    {
        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessageMatches('/chk_media_files_sha256/');

        $this->storeFile(strtoupper(self::sha256('a')));
    }

    public function testRejectsAHashOfTheWrongLength(): void
    {
        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessageMatches('/chk_media_files_sha256/');

        $this->storeFile(substr(self::sha256('a'), 0, 63));
    }

    public function testRejectsASecondFileWithTheSameHash(): void
    {
        // Identical bytes are stored once (ADR 0016), whatever type a second
        // import claims for them.
        $this->storeFile(self::sha256('a'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessageMatches('/uk_media_files_sha256/');

        $this->storeFile(self::sha256('a'), 'image/jpeg');
    }

    public function testRejectsAFileOfAnUnknownType(): void
    {
        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessageMatches('/fk_media_files_type/');

        $this->database->execute(
            'INSERT INTO media_files (sha256, media_type_id, size_bytes)
                SELECT :sha256, MAX(id) + 1, 1024 FROM media_types',
            ['sha256' => self::sha256('a')],
        );
    }

    public function testRejectsAFileOfAnInactiveType(): void
    {
        $this->deactivateMediaType('image/gif');

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessageMatches('/A media type that is not active accepts no new files/');

        $this->storeFile(self::sha256('a'), 'image/gif');
    }

    public function testRejectsAFileAboveTheSizeLimitOfItsType(): void
    {
        $limit = $this->maxBytes('image/png');

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessageMatches('/A file exceeds the size limit of its media type/');

        $this->storeFile(self::sha256('a'), 'image/png', $limit + 1);
    }

    public function testStoresAFileAtTheSizeLimitOfItsType(): void
    {
        $sha256 = self::sha256('a');

        $this->storeFile($sha256, 'image/png', $this->maxBytes('image/png'));

        self::assertSame(1, $this->countRows('media_files', 'sha256', $sha256));
    }

    public function testDeactivatingATypeKeepsItsStoredFiles(): void
    {
        $sha256 = self::sha256('a');
        $this->storeFile($sha256, 'image/gif');

        $this->deactivateMediaType('image/gif');

        self::assertSame(1, $this->countRows('media_files', 'sha256', $sha256));
    }

    public function testRejectsAWidthWithoutAHeight(): void
    {
        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessageMatches('/chk_media_files_dimensions_paired/');

        $this->storeFile(self::sha256('a'), 'image/png', 1024, 640, null);
    }

    public function testRejectsAZeroDimension(): void
    {
        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessageMatches('/chk_media_files_dimensions_positive/');

        $this->storeFile(self::sha256('a'), 'image/png', 1024, 0, 480);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function storedFileColumnProvider(): iterable
    {
        yield 'hash' => ['sha256', sprintf("'%s'", self::sha256('b'))];
        yield 'type' => ['media_type_id', "(SELECT t.id FROM media_types AS t WHERE t.mime_type = 'image/jpeg')"];
        yield 'size' => ['size_bytes', '2048'];
        yield 'width' => ['width', '800'];
        yield 'height' => ['height', '600'];
    }

    #[DataProvider('storedFileColumnProvider')]
    public function testRefusesChangingAStoredFile(string $column, string $value): void
    {
        $fileId = $this->storeFile(self::sha256('a'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessageMatches('/A stored file cannot be changed/');

        // Column and value come from the provider above, never from a caller.
        $this->database->execute(
            sprintf('UPDATE media_files SET %s = %s WHERE id = :id', $column, $value),
            ['id' => $fileId],
        );
    }

    public function testKeepsAFileWhileAMediaEntryRefersToIt(): void
    {
        $fileId = $this->storeFile(self::sha256('a'));
        $this->createMedia($fileId);

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessageMatches('/fk_media_file\b/');

        $this->database->execute('DELETE FROM media_files WHERE id = :id', ['id' => $fileId]);
    }

    public function testRemovesAFileNoMediaEntryRefersTo(): void
    {
        $fileId = $this->storeFile(self::sha256('a'));

        $this->database->execute('DELETE FROM media_files WHERE id = :id', ['id' => $fileId]);

        self::assertSame(0, $this->countRows('media_files', 'id', $fileId));
    }

    public function testSeveralMediaEntriesShareOneFile(): void
    {
        $fileId = $this->storeFile(self::sha256('a'));

        $this->createMedia($fileId, 'first.png');
        $this->createMedia($fileId, 'second.png');

        self::assertSame(2, $this->countRows('media', 'media_file_id', $fileId));
    }

    public function testRejectsABlankOriginalName(): void
    {
        $fileId = $this->storeFile(self::sha256('a'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessageMatches('/chk_media_original_name/');

        $this->createMedia($fileId, '   ');
    }

    public function testRefusesChangingThePublicIdOfAMediaEntry(): void
    {
        $mediaId = $this->createMedia($this->storeFile(self::sha256('a')));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessageMatches('/The public id of a media entry cannot change/');

        $this->database->execute(
            'UPDATE media SET public_id = :public_id WHERE id = :id',
            ['public_id' => self::uuid(), 'id' => $mediaId],
        );
    }

    public function testAllowsSoftDeletingAMediaEntry(): void
    {
        $mediaId = $this->createMedia($this->storeFile(self::sha256('a')));

        $this->database->execute(
            'UPDATE media SET deleted_at = NOW(3) WHERE id = :id',
            ['id' => $mediaId],
        );

        $row = $this->database->fetchOne('SELECT deleted_at FROM media WHERE id = :id', ['id' => $mediaId]);
        self::assertNotNull($row);
        self::assertNotNull($row['deleted_at']);
    }

    public function testStoresTextsPerLocale(): void
    {
        $mediaId = $this->createMedia($this->storeFile(self::sha256('a')));

        $this->addText($mediaId, 'de', 'Leuchtturm', 'Ein Leuchtturm bei Sonnenuntergang');
        $this->addText($mediaId, 'en', null, 'A lighthouse at sunset');

        self::assertSame(2, $this->countRows('media_texts', 'media_id', $mediaId));
    }

    public function testRejectsAMediaTextWithNeitherTitleNorAltText(): void
    {
        $mediaId = $this->createMedia($this->storeFile(self::sha256('a')));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessageMatches('/chk_media_texts_not_empty/');

        $this->addText($mediaId, 'de', null, null);
    }

    public function testRejectsABlankAltText(): void
    {
        $mediaId = $this->createMedia($this->storeFile(self::sha256('a')));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessageMatches('/chk_media_texts_alt_text/');

        $this->addText($mediaId, 'de', 'Leuchtturm', '   ');
    }

    public function testRejectsASecondTextForTheSameLocale(): void
    {
        $mediaId = $this->createMedia($this->storeFile(self::sha256('a')));
        $this->addText($mediaId, 'de', 'Leuchtturm', null);

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessageMatches('/uk_media_texts_media_locale/');

        $this->addText($mediaId, 'de', 'Turm', null);
    }

    public function testDeletingAMediaEntryRemovesItsTexts(): void
    {
        $mediaId = $this->createMedia($this->storeFile(self::sha256('a')));
        $this->addText($mediaId, 'de', 'Leuchtturm', null);

        $this->database->execute('DELETE FROM media WHERE id = :id', ['id' => $mediaId]);

        self::assertSame(0, $this->countRows('media_texts', 'media_id', $mediaId));
    }

    public function testRefusesDeletingALocaleThatHasMediaTexts(): void
    {
        $this->database->execute(
            "INSERT INTO locales (code, english_name, native_name) VALUES ('xa', 'xa', 'xa')",
        );
        $mediaId = $this->createMedia($this->storeFile(self::sha256('a')));
        $this->addText($mediaId, 'xa', 'Leuchtturm', null);

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessageMatches('/fk_media_texts_locale/');

        $this->database->execute("DELETE FROM locales WHERE code = 'xa'");
    }
}
