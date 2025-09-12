<?php

declare(strict_types=1);

$features = [
    'Account',
    'DockerManagement',
    'McpInstancesManagement',
    'McpInstancesConfiguration',
];

foreach ($features as $from) {
    foreach ($features as $to) {
        if ($from === $to) {
            continue;
        }

        arch("{$from} must not use {$to} internals")
            ->expect("App\\{$from}")
            ->classes()
            ->not->toUse([
                "App\\{$to}\\Domain",
                "App\\{$to}\\Infrastructure",
                "App\\{$to}\\Presentation",
            ])
            ->group('architecture');
    }
}
