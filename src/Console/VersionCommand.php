<?php

declare(strict_types=1);

namespace OezCMS\Console;

final class VersionCommand implements Command
{
    private const string NAME = 'oezCMS';
    private const string VERSION = '0.1.0-dev';

    public function name(): string
    {
        return 'version';
    }

    public function description(): string
    {
        return 'Show the application version';
    }

    public function run(Input $input, Output $output): ExitCode
    {
        $output->writeLine(sprintf('%s %s', self::NAME, self::VERSION));

        return ExitCode::Success;
    }
}
