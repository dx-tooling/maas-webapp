<?php

declare(strict_types=1);

namespace App\Authentication\Presentation\Controller;

use App\McpInstancesManagement\Domain\Entity\McpInstance;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ForwardAuthController extends AbstractController
{
    private const int CACHE_TTL = 300; // 5 minutes

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CacheItemPoolInterface $cache,
        private readonly LoggerInterface        $logger
    ) {
    }

    #[Route(
        path: '/auth/mcp-bearer-check',
        name: 'authentication.presentation.mcp_bearer_check',
        methods: ['GET', 'HEAD']
    )]
    public function mcpBearerCheckAction(Request $request): Response
    {
        // Prefer original requested host provided by Traefik
        $forwardedHostHeader = (string) ($request->headers->get('x-forwarded-host') ?? '');
        if ($forwardedHostHeader !== '') {
            // Traefik may send a comma-separated chain; take the first
            $forwardedHostHeader = trim(explode(',', $forwardedHostHeader)[0]);
            // Strip optional port from host
            $forwardedHostHeader = preg_replace('/:\d+$/', '', $forwardedHostHeader) ?? $forwardedHostHeader;
        }
        $host       = $forwardedHostHeader !== '' ? $forwardedHostHeader : $request->headers->get('host', '');
        $authHeader = $request->headers->get('authorization', '');
        $xfUri      = (string) ($request->headers->get('x-forwarded-uri') ?? '');
        $xfMethod   = (string) ($request->headers->get('x-forwarded-method') ?? '');
        $xfProto    = (string) ($request->headers->get('x-forwarded-proto') ?? '');

        // Debug state
        $presentedToken = null;
        $instanceSlug   = null;
        $cacheHit       = false;

        // Extract bearer token
        if (!preg_match('/^Bearer\s+(\S+)$/i', $authHeader, $matches)) {
            $this->logger->info('[ForwardAuth] Missing or invalid Bearer token', [
                'host' => $host,
                'ip'   => $request->getClientIp()
            ]);

            return new Response('', 401, array_merge(
                ['WWW-Authenticate' => 'Bearer realm="MCP"'],
                $this->buildDebugHeaders($host, $forwardedHostHeader, $xfUri, $xfMethod, $xfProto, $authHeader, null, null, false, ['X-FA-Reason' => 'missing-or-invalid-bearer'])
            ));
        }

        $presentedToken = $matches[1];

        // Prefer explicit header from Traefik middleware; fall back to Host parsing
        $explicitInstance = (string) ($request->headers->get('x-mcp-instance') ?? '');
        if ($explicitInstance !== '') {
            $instanceSlug = $explicitInstance;
        } else {
            // Extract instance ID from host: mcp-<instance-id>.mcp-as-a-service.com
            if (!preg_match('/^mcp-([^.]+)\./i', $host, $hostMatches)) {
                $this->logger->warning('[ForwardAuth] Invalid host format and no X-MCP-Instance header', [
                    'host' => $host,
                    'ip'   => $request->getClientIp()
                ]);

                return new Response('', 403, $this->buildDebugHeaders($host, $forwardedHostHeader, $xfUri, $xfMethod, $xfProto, $authHeader, $presentedToken, null, false, ['X-FA-Reason' => 'invalid-host-format']));
            }

            $instanceSlug = $hostMatches[1];
        }

        // Check cache first
        $cacheKey   = 'mcp_auth_' . md5($instanceSlug);
        $cachedItem = $this->cache->getItem($cacheKey);

        if ($cachedItem->isHit()) {
            $cacheHit      = true;
            $cachedValue   = $cachedItem->get();
            $expectedToken = is_string($cachedValue) ? $cachedValue : null;
        } else {
            // Lookup instance by slug
            $repo     = $this->entityManager->getRepository(McpInstance::class);
            $instance = $repo->findOneBy(['instanceSlug' => $instanceSlug]);

            if (!$instance) {
                $this->logger->warning('[ForwardAuth] Instance not found', [
                    'instanceSlug' => $instanceSlug,
                    'host'         => $host,
                    'ip'           => $request->getClientIp()
                ]);

                return new Response('', 403, $this->buildDebugHeaders($host, $forwardedHostHeader, $xfUri, $xfMethod, $xfProto, $authHeader, $presentedToken, $instanceSlug, $cacheHit, ['X-FA-Reason' => 'instance-not-found']));
            }

            $expectedToken = $instance->getMcpBearer();

            // Cache the token for future requests
            $cachedItem->set($expectedToken);
            $cachedItem->expiresAfter(self::CACHE_TTL);
            $this->cache->save($cachedItem);
        }

        if ($expectedToken === null) {
            return new Response('', 403, $this->buildDebugHeaders($host, $forwardedHostHeader, $xfUri, $xfMethod, $xfProto, $authHeader, $presentedToken, $instanceSlug, $cacheHit, ['X-FA-Reason' => 'no-expected-token']));
        }

        // Validate token
        if (!hash_equals($expectedToken, $presentedToken)) {
            $this->logger->warning('[ForwardAuth] Invalid bearer token', [
                'instanceSlug' => $instanceSlug,
                'host'         => $host,
                'ip'           => $request->getClientIp()
            ]);

            return new Response('', 401, array_merge(
                ['WWW-Authenticate' => 'Bearer realm="MCP"'],
                $this->buildDebugHeaders($host, $forwardedHostHeader, $xfUri, $xfMethod, $xfProto, $authHeader, $presentedToken, $instanceSlug, $cacheHit, ['X-FA-Reason' => 'token-mismatch'])
            ));
        }

        // Success - optionally include instance ID in response headers
        $this->logger->info('[ForwardAuth] Authentication successful', [
            'instanceSlug' => $instanceSlug,
            'host'         => $host,
            'ip'           => $request->getClientIp()
        ]);

        return new Response('', 204, array_merge(
            ['X-MCP-Instance-Id' => $instanceSlug],
            $this->buildDebugHeaders($host, $forwardedHostHeader, $xfUri, $xfMethod, $xfProto, $authHeader, $presentedToken, $instanceSlug, $cacheHit, ['X-FA-Reason' => 'ok'])
        ));
    }

    /**
     * @param array<string,string> $extra
     *
     * @return array<string,string>
     */
    private function buildDebugHeaders(
        string  $host,
        string  $forwardedHostHeader,
        string  $xfUri,
        string  $xfMethod,
        string  $xfProto,
        string  $authHeader,
        ?string $presentedToken,
        ?string $instanceSlug,
        bool    $cacheHit,
        array   $extra = []
    ): array {
        $base = [
            'X-FA-Debug'         => '1',
            'X-FA-Host'          => $host,
            'X-FA-XFH'           => $forwardedHostHeader,
            'X-FA-URI'           => $xfUri,
            'X-FA-Method'        => $xfMethod,
            'X-FA-Proto'         => $xfProto,
            'X-FA-Token-Present' => $authHeader !== '' ? 'true' : 'false',
            'X-FA-Token-Len'     => (string) strlen((string) $presentedToken),
            'X-FA-InstanceSlug'  => (string) ($instanceSlug ?? ''),
            'X-FA-Cache-Hit'     => $cacheHit ? 'true' : 'false',
        ];

        return array_merge($base, $extra);
    }
}
