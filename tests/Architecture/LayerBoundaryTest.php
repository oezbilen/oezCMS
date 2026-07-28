<?php

declare(strict_types=1);

namespace OezCMS\Tests\Architecture;

use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class LayerBoundaryTest extends TestCase
{
    /**
     * Layers that may hold code, other than modules.
     */
    private const array LAYERS = [
        'OezCMS\\Core',
        'OezCMS\\Console',
        'OezCMS\\Http',
    ];

    /**
     * What a layer may not reach for.
     *
     * Core sits at the bottom and knows nothing above it. Modules contain
     * feature code and must not depend on delivery adapters such as Console or
     * Http. Console and Http may use Core and published module APIs, but neither
     * delivery adapter may depend on the other.
     *
     * @var array<string, list<string>>
     */
    private const array FORBIDDEN = [
        'OezCMS\\Core' => [
            'OezCMS\\Console',
            'OezCMS\\Http',
            'OezCMS\\Modules',
        ],
        'OezCMS\\Modules' => [
            'OezCMS\\Console',
            'OezCMS\\Http',
        ],
        'OezCMS\\Console' => [
            'OezCMS\\Http',
        ],
        'OezCMS\\Http' => [
            'OezCMS\\Console',
        ],
    ];

    public function testNoLayerReachesAboveItself(): void
    {
        $violations = [];

        foreach ($this->sourceFiles() as $path) {
            $analysis = $this->analyseFile($path);

            foreach ($this->forbiddenImports($analysis['namespace'], $analysis['imports']) as $import) {
                $violations[] = sprintf('%s: %s imports %s', $this->relativePath($path), $analysis['namespace'], $import);
            }
        }

        self::assertSame([], $violations);
    }

    public function testEveryFileBelongsToANamedLayer(): void
    {
        // The guard against a dumping ground by a different name: a new
        // top-level namespace fails here until someone says which layer it is.
        $unknown = [];

        foreach ($this->sourceFiles() as $path) {
            $namespace = $this->analyseFile($path)['namespace'];

            if (!$this->isKnownLayer($namespace)) {
                $unknown[] = sprintf('%s: unknown layer "%s"', $this->relativePath($path), $namespace);
            }
        }

        self::assertSame([], $unknown);
    }

    /**
     * The rule holds no violations today and would hold none if it were broken,
     * so it is exercised directly against cases it must and must not catch.
     *
     * @param list<string> $imports
     * @param list<string> $expected
     */
    #[DataProvider('boundaryCaseProvider')]
    public function testTheRuleRecognisesTheCasesItIsMeantTo(string $namespace, array $imports, array $expected): void
    {
        self::assertSame($expected, $this->forbiddenImports($namespace, $imports));
    }

    /**
     * @return iterable<string, array{string, list<string>, list<string>}>
     */
    public static function boundaryCaseProvider(): iterable
    {
        yield 'core may not know the console' => [
            'OezCMS\\Core',
            ['OezCMS\\Console\\Output'],
            ['OezCMS\\Console\\Output'],
        ];

        yield 'core may not know http' => [
            'OezCMS\\Core\\Runtime',
            ['OezCMS\\Http\\Request'],
            ['OezCMS\\Http\\Request'],
        ];

        yield 'core may not know modules' => [
            'OezCMS\\Core',
            ['OezCMS\\Modules\\Media\\Api\\MediaLibrary'],
            ['OezCMS\\Modules\\Media\\Api\\MediaLibrary'],
        ];

        yield 'the console may use core' => [
            'OezCMS\\Console',
            ['OezCMS\\Core\\Container'],
            [],
        ];

        yield 'http may use core' => [
            'OezCMS\\Http\\Controller',
            ['OezCMS\\Core\\Container'],
            [],
        ];

        yield 'a module may use core' => [
            'OezCMS\\Modules\\Media\\Application',
            ['OezCMS\\Core\\Container'],
            [],
        ];

        yield 'a module may not use the console layer' => [
            'OezCMS\\Modules\\Media\\Application',
            ['OezCMS\\Console\\Output'],
            ['OezCMS\\Console\\Output'],
        ];

        yield 'a module may not use the http layer' => [
            'OezCMS\\Modules\\Media\\Application',
            ['OezCMS\\Http\\Request'],
            ['OezCMS\\Http\\Request'],
        ];

        yield 'a module may not reach into another module' => [
            'OezCMS\\Modules\\Media',
            ['OezCMS\\Modules\\I18n\\Translator'],
            ['OezCMS\\Modules\\I18n\\Translator'],
        ];

        yield 'a module may use what another module publishes' => [
            'OezCMS\\Modules\\Media',
            ['OezCMS\\Modules\\I18n\\Api\\Translator'],
            [],
        ];

        yield 'a module may use itself' => [
            'OezCMS\\Modules\\Media',
            ['OezCMS\\Modules\\Media\\Storage'],
            [],
        ];

        yield 'a layer is a namespace, not a string prefix' => [
            'OezCMS\\CoreUtilities',
            ['OezCMS\\Console\\Output'],
            [],
        ];

        yield 'the console may not reach into a module' => [
            'OezCMS\\Console',
            ['OezCMS\\Modules\\Media\\Storage'],
            ['OezCMS\\Modules\\Media\\Storage'],
        ];

        yield 'the console may use what a module publishes' => [
            'OezCMS\\Console',
            ['OezCMS\\Modules\\Media\\Api\\MediaLibrary'],
            [],
        ];

        yield 'module names are whole segments, not prefixes' => [
            'OezCMS\\Modules\\User_Management',
            ['OezCMS\\Modules\\User_Service\\Storage'],
            ['OezCMS\\Modules\\User_Service\\Storage'],
        ];

        yield 'a published surface is found under an underscored module name' => [
            'OezCMS\\Modules\\Media',
            ['OezCMS\\Modules\\User_Management\\Api\\Directory'],
            [],
        ];

        yield 'a published surface may not lean on its own internals' => [
            'OezCMS\\Modules\\Media\\Api',
            ['OezCMS\\Modules\\Media\\Storage\\LocalDisk'],
            ['OezCMS\\Modules\\Media\\Storage\\LocalDisk'],
        ];

        yield 'a nested published surface may not lean on its own internals' => [
            'OezCMS\\Modules\\Media\\Api\\Contract',
            ['OezCMS\\Modules\\Media\\Storage\\LocalDisk'],
            ['OezCMS\\Modules\\Media\\Storage\\LocalDisk'],
        ];

        yield 'a module api may use another part of its api' => [
            'OezCMS\\Modules\\Media\\Api\\Dto',
            ['OezCMS\\Modules\\Media\\Api\\Contract\\MediaLibrary'],
            [],
        ];

        yield 'a module api may use core' => [
            'OezCMS\\Modules\\Media\\Api',
            ['OezCMS\\Core\\Clock'],
            [],
        ];

        yield 'a module api may use an external dependency' => [
            'OezCMS\\Modules\\Media\\Api',
            ['Psr\\Clock\\ClockInterface'],
            [],
        ];

        yield 'internals may implement what the surface declares' => [
            'OezCMS\\Modules\\Media\\Storage',
            ['OezCMS\\Modules\\Media\\Api\\MediaLibrary'],
            [],
        ];

        yield 'http may not reach into module internals' => [
            'OezCMS\\Http\\Controller',
            ['OezCMS\\Modules\\I18n\\Translator'],
            ['OezCMS\\Modules\\I18n\\Translator'],
        ];

        yield 'http may use a module api' => [
            'OezCMS\\Http\\Controller',
            ['OezCMS\\Modules\\I18n\\Api\\Translator'],
            [],
        ];

        yield 'console may not reach into module internals' => [
            'OezCMS\\Console\\Command',
            ['OezCMS\\Modules\\Media\\Storage'],
            ['OezCMS\\Modules\\Media\\Storage'],
        ];

        yield 'console may use a module api' => [
            'OezCMS\\Console\\Command',
            ['OezCMS\\Modules\\Media\\Api\\MediaStorage'],
            [],
        ];
    }

    public function testInternalReferencesAreImportedRatherThanWrittenInline(): void
    {
        $violations = [];

        foreach ($this->sourceFiles() as $path) {
            foreach ($this->analyseFile($path)['inline'] as $reference) {
                $violations[] = sprintf(
                    '%s: %s is written inline instead of imported',
                    $this->relativePath($path),
                    $reference,
                );
            }
        }

        self::assertSame([], $violations);
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('inlineReferenceCaseProvider')]
    public function testInlineReferenceRule(string $source, array $expected): void
    {
        self::assertSame($expected, $this->analyseSource($source)['inline']);
    }

    /**
     * @param  list<string> $imports
     * @return list<string>
     */
    private function forbiddenImports(string $namespace, array $imports): array
    {
        $forbidden = [];

        foreach ($imports as $import) {
            if ($this->isForbidden($namespace, $import)) {
                $forbidden[] = $import;
            }
        }

        return $forbidden;
    }

    private function isForbidden(string $namespace, string $import): bool
    {
        foreach (self::FORBIDDEN as $sourceLayer => $targetLayers) {
            if (!$this->isWithin($namespace, $sourceLayer)) {
                continue;
            }

            foreach ($targetLayers as $targetLayer) {
                if ($this->isWithin($import, $targetLayer)) {
                    return true;
                }
            }
        }

        return $this->importsNonPublicModuleCode($namespace, $import)
            || $this->moduleApiImportsInternalCode($namespace, $import);
    }

    /**
     * A module's internals are nobody's business but its own. This holds for any
     * consumer that is not the module itself, so Console and Http are bound by
     * it exactly as another module is.
     */
    private function importsNonPublicModuleCode(string $namespace, string $import): bool
    {
        $targetModule = $this->moduleOf($import);

        if (null === $targetModule || $targetModule === $this->moduleOf($namespace)) {
            return false;
        }

        return !$this->isWithin($import, $targetModule . '\\Api');
    }

    /**
     * What a module publishes must not depend on what it keeps to itself. A
     * surface leaning on internals publishes them too, whatever the directory
     * layout says.
     *
     * The reverse direction stays open: an internal class implementing an
     * interface from Api is exactly what Api is there for.
     */
    private function moduleApiImportsInternalCode(string $namespace, string $import): bool
    {
        $sourceModule = $this->moduleOf($namespace);

        if (null === $sourceModule || !$this->isWithin($namespace, $sourceModule . '\\Api')) {
            return false;
        }

        return $this->isWithin($import, $sourceModule) && !$this->isWithin($import, $sourceModule . '\\Api');
    }

    /**
     * The namespace comes from a file PHP has to be able to load, so this
     * identifies a module rather than re-validating the identifier grammar.
     */
    private function moduleOf(string $namespace): ?string
    {
        $segments = explode('\\', $namespace);

        if (
            count($segments) < 3
            || 'OezCMS' !== $segments[0]
            || 'Modules' !== $segments[1]
            || '' === $segments[2]
        ) {
            return null;
        }

        return implode('\\', array_slice($segments, 0, 3));
    }

    private function isKnownLayer(string $namespace): bool
    {
        if ($this->isWithin($namespace, 'OezCMS\\Modules')) {
            // src/Modules itself is not a home for code; a module needs a name.
            return null !== $this->moduleOf($namespace);
        }

        foreach (self::LAYERS as $layer) {
            if ($this->isWithin($namespace, $layer)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Compared segment-wise, so OezCMS\CoreUtilities is not inside OezCMS\Core.
     */
    private function isWithin(string $namespace, string $prefix): bool
    {
        return $namespace === $prefix || str_starts_with($namespace, $prefix . '\\');
    }

    /**
     * @return array{namespace: string, imports: list<string>, inline: list<string>}
     */
    private function analyseFile(string $path): array
    {
        $source = file_get_contents($path);
        self::assertIsString($source);

        $analysis = $this->analyseSource($source);

        self::assertNotSame(
            '',
            $analysis['namespace'],
            sprintf('%s declares no namespace.', $this->relativePath($path)),
        );

        return $analysis;
    }

    /**
     * One pass over the token stream answering both questions the boundary rules
     * ask of a file: what it imports, and what it names without importing.
     *
     * Tokenized rather than matched, so text in comments, strings and heredocs
     * cannot masquerade as a declaration.
     *
     * Telling an import from a trait use or a closure capture needs no knowledge
     * of classes at all: an import sits at brace depth zero and is not followed
     * by "(", while a trait use needs a class body and a closure capture needs
     * its parenthesis. Bracketed namespaces are rejected because they would make
     * depth zero mean something else.
     *
     * Group imports, several imports in one statement and unterminated ones are
     * rejected too. The project style gate prevents all three, but refusing them
     * here keeps this analysis right when it runs on its own -- silently reading
     * half a statement would hide the dependency in the other half.
     *
     * @return array{namespace: string, imports: list<string>, inline: list<string>}
     */
    private function analyseSource(string $source): array
    {
        $tokens = token_get_all($source);
        $namespace = '';
        $namespaceCount = 0;
        $imports = [];
        $inline = [];
        $braceDepth = 0;
        $count = count($tokens);

        for ($index = 0; $index < $count; ++$index) {
            $token = $tokens[$index];

            if (is_string($token)) {
                if ('{' === $token) {
                    ++$braceDepth;
                } elseif ('}' === $token) {
                    --$braceDepth;
                }

                continue;
            }

            [$type, $value] = $token;

            // Interpolation opens a brace with an array token and closes it with
            // a plain one, so the opening has to be counted here or the depth
            // drifts for the rest of the file.
            if (T_CURLY_OPEN === $type || T_DOLLAR_OPEN_CURLY_BRACES === $type) {
                ++$braceDepth;

                continue;
            }

            if (T_NAMESPACE === $type) {
                ++$namespaceCount;

                if (1 < $namespaceCount) {
                    throw new LogicException('Multiple namespace declarations are not supported.');
                }

                [$namespace, $index] = $this->readNamespaceName($tokens, $index + 1);

                continue;
            }

            if (T_USE === $type) {
                if (0 !== $braceDepth) {
                    continue;
                }

                if ('(' === $this->nextSignificantToken($tokens, $index + 1)) {
                    continue;
                }

                // The whole statement is consumed here, which is also why its
                // name never reaches the inline check below.
                [$import, $index] = $this->readImportName($tokens, $index + 1);
                $imports[] = ltrim($import, '\\');

                continue;
            }

            if (T_NAME_FULLY_QUALIFIED === $type && $this->isWithin(ltrim($value, '\\'), 'OezCMS')) {
                $inline[] = $value;
            }
        }

        return [
            'namespace' => $namespace,
            'imports' => $imports,
            'inline' => array_values(array_unique($inline)),
        ];
    }

    /**
     * @param list<array{int, string, int}|string> $tokens
     *
     * @return array{string, int}
     */
    private function readNamespaceName(array $tokens, int $index): array
    {
        $name = '';
        $count = count($tokens);

        for (; $index < $count; ++$index) {
            $token = $tokens[$index];

            if (is_string($token)) {
                if ('{' === $token) {
                    throw new LogicException('Bracketed namespace declarations are not supported.');
                }

                if (';' === $token) {
                    break;
                }

                continue;
            }

            [$type, $value] = $token;

            if (
                T_NAME_QUALIFIED === $type
                || T_NAME_FULLY_QUALIFIED === $type
                || T_NAME_RELATIVE === $type
                || T_STRING === $type
                || T_NS_SEPARATOR === $type
            ) {
                $name .= $value;
            }
        }

        if ('' === $name) {
            throw new LogicException('A namespace declaration contains no name.');
        }

        return [ltrim($name, '\\'), $index];
    }

    /**
     * Returns the imported name and the index of the semicolon ending the
     * statement, so the caller resumes on the token after it.
     *
     * @param list<array{int, string, int}|string> $tokens
     *
     * @return array{string, int}
     */
    private function readImportName(array $tokens, int $index): array
    {
        $name = '';
        $terminated = false;
        $count = count($tokens);

        for (; $index < $count; ++$index) {
            $token = $tokens[$index];

            if (is_string($token)) {
                if ('{' === $token) {
                    throw new LogicException('Group imports are not supported; expand them first.');
                }

                if (',' === $token) {
                    throw new LogicException(
                        'Multiple imports in one statement are not supported; split them first.',
                    );
                }

                if (';' === $token) {
                    $terminated = true;

                    break;
                }

                continue;
            }

            [$type, $value] = $token;

            if (
                T_WHITESPACE === $type
                || T_COMMENT === $type
                || T_DOC_COMMENT === $type
                || T_FUNCTION === $type
                || T_CONST === $type
            ) {
                continue;
            }

            if (T_AS === $type) {
                // The alias is a local name and no dependency. Reading stops at
                // the semicolon rather than continuing, which would otherwise
                // glue the next statement onto this name and consume it.
                $index = $this->skipImportAlias($tokens, $index + 1);
                $terminated = true;

                break;
            }

            if (
                T_NAME_QUALIFIED === $type
                || T_NAME_FULLY_QUALIFIED === $type
                || T_STRING === $type
                || T_NS_SEPARATOR === $type
            ) {
                $name .= $value;
            }
        }

        if ('' === $name) {
            throw new LogicException('An import statement contains no name.');
        }

        if (!$terminated) {
            throw new LogicException('An import statement is not terminated by a semicolon.');
        }

        return [$name, $index];
    }

    /**
     * @param list<array{int, string, int}|string> $tokens
     */
    private function skipImportAlias(array $tokens, int $index): int
    {
        $count = count($tokens);

        for (; $index < $count; ++$index) {
            $token = $tokens[$index];

            if (is_string($token)) {
                if (',' === $token) {
                    throw new LogicException(
                        'Multiple imports in one statement are not supported; split them first.',
                    );
                }

                if (';' === $token) {
                    return $index;
                }
            }
        }

        throw new LogicException('An import statement is not terminated by a semicolon.');
    }

    /**
     * @param list<array{int, string, int}|string> $tokens
     *
     * @return array{int, string, int}|string|null
     */
    private function nextSignificantToken(array $tokens, int $index): array|string|null
    {
        $count = count($tokens);

        for (; $index < $count; ++$index) {
            $token = $tokens[$index];

            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return $token;
        }

        return null;
    }

    /**
     * @return iterable<string>
     */
    private function sourceFiles(): iterable
    {
        /** @var iterable<string, SplFileInfo> $files */
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->projectRoot() . '/src', RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if ($file->isFile() && 'php' === $file->getExtension()) {
                yield $file->getPathname();
            }
        }
    }

    private function projectRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    private function relativePath(string $path): string
    {
        $root = $this->projectRoot() . DIRECTORY_SEPARATOR;

        if (!str_starts_with($path, $root)) {
            return $path;
        }

        return substr($path, strlen($root));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function inlineReferenceCaseProvider(): iterable
    {
        yield 'fully qualified class reference' => [
            <<<'PHP'
            <?php

            namespace OezCMS\Core;

            $output = new \OezCMS\Console\Output();
            PHP,
            ['\\OezCMS\\Console\\Output'],
        ];

        yield 'fully qualified parameter type' => [
            <<<'PHP'
            <?php

            namespace OezCMS\Core;

            function run(\OezCMS\Console\Output $output): void
            {
            }
            PHP,
            ['\\OezCMS\\Console\\Output'],
        ];

        yield 'imported reference' => [
            <<<'PHP'
            <?php

            namespace OezCMS\Core;

            use \OezCMS\Console\Output;

            $output = new Output();
            PHP,
            [],
        ];

        yield 'fully qualified trait use is still inline' => [
            <<<'PHP'
            <?php

            namespace OezCMS\Core;

            final class Example
            {
                use \OezCMS\Console\WritesOutput;
            }
            PHP,
            ['\\OezCMS\\Console\\WritesOutput'],
        ];

        yield 'closure use does not blind the scan' => [
            <<<'PHP'
            <?php

            namespace OezCMS\Core;

            $f = static function () use ($container): void {
                \OezCMS\Console\Output::write();
            };
            PHP,
            ['\\OezCMS\\Console\\Output'],
        ];

        yield 'a closure in constructor arguments does not open the class body' => [
            <<<'PHP'
            <?php

            namespace OezCMS\Core;

            $instance = new class(
                static function () use ($container): void {
                },
            ) {
                use \OezCMS\Console\WritesOutput;
            };
            PHP,
            ['\\OezCMS\\Console\\WritesOutput'],
        ];

        yield 'string interpolation does not unbalance the brace count' => [
            <<<'PHP'
            <?php

            namespace OezCMS\Core;

            final class Example
            {
                public function run(): string
                {
                    return "value: {$this->name}";
                }

                use \OezCMS\Console\WritesOutput;
            }
            PHP,
            ['\\OezCMS\\Console\\WritesOutput'],
        ];

        yield 'reference in a comment' => [
            <<<'PHP'
            <?php

            namespace OezCMS\Core;

            // Do not use \OezCMS\Console\Output here.
            PHP,
            [],
        ];

        yield 'reference in a string' => [
            <<<'PHP'
            <?php

            namespace OezCMS\Core;

            $name = '\OezCMS\Console\Output';
            PHP,
            [],
        ];

        yield 'external fully qualified reference' => [
            <<<'PHP'
            <?php

            namespace OezCMS\Core;

            $date = new \DateTimeImmutable();
            PHP,
            [],
        ];

        yield 'duplicates are reported once' => [
            <<<'PHP'
            <?php

            namespace OezCMS\Core;

            \OezCMS\Console\Output::write();
            \OezCMS\Console\Output::flush();
            PHP,
            ['\\OezCMS\\Console\\Output'],
        ];
    }

    public function testImportsIgnoreWhatOnlyLooksLikeOne(): void
    {
        $source = <<<'PHP'
            <?php

            namespace OezCMS\Core;

            use OezCMS\Console\Output;
            use OezCMS\Core\Support\LogsActivity;

            final class Example
            {
                use LogsActivity;

                public function run(Container $container): callable
                {
                    return static function () use ($container): void {
                    };
                }
            }
            PHP;

        $analysis = $this->analyseSource($source);

        self::assertSame('OezCMS\\Core', $analysis['namespace']);
        self::assertSame(['OezCMS\\Console\\Output', 'OezCMS\\Core\\Support\\LogsActivity'], $analysis['imports']);
    }

    public function testImportAliasesDoNotBecomeDependencies(): void
    {
        $source = <<<'PHP'
            <?php

            namespace OezCMS\Core;

            use OezCMS\Console\Output as ConsoleOutput;
            PHP;

        self::assertSame(['OezCMS\\Console\\Output'], $this->analyseSource($source)['imports']);
    }

    public function testAnAliasedImportDoesNotSwallowTheNextOne(): void
    {
        // Skipping the alias used to run past its semicolon, which glued the
        // following import onto the name and consumed its statement, so two
        // dependencies disappeared at once.
        $source = <<<'PHP'
            <?php

            namespace OezCMS\Core;

            use OezCMS\Console\Output as ConsoleOutput;
            use OezCMS\Core\Container;
            PHP;

        self::assertSame(
            ['OezCMS\\Console\\Output', 'OezCMS\\Core\\Container'],
            $this->analyseSource($source)['imports'],
        );
    }

    public function testFunctionAndConstantImportsAreDependencies(): void
    {
        $source = <<<'PHP'
            <?php

            namespace OezCMS\Core;

            use function OezCMS\Console\write_output;
            use const OezCMS\Http\DEFAULT_STATUS;
            PHP;

        self::assertSame(
            ['OezCMS\\Console\\write_output', 'OezCMS\\Http\\DEFAULT_STATUS'],
            $this->analyseSource($source)['imports'],
        );
    }

    public function testAnalysisRejectsGroupImports(): void
    {
        $source = <<<'PHP'
            <?php

            namespace OezCMS\Core;

            use OezCMS\Core\{Container, Database};
            PHP;

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageMatches('/Group imports are not supported/');

        $this->analyseSource($source);
    }

    public function testAnalysisRejectsMultipleImportsInOneStatement(): void
    {
        $source = <<<'PHP'
            <?php

            namespace OezCMS\Core;

            use OezCMS\Core\Container, OezCMS\Console\Output;
            PHP;

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageMatches('/Multiple imports in one statement are not supported/');

        $this->analyseSource($source);
    }

    public function testAnalysisRejectsMultipleNamespaces(): void
    {
        $source = <<<'PHP'
            <?php

            namespace OezCMS\Core;

            namespace OezCMS\Console;
            PHP;

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageMatches('/Multiple namespace declarations are not supported/');

        $this->analyseSource($source);
    }

    public function testAnalysisRejectsBracketedNamespaces(): void
    {
        // Not a matter of taste: brace depth zero only means "namespace level"
        // while the bracketed form is absent, and that is what tells a trait use
        // from an import.
        $source = <<<'PHP'
            <?php

            namespace OezCMS\Core {
                final class Example
                {
                }
            }
            PHP;

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageMatches('/Bracketed namespace declarations are not supported/');

        $this->analyseSource($source);
    }

    public function testAnalysisRejectsAnUnterminatedImport(): void
    {
        $source = <<<'PHP'
            <?php

            namespace OezCMS\Core;

            use OezCMS\Console\Output
            PHP;

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageMatches('/not terminated by a semicolon/');

        $this->analyseSource($source);
    }
}
