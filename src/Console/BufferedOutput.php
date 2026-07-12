<?php

declare(strict_types=1);

namespace OezCMS\Console;

final class BufferedOutput implements Output
{
    private string $contents = '';

    public function write(string $text): void
    {
        $this->contents .= $text;
    }

    public function writeLine(string $line): void
    {
        $this->contents .= $line . "\n";
    }

    public function contents(): string
    {
        return $this->contents;
    }
}
