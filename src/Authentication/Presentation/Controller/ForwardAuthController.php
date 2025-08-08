<?php

declare(strict_types=1);

namespace App\Authentication\Presentation\Controller;

use App\McpInstances\Domain\Entity\McpInstance;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ForwardAuthController extends AbstractController
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
        $host       = $request->headers->get('host', '');
        $authHeader = $request->headers->get('authorization', '');

        // Extract bearer token
        if (!preg_match('/^Bearer\s+(\S+)$/i', $authHeader, $matches)) {
            $this->logger->info('[ForwardAuth] Missing or invalid Bearer token', [
                'host' => $host,
                'ip'   => $request->getClientIp()
            ]);

            return new Response('', 401, ['WWW-Authenticate' => 'Bearer realm="MCP"']);
        }

        $presentedToken = $matches[1];

        // Extract instance ID from host: mcp-<instance-id>.mcp-as-a-service.com
        if (!preg_match('/^mcp-([^.]+)\./i', $host, $hostMatches)) {
            $this->logger->warning('[ForwardAuth] Invalid host format', [
                'host' => $host,
                'ip'   => $request->getClientIp()
            ]);

            return new Response('', 403);
        }

        $instanceSlug = $hostMatches[1];

        // Check cache first
        $cacheKey   = 'mcp_auth_' . md5($instanceSlug);
        $cachedItem = $this->cache->getItem($cacheKey);

        if ($cachedItem->isHit()) {
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

                return new Response('', 403);
            }

            $expectedToken = $instance->getMcpBearer();

            // Cache the token for future requests
            $cachedItem->set($expectedToken);
            $cachedItem->expiresAfter(self::CACHE_TTL);
            $this->cache->save($cachedItem);
        }

        if ($expectedToken === null) {
            return new Response('', 403);
        }

        // Validate token
        if (!hash_equals($expectedToken, $presentedToken)) {
            $this->logger->warning('[ForwardAuth] Invalid bearer token', [
                'instanceSlug' => $instanceSlug,
                'host'         => $host,
                'ip'           => $request->getClientIp()
            ]);

            return new Response('', 401, ['WWW-Authenticate' => 'Bearer realm="MCP"']);
        }

        // Success - optionally include instance ID in response headers
        $this->logger->info('[ForwardAuth] Authentication successful', [
            'instanceSlug' => $instanceSlug,
            'host'         => $host,
            'ip'           => $request->getClientIp()
        ]);

        return new Response('', 204, [
            'X-MCP-Instance-Id' => $instanceSlug
        ]);
    }
}
