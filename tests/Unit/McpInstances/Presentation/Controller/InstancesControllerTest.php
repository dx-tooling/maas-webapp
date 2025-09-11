<?php

declare(strict_types=1);

namespace App\Tests\Unit\McpInstances\Presentation\Controller;

use App\Account\Domain\Entity\AccountCore;
use App\DockerManagement\Facade\DockerManagementFacadeInterface;
use App\McpInstancesConfiguration\Domain\Config\Dto\EndpointConfig;
use App\McpInstancesConfiguration\Domain\Config\Dto\InstanceDockerConfig;
use App\McpInstancesConfiguration\Domain\Config\Dto\InstanceTypeConfig;
use App\McpInstancesConfiguration\Domain\Config\Service\InstanceTypesConfigServiceInterface;
use App\McpInstancesManagement\Domain\Dto\EndpointStatusDto;
use App\McpInstancesManagement\Domain\Dto\InstanceStatusDto;
use App\McpInstancesManagement\Domain\Dto\ProcessStatusContainerDto;
use App\McpInstancesManagement\Domain\Dto\ProcessStatusDto;
use App\McpInstancesManagement\Domain\Dto\ServiceStatusDto;
use App\McpInstancesManagement\Domain\Enum\InstanceType;
use App\McpInstancesManagement\Domain\Service\McpInstancesDomainServiceInterface;
use App\McpInstancesManagement\Presentation\Controller\InstancesController;
use App\McpInstancesManagement\Presentation\McpInstancesPresentationService;
use App\Tests\Support\DomainTestHelper;
use App\Tests\Support\WebUiTestHelper;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;
use Twig\Environment;

class InstancesControllerTest extends TestCase
{
    public function testDashboardRendersExpectedHtmlStructureWithInstancePresent(): void
    {
        $twig = WebUiTestHelper::createTwigEnvironment();

        // Mocks for dependencies outside Presentation layer
        $domainService = $this->createMock(McpInstancesDomainServiceInterface::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $dockerFacade  = $this->createMock(DockerManagementFacadeInterface::class);
        $typesConfig   = $this->createMock(InstanceTypesConfigServiceInterface::class);

        // Prepare a domain entity with stable values
        $accountId   = 'acc-123';
        $instanceId  = '11111111-2222-3333-4444-555555555555';
        $mcpBearer   = 'test-bearer';
        $vncPassword = 'test-vnc-pass';

        $domainInstance = DomainTestHelper::newDomainInstance(
            $accountId,
            InstanceType::PLAYWRIGHT_V1,
            1280,
            720,
            24,
            $vncPassword,
            $mcpBearer
        );
        // Set ID and derived fields
        DomainTestHelper::setPrivateProperty($domainInstance, 'id', $instanceId);
        $domainInstance->generateDerivedFields('example.test');

        // Mock type config to provide display name and endpoint paths
        $typesConfig->method('getTypeConfig')
            ->willReturnCallback(function (InstanceType $t): ?InstanceTypeConfig {
                if ($t !== InstanceType::PLAYWRIGHT_V1) {
                    return null;
                }

                return new InstanceTypeConfig(
                    'Playwright v1',
                    'Playwright automation for web testing',
                    new InstanceDockerConfig('some/image:tag', []),
                    [
                        'mcp' => new EndpointConfig(8080, 'bearer', ['/mcp', '/sse'], null),
                        'vnc' => new EndpointConfig(6080, null, ['/vnc'], null),
                    ]
                );
            });

        // Domain service returns the instance for the account
        $domainService->method('getMcpInstanceInfosForAccount')
            ->willReturn([$domainInstance]);

        // Domain service returns a ProcessStatusDto used by the health component
        $domainService->method('getProcessStatusForInstance')
            ->with($instanceId)
            ->willReturn(new ProcessStatusDto(
                $instanceId,
                new ServiceStatusDto('running', 'running', 'running', 'running'),
                true,
                new ProcessStatusContainerDto(
                    'mcp-instance-abcdef',
                    'running',
                    true,
                    true,
                    true,
                    'https://mcp.example.test/mcp',
                    'https://vnc.example.test/vnc'
                )
            ));

        // Docker facade returns generic instance status used by the dashboard/detail
        $dockerFacade->method('getInstanceStatus')
            ->willReturn(new InstanceStatusDto(
                $instanceId,
                'mcp-instance-abcdef',
                'running',
                true,
                [
                    new EndpointStatusDto('mcp', true, ['https://mcp-example.test/mcp', 'https://mcp-example.test/sse'], true, true),
                    new EndpointStatusDto('vnc', true, ['https://vnc-example.test/vnc'], false, false),
                ]
            ));

        // Presentation service depends on domain lookup by ID for generic status
        $domainService->method('getMcpInstanceById')
            ->with($instanceId)
            ->willReturn($domainInstance);

        $presentation = new McpInstancesPresentationService(
            $domainService,
            $entityManager,
            $dockerFacade,
            $typesConfig
        );

        // Controller that uses our Twig env and returns a fixed authenticated user
        $controller = new class($domainService, $presentation, $twig, $accountId) extends InstancesController {
            public function __construct(
                McpInstancesDomainServiceInterface $domain,
                McpInstancesPresentationService    $presentation,
                private Environment                $twig,
                private string                     $accountId
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

            public function getUser(): UserInterface
            {
                $user = new AccountCore('user@example.test', 'hash');
                $ref  = new ReflectionProperty(AccountCore::class, 'id');
                $ref->setAccessible(true);
                $ref->setValue($user, $this->accountId);

                return $user;
            }
        };

        // 1) Overview page
        $response = $controller->dashboardAction();
        $html     = (string) $response->getContent();
        $crawler  = new Crawler($html);

        $title = $crawler->filter('[data-test-id="page-title"]');
        $this->assertCount(1, $title);
        $this->assertSame('MCP Instances', trim($title->text()));

        // Should list one instance row
        $rows = $crawler->filter('[data-test-class="instances-row"]');
        $this->assertCount(1, $rows);
        $this->assertStringContainsString($instanceId, $rows->text());

        // 2) Detail page
        $response2 = $controller->detailAction($instanceId);
        $html2     = (string) $response2->getContent();
        $c2        = new Crawler($html2);

        // General card present with bearer
        $generalCard = $c2->filter('[data-test-id="card-general"]');
        $this->assertGreaterThan(0, count($generalCard));
        $bearerInput = $c2->filter('[data-test-id="mcp-bearer"]');
        $this->assertCount(1, $bearerInput);
        $bearerVals = $bearerInput->extract(['value']);
        $this->assertSame($mcpBearer, $bearerVals[0] ?? null);

        // MCP endpoints include both paths
        $mcpCard = $c2->filter('[data-test-id="card-mcp-endpoints"]');
        $this->assertGreaterThan(0, count($mcpCard));
        $this->assertStringContainsString('/mcp', $mcpCard->text());
        $this->assertStringContainsString('/sse', $mcpCard->text());
    }
}
