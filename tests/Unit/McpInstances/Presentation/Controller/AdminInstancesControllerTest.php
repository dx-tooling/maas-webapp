<?php

declare(strict_types=1);

namespace App\Tests\Unit\McpInstances\Presentation\Controller;

use App\DockerManagement\Facade\DockerManagementFacadeInterface;
use App\McpInstancesConfiguration\Facade\Dto\EndpointConfig;
use App\McpInstancesConfiguration\Facade\Dto\InstanceDockerConfig;
use App\McpInstancesConfiguration\Facade\Dto\InstanceTypeConfig;
use App\McpInstancesConfiguration\Facade\Service\InstanceTypesConfigFacadeInterface;
use App\McpInstancesManagement\Domain\Enum\InstanceType;
use App\McpInstancesManagement\Domain\Service\McpInstancesDomainServiceInterface;
use App\McpInstancesManagement\Presentation\Controller\AdminInstancesController;
use App\McpInstancesManagement\Presentation\McpInstancesPresentationService;
use App\Tests\Support\DomainTestHelper;
use App\Tests\Support\WebUiTestHelper;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

final class AdminInstancesControllerTest extends TestCase
{
    public function testOverviewRendersExpectedHtmlStructure(): void
    {
        $twig          = WebUiTestHelper::createTwigEnvironment();
        $domainService = $this->createMock(McpInstancesDomainServiceInterface::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $dockerFacade  = $this->createMock(DockerManagementFacadeInterface::class);
        $typesConfig   = $this->createMock(InstanceTypesConfigFacadeInterface::class);

        // Overview uses domain to fetch instances; empty list simplifies rendering
        $domainService->method('getAllMcpInstances')->willReturn([]);

        $presentation = new McpInstancesPresentationService(
            $domainService,
            $entityManager,
            $dockerFacade,
            $typesConfig,
        );

        $controller = new class($domainService, $presentation, $twig) extends AdminInstancesController {
            public function __construct(
                McpInstancesDomainServiceInterface $domain,
                McpInstancesPresentationService    $presentation,
                private Environment                $twig,
            ) {
                parent::__construct($domain, $presentation);
            }

            /**
             * @param array<string,mixed> $parameters
             */
            protected function render(string $view, array $parameters = [], ?Response $response = null): Response
            {
                $html = $this->twig->render($view, $parameters);

                return new Response($html);
            }
        };

        $response = $controller->overviewAction();
        $crawler  = new Crawler((string) $response->getContent());

        // Page title exists
        $title = $crawler->filter('[data-test-id="page-title"]');
        $this->assertCount(1, $title);
        $this->assertSame('MCP Instances Admin Overview', trim($title->text()));

        // Cards
        $statsCard = $crawler->filter('[data-test-id="card-stats"]');
        $this->assertGreaterThan(0, $statsCard->count());

        $tableCard = $crawler->filter('[data-test-id="card-table"]');
        $this->assertGreaterThan(0, $tableCard->count());
    }

    public function testDetailRendersExpectedHtmlStructure(): void
    {
        $twig          = WebUiTestHelper::createTwigEnvironment();
        $domainService = $this->createMock(McpInstancesDomainServiceInterface::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $dockerFacade  = $this->createMock(DockerManagementFacadeInterface::class);
        $typesConfig   = $this->createMock(InstanceTypesConfigFacadeInterface::class);

        // Prepare domain instance similar to InstancesControllerTest
        $accountId   = 'acc-999';
        $instanceId  = 'abcd-1234';
        $vncPassword = 'secret';
        $mcpBearer   = 'bearer-token';

        $domainInstance = DomainTestHelper::newDomainInstance(
            $accountId,
            InstanceType::PLAYWRIGHT_V1,
            1280,
            720,
            24,
            $vncPassword,
            $mcpBearer
        );
        DomainTestHelper::setPrivateProperty($domainInstance, 'id', $instanceId);
        $domainInstance->generateDerivedFields('example.test');

        // Provide instance type config so mapping yields vnc external paths
        $typesConfig->method('getTypeConfig')->willReturnCallback(function (InstanceType $t): ?InstanceTypeConfig {
            if ($t !== InstanceType::PLAYWRIGHT_V1) {
                return null;
            }

            return new InstanceTypeConfig(
                'Playwright v1',
                'Playwright automation for web testing',
                new InstanceDockerConfig('img:tag', []),
                [
                    'mcp' => new EndpointConfig(8080, 'bearer', ['/mcp', '/sse'], null),
                    'vnc' => new EndpointConfig(6080, null, ['/vnc'], null),
                ]
            );
        });

        // Domain lookups used by presentation service
        $domainService->method('getMcpInstanceById')->with($instanceId)->willReturn($domainInstance);
        $domainService->method('getProcessStatusForInstance')->willThrowException(new Exception('skip'));

        $presentation = new McpInstancesPresentationService(
            $domainService,
            $entityManager,
            $dockerFacade,
            $typesConfig,
        );

        $controller = new class($domainService, $presentation, $twig) extends AdminInstancesController {
            public function __construct(
                McpInstancesDomainServiceInterface $domain,
                McpInstancesPresentationService    $presentation,
                private Environment                $twig,
            ) {
                parent::__construct($domain, $presentation);
            }

            /**
             * @param array<string,mixed> $parameters
             */
            protected function render(string $view, array $parameters = [], ?Response $response = null): Response
            {
                $html = $this->twig->render($view, $parameters);

                return new Response($html);
            }
        };

        $response = $controller->detailAction($instanceId, new \Symfony\Component\HttpFoundation\Request());
        $crawler  = new Crawler((string) $response->getContent());

        // Page title exists
        $title = $crawler->filter('[data-test-id="page-title"]');
        $this->assertCount(1, $title);
        $this->assertSame('MCP Instance Details', trim($title->text()));

        // General Instance Data card exists
        $general = $crawler->filter('[data-test-id="card-general"]');
        $this->assertGreaterThan(0, $general->count());

        // VNC Password input present
        $vncInput = $crawler->filter('[data-test-id="vnc-password"]');
        $this->assertGreaterThan(0, $vncInput->count());

        // MCP Bearer token input present
        $bearerInput = $crawler->filter('[data-test-id="mcp-bearer"]');
        $this->assertGreaterThan(0, $bearerInput->count());

        // MCP Endpoint(s) card
        $mcpCard = $crawler->filter('[data-test-id="card-mcp-endpoints"]');
        $this->assertGreaterThan(0, $mcpCard->count());
    }
}
