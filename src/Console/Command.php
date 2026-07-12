<?php

declare(strict_types=1);

namespace OezCMS\Console;

interface Command
{
    public function name(): string;

    public function description(): string;

    public function run(Input $input, Output $output): ExitCode;
}
