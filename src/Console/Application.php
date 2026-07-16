<?php

declare(strict_types=1);

namespace OezCMS\Console;

use Throwable;

final class Application
{
    public function __construct(
        private readonly CommandRegistry $registry,
        private readonly bool $debug = false,
    ) {
    }

    public function run(Input $input, Output $output, Output $errorOutput): ExitCode
    {
        $name = $input->command();

        if (null === $name) {
            $this->writeUsage($errorOutput);

            return ExitCode::Usage;
        }

        if (!$this->registry->has($name)) {
            $errorOutput->writeLine(sprintf('Unknown command: %s', $name));
            $this->writeUsage($errorOutput);

            return ExitCode::Usage;
        }

        try {
            return $this->registry->get($name)->run($input, $output);
        } catch (Throwable $exception) {
            $errorOutput->writeLine(sprintf('Error: %s', $exception->getMessage()));

            if ($this->debug) {
                $errorOutput->writeLine('');
                $errorOutput->writeLine((string) $exception);
            }

            return ExitCode::Failure;
        }
    }

    private function writeUsage(Output $output): void
    {
        $output->writeLine('Usage: console <command> [arguments]');

        $commands = $this->registry->all();

        if ([] === $commands) {
            return;
        }

        $output->writeLine('');
        $output->writeLine('Available commands:');

        foreach ($commands as $command) {
            $output->writeLine(sprintf('  %-20s %s', $command->name(), $command->description()));
        }
    }
}
