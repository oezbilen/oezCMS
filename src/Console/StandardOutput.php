<?php

declare(strict_types=1);

namespace OezCMS\Console;

final class StandardOutput implements Output
{
    public function write(string $text): void
    {
        echo $text;
    }

    public function writeLine(string $line): void
    {
        echo $line, "\n";
    }
}
