<?php

declare(strict_types=1);

namespace OezCMS\Console;

final class StreamOutput implements Output
{
    /**
     * @param resource $stream
     */
    public function __construct(private $stream)
    {
    }

    public function write(string $text): void
    {
        fwrite($this->stream, $text);
    }

    public function writeLine(string $line): void
    {
        fwrite($this->stream, $line . "\n");
    }
}
