<?php

namespace App\Services\StepExecutors;

use RuntimeException;

/**
 * Executes predefined safe script commands.
 * No arbitrary code execution — only whitelisted commands are allowed.
 */
class ScriptStepExecutor implements StepExecutorInterface
{
    // Only these commands are allowed — no arbitrary execution
    private const ALLOWED_COMMANDS = [
        'data-sync' => 'Synchronize data between sources',
        'report-generate' => 'Generate a report',
        'cache-clear' => 'Clear application cache',
        'export-csv' => 'Export data to CSV',
        'import-data' => 'Import data from source',
        'send-notification' => 'Send a notification',
    ];

    public function execute(array $config, array $context = []): ?array
    {
        $command = $config['command'] ?? '';
        $args = $config['args'] ?? [];

        if (! isset(self::ALLOWED_COMMANDS[$command])) {
            throw new RuntimeException(
                "Script command '{$command}' is not in the allowed commands list. " .
                'Allowed: ' . implode(', ', array_keys(self::ALLOWED_COMMANDS))
            );
        }

        // Simulate script execution (in production, this would dispatch to a sandboxed runner)
        $startTime = microtime(true);

        // Simulate processing time
        usleep(100000); // 100ms

        $duration = round((microtime(true) - $startTime) * 1000, 2);

        return [
            'command' => $command,
            'args' => $args,
            'status' => 'completed',
            'duration_ms' => $duration,
            'output' => "Script '{$command}' executed successfully",
        ];
    }

    public static function getAllowedCommands(): array
    {
        return self::ALLOWED_COMMANDS;
    }
}
