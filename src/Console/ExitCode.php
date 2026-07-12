<?php

declare(strict_types=1);

namespace OezCMS\Console;

enum ExitCode: int
{
    case Success = 0;
    case Failure = 1;
    case Usage = 2;
}
