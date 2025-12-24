<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan as ArtisanFacade;

class CommandAnalyzer
{
    /**
     * Analyze a command and extract its signature details
     */
    public static function analyzeCommand(string $commandName): array
    {
        try {
            $command = ArtisanFacade::all()[$commandName] ?? null;

            if (!$command) {
                return [
                    'exists' => false,
                    'arguments' => [],
                    'options' => [],
                ];
            }

            $definition = $command->getDefinition();

            $arguments = [];
            foreach ($definition->getArguments() as $argument) {
                $arguments[] = [
                    'name' => $argument->getName(),
                    'description' => $argument->getDescription(),
                    'required' => $argument->isRequired(),
                    'is_array' => $argument->isArray(),
                    'default' => $argument->getDefault(),
                ];
            }

            $options = [];
            foreach ($definition->getOptions() as $option) {
                // Skip common Laravel options
                if (in_array($option->getName(), ['help', 'quiet', 'verbose', 'version', 'ansi', 'no-ansi', 'no-interaction', 'env'])) {
                    continue;
                }

                $options[] = [
                    'name' => $option->getName(),
                    'shortcut' => $option->getShortcut(),
                    'description' => $option->getDescription(),
                    'accepts_value' => $option->acceptValue(),
                    'is_value_required' => $option->isValueRequired(),
                    'is_array' => $option->isArray(),
                    'default' => $option->getDefault(),
                ];
            }

            return [
                'exists' => true,
                'name' => $commandName,
                'description' => $command->getDescription(),
                'arguments' => $arguments,
                'options' => $options,
            ];

        } catch (\Exception $e) {
            return [
                'exists' => false,
                'error' => $e->getMessage(),
                'arguments' => [],
                'options' => [],
            ];
        }
    }

    /**
     * Build command string with parameters
     */
    public static function buildCommandString(string $command, array $params = []): string
    {
        $commandStr = $command;

        foreach ($params as $key => $value) {
            if (is_bool($value)) {
                if ($value) {
                    $commandStr .= " --{$key}";
                }
            } else {
                $commandStr .= " --{$key}={$value}";
            }
        }

        return $commandStr;
    }
}

