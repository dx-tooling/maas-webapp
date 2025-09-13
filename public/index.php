<?php

declare(strict_types=1);

// Optional maintenance mode: set MAAS_MAINTENANCE=1 to enable
// Do not show the maintenance page if the request is for path /auth/mcp-bearer-check
// @phpstan-ignore-next-line
$__maasMaintenance = $_SERVER['MAAS_MAINTENANCE'] ?? getenv('MAAS_MAINTENANCE') ?? '1';
if ($__maasMaintenance === '1'
    && $_SERVER['HTTP_X_FORWARDED_FOR'] !== '178.202.190.26'
    && $_SERVER['REQUEST_URI']          !== '/auth/mcp-bearer-check'
) {
    echo 'The MCP-as-a-Service web application is down for maintenance for the next couple of hours. We are sorry for any inconvenience this may cause.';
    exit;
}

use App\Kernel;
use EnterpriseToolingForSymfony\SharedBundle\DateAndTime\Enum\Timezone;

require_once dirname(__DIR__) . '/vendor/autoload_runtime.php';

date_default_timezone_set(Timezone::UTC->value);

return function (array $context) {
    if (!is_string($context['APP_ENV'])) {
        throw new ValueError('The "APP_ENV" environment variable is not set to a valid value.');
    }

    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
