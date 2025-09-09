<?php

declare(strict_types=1);

namespace App\Tests\Unit\McpInstances\Presentation\Controller;

use App\Account\Domain\Entity\AccountCore;
use App\DockerManagement\Facade\DockerManagementFacadeInterface;
use App\McpInstances\Domain\Config\Dto\EndpointConfig;
use App\McpInstances\Domain\Config\Dto\InstanceDockerConfig;
use App\McpInstances\Domain\Config\Dto\InstanceTypeConfig;
use App\McpInstances\Domain\Config\Service\InstanceTypesConfigServiceInterface;
use App\McpInstances\Domain\Dto\EndpointStatusDto;
use App\McpInstances\Domain\Dto\InstanceStatusDto;
use App\McpInstances\Domain\Dto\ProcessStatusContainerDto;
use App\McpInstances\Domain\Dto\ProcessStatusDto;
use App\McpInstances\Domain\Dto\ServiceStatusDto;
use App\McpInstances\Domain\Entity\McpInstance as DomainMcpInstance;
use App\McpInstances\Domain\Enum\InstanceType;
use App\McpInstances\Domain\Service\McpInstancesDomainServiceInterface;
use App\McpInstances\Presentation\Controller\InstancesController;
use App\McpInstances\Presentation\McpInstancesPresentationService;
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

        $domainInstance = $this->newDomainInstance(
            $accountId,
            InstanceType::PLAYWRIGHT_V1,
            1280,
            720,
            24,
            $vncPassword,
            $mcpBearer
        );
        // Set ID and derived fields
        $this->setPrivateProperty($domainInstance, 'id', $instanceId);
        $domainInstance->generateDerivedFields('example.test');

        // Mock type config to provide display name and endpoint paths
        $typesConfig->method('getTypeConfig')
            ->willReturnCallback(function (InstanceType $t): ?InstanceTypeConfig {
                if ($t !== InstanceType::PLAYWRIGHT_V1) {
                    return null;
                }

                return new InstanceTypeConfig(
                    'Playwright v1',
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

        // Docker facade returns generic instance status used by the dashboard
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

            public function getUser(): ?UserInterface
            {
                $user = new AccountCore('user@example.test', 'hash');
                // Inject an ID so AbstractAccountAwareController accepts it
                $ref = new ReflectionProperty(AccountCore::class, 'id');
                $ref->setAccessible(true);
                $ref->setValue($user, $this->accountId);

                if ($this->accountId === '') {
                    return null;
                }

                return $user;
            }
        };

        $response = $controller->dashboardAction();
        $html     = (string) $response->getContent();

        // Use Symfony DomCrawler for structured assertions
        $crawler = new Crawler($html);

        // Page title exists
        $title = $crawler->filter('h1.etfswui-pagetitle');
        $this->assertCount(1, $title);
        $this->assertSame('MCP Instance Dashboard', trim($title->text()));

        // Instance display name present
        $displayNode = $crawler->filterXPath("//div[contains(@class,'etfswui-text') and contains(., 'Playwright v1')]");
        $this->assertGreaterThan(0, $displayNode->count());

        // General Instance Data card exists
        $generalCard = $crawler->filterXPath("//div[contains(@class,'etfswui-card')][.//span[contains(@class,'etfswui-card-title-text') and normalize-space(.)='General Instance Data']]");
        $this->assertGreaterThan(0, $generalCard->count());

        // MCP Bearer token input present with expected value
        $bearerInput = $crawler->filter('input[aria-label="MCP Bearer Token"]');
        $this->assertCount(1, $bearerInput);
        $this->assertSame($mcpBearer, (string) $bearerInput->attr('value'));

        // MCP Endpoint(s) card contains both paths /mcp and /sse
        $mcpCard = $crawler->filterXPath("//div[contains(@class,'etfswui-card')][.//span[contains(@class,'etfswui-card-title-text') and normalize-space(.)='MCP Endpoint(s)']]");
        $this->assertGreaterThan(0, $mcpCard->count());
        $this->assertStringContainsString('/mcp', $mcpCard->text());
        $this->assertStringContainsString('/sse', $mcpCard->text());

        // VNC Web Client card contains password and link
        $vncCard = $crawler->filterXPath("//div[contains(@class,'etfswui-card')][.//span[contains(@class,'etfswui-card-title-text') and normalize-space(.)='VNC Web Client']]");
        $this->assertGreaterThan(0, $vncCard->count());
        $this->assertSame($vncPassword, (string) $vncCard->filter('input[aria-label="VNC Password"]')->attr('value'));
        $this->assertGreaterThan(0, $vncCard->filter('a[target="_blank"]')->count());

        // Actions contain the two forms with expected action paths (via stubbed path())
        $actionsCard = $crawler->filterXPath("//div[contains(@class,'etfswui-card')][.//span[contains(@class,'etfswui-card-title-text') and normalize-space(.)='Actions']]");
        $this->assertGreaterThan(0, $actionsCard->count());
        $this->assertGreaterThan(0, $actionsCard->filter('form[action="/mcp_instances.presentation.recreate_container"]')->count());
        $this->assertGreaterThan(0, $actionsCard->filter('form[action="/mcp_instances.presentation.stop"]')->count());
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
