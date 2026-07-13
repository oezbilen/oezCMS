<?php

declare(strict_types=1);

namespace OezCMS\Console;

use OezCMS\Core\Version;

final class VersionCommand implements Command
{
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
        $output->writeLine(Version::full());

        return ExitCode::Success;
    }
}
