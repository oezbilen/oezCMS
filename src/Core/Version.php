<?php

declare(strict_types=1);

namespace OezCMS\Core;

final class Version
{
    public const string NAME = 'oezCMS';
    public const string VERSION = '0.1.0-dev';

    public static function full(): string
    {
        return sprintf('%s %s', self::NAME, self::VERSION);
    }
}
