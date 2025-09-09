<?php

declare(strict_types=1);

namespace App\Tests\Unit\McpInstances\Presentation\Controller;

use App\DockerManagement\Facade\DockerManagementFacadeInterface;
use App\McpInstances\Domain\Config\Dto\EndpointConfig;
use App\McpInstances\Domain\Config\Dto\InstanceDockerConfig;
use App\McpInstances\Domain\Config\Dto\InstanceTypeConfig;
use App\McpInstances\Domain\Config\Service\InstanceTypesConfigServiceInterface;
use App\McpInstances\Domain\Entity\McpInstance as DomainMcpInstance;
use App\McpInstances\Domain\Enum\InstanceType;
use App\McpInstances\Domain\Service\McpInstancesDomainServiceInterface;
use App\McpInstances\Presentation\Controller\AdminInstancesController;
use App\McpInstances\Presentation\McpInstancesPresentationService;
use App\Tests\Support\WebUiTestHelper;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
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
        $typesConfig   = $this->createMock(InstanceTypesConfigServiceInterface::class);

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
        $title = $crawler->filter('h1.etfswui-pagetitle');
        $this->assertCount(1, $title);
        $this->assertSame('MCP Instances Admin Overview', trim($title->text()));

        // Cards
        $statsCard = $crawler->filterXPath("//div[contains(@class,'etfswui-card')][.//span[contains(@class,'etfswui-card-title-text') and normalize-space(.)='Platform Statistics']]");
        $this->assertGreaterThan(0, $statsCard->count());

        $tableCard = $crawler->filterXPath("//div[contains(@class,'etfswui-card')][.//span[contains(@class,'etfswui-card-title-text') and normalize-space(.)='All MCP Instances']]");
        $this->assertGreaterThan(0, $tableCard->count());
    }

    public function testDetailRendersExpectedHtmlStructure(): void
    {
        $twig          = WebUiTestHelper::createTwigEnvironment();
        $domainService = $this->createMock(McpInstancesDomainServiceInterface::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $dockerFacade  = $this->createMock(DockerManagementFacadeInterface::class);
        $typesConfig   = $this->createMock(InstanceTypesConfigServiceInterface::class);

        // Prepare domain instance similar to InstancesControllerTest
        $accountId   = 'acc-999';
        $instanceId  = 'abcd-1234';
        $vncPassword = 'secret';
        $mcpBearer   = 'bearer-token';

        $domainInstance = $this->newDomainInstance(
            $accountId,
            InstanceType::PLAYWRIGHT_V1,
            1280,
            720,
            24,
            $vncPassword,
            $mcpBearer
        );
        $this->setPrivateProperty($domainInstance, 'id', $instanceId);
        $domainInstance->generateDerivedFields('example.test');

        // Provide instance type config so mapping yields vnc external paths
        $typesConfig->method('getTypeConfig')->willReturnCallback(function (InstanceType $t): ?InstanceTypeConfig {
            if ($t !== InstanceType::PLAYWRIGHT_V1) {
                return null;
            }

            return new InstanceTypeConfig(
                'Playwright v1',
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
        $title = $crawler->filter('h1.etfswui-pagetitle');
        $this->assertCount(1, $title);
        $this->assertSame('MCP Instance Details', trim($title->text()));

        // General Instance Data card exists
        $general = $crawler->filterXPath("//div[contains(@class,'etfswui-card')][.//span[contains(@class,'etfswui-card-title-text') and normalize-space(.)='General Instance Data']]");
        $this->assertGreaterThan(0, $general->count());

        // VNC Password input present
        $vncInput = $crawler->filter('input[aria-label="VNC Password"]');
        $this->assertGreaterThan(0, $vncInput->count());

        // MCP Bearer token input present
        $bearerInput = $crawler->filter('input[aria-label="MCP Bearer Token"]');
        $this->assertGreaterThan(0, $bearerInput->count());

        // MCP Endpoint(s) card
        $mcpCard = $crawler->filterXPath("//div[contains(@class,'etfswui-card')][.//span[contains(@class,'etfswui-card-title-text') and normalize-space(.)='MCP Endpoint(s)']]");
        $this->assertGreaterThan(0, $mcpCard->count());
    }

    private function newDomainInstance(
        string       $accountCoreId,
        InstanceType $type,
        int          $screenWidth,
        int          $screenHeight,
        int          $colorDepth,
        string       $vncPassword,
        string       $mcpBearer
    ): DomainMcpInstance {
        return new DomainMcpInstance(
            $accountCoreId,
            $type,
            $screenWidth,
            $screenHeight,
            $colorDepth,
            $vncPassword,
            $mcpBearer
        );
    }

    private function setPrivateProperty(object $object, string $property, mixed $value): void
    {
        $ref = new ReflectionProperty($object, $property);
        $ref->setAccessible(true);
        $ref->setValue($object, $value);
    }
}
