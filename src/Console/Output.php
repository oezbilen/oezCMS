<?php

declare(strict_types=1);

namespace OezCMS\Console;

interface Output
{
    public function write(string $text): void;

    public function writeLine(string $line): void;
}
