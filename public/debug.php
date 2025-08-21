<?php

declare(strict_types=1);

header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-cache, private');

// Utility: get all request headers in a portable way
if (!function_exists('maas_getallheaders')) {
    /**
     * @return array<string,string>
     */
    function maas_getallheaders(): array
    {
        /** @var array<string,mixed> $server */
        $server  = $_SERVER;
        $headers = [];
        foreach ($server as $name => $value) {
            if (str_starts_with($name, 'HTTP_')) {
                $h           = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                $headers[$h] = is_string($value) ? $value : (is_scalar($value) ? (string) $value : '');
            }
        }
        // CONTENT_TYPE and CONTENT_LENGTH are not prefixed with HTTP_
        foreach (['CONTENT_TYPE' => 'Content-Type', 'CONTENT_LENGTH' => 'Content-Length'] as $key => $h) {
            if (array_key_exists($key, $server)) {
                $v           = $server[$key];
                $headers[$h] = is_string($v) ? $v : (is_scalar($v) ? (string) $v : '');
            }
        }
        ksort($headers);

        return $headers;
    }
}

/**
 * Safely fetch a string value from $_SERVER.
 */
function server_string(string $key): string
{
    /** @var array<string,mixed> $server */
    $server = $_SERVER;
    if (array_key_exists($key, $server) && is_string($server[$key])) {
        return $server[$key];
    }

    return '';
}

$method   = server_string('REQUEST_METHOD');
$uri      = server_string('REQUEST_URI');
$proto    = server_string('SERVER_PROTOCOL');
$hostHdr  = server_string('HTTP_HOST');
$xfh      = server_string('HTTP_X_FORWARDED_HOST');
$xfuri    = server_string('HTTP_X_FORWARDED_URI');
$xfm      = server_string('HTTP_X_FORWARDED_METHOD');
$xfp      = server_string('HTTP_X_FORWARDED_PROTO');
$fwd      = server_string('HTTP_FORWARDED');
$auth     = server_string('HTTP_AUTHORIZATION');
$remoteIP = server_string('REMOTE_ADDR');

// Try to detect instance slug via multiple strategies
$instanceFromQuery = array_key_exists('instance', $_GET) && is_string($_GET['instance']) ? $_GET['instance'] : '';
$instanceFromXFH   = '';
$instanceFromHost  = '';
if (preg_match('/^mcp-([^.]+)\./i', $xfh, $m)) {
    $instanceFromXFH = $m[1];
}
if (preg_match('/^mcp-([^.]+)\./i', $hostHdr, $m2)) {
    $instanceFromHost = $m2[1];
}

echo "=== Request Line ===\n";
echo $method . ' ' . $uri . ' ' . $proto . "\n\n";

echo "=== Core Headers ===\n";
printf("Host: %s\n", $hostHdr);
printf("X-Forwarded-Host: %s\n", $xfh);
printf("X-Forwarded-Uri: %s\n", $xfuri);
printf("X-Forwarded-Method: %s\n", $xfm);
printf("X-Forwarded-Proto: %s\n", $xfp);
printf("Forwarded: %s\n", $fwd);
printf("Authorization: %s\n", $auth !== '' ? 'present (len=' . strlen($auth) . ')' : 'absent');
printf("Remote-Addr: %s\n", $remoteIP);
echo "\n";

echo "=== Parsed Values ===\n";
printf("instance(query): %s\n", $instanceFromQuery);
printf("instance(from XFH): %s\n", $instanceFromXFH);
printf("instance(from Host): %s\n", $instanceFromHost);
echo "\n";

echo "=== All Request Headers ===\n";
foreach (maas_getallheaders() as $k => $v) {
    printf("%s: %s\n", $k, $v);
}
echo "\n";

echo "=== Selected SERVER entries ===\n";
$keys = [
    'SERVER_NAME', 'SERVER_ADDR', 'SERVER_PORT', 'SERVER_PROTOCOL',
    'REQUEST_METHOD', 'REQUEST_URI', 'QUERY_STRING',
    'HTTP_HOST', 'HTTP_FORWARDED',
    'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED_HOST', 'HTTP_X_FORWARDED_PROTO', 'HTTP_X_FORWARDED_METHOD', 'HTTP_X_FORWARDED_URI',
    'HTTP_AUTHORIZATION', 'REMOTE_ADDR', 'HTTPS'
];
foreach ($keys as $k) {
    if (array_key_exists($k, $_SERVER)) {
        $v = $_SERVER[$k];
        printf("%s=%s\n", $k, is_string($v) ? $v : (is_scalar($v) ? (string) $v : ''));
    }
}
echo "\n";

echo "OK\n";
