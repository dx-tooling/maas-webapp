<?php

declare(strict_types=1);

namespace App\Tests\Unit\McpInstancesManagement\Presentation\Controller;

use App\Account\Domain\Entity\AccountCore;
use App\Account\Facade\AccountFacadeInterface;
use App\DockerManagement\Facade\DockerManagementFacadeInterface;
use App\McpInstancesConfiguration\Facade\Service\InstanceTypesConfigFacadeInterface;
use App\McpInstancesManagement\Domain\Service\McpInstancesDomainServiceInterface;
use App\McpInstancesManagement\Presentation\Controller\InstancesController;
use App\McpInstancesManagement\Presentation\McpInstancesPresentationService;
use App\Tests\Support\WebUiTestHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\User\UserInterface;
use Twig\Environment;

final class InstancesControllerEnvironmentVariablesTest extends TestCase
{
    /** @var McpInstancesDomainServiceInterface&MockObject */
    private McpInstancesDomainServiceInterface $domainService;

    private InstancesController $unitUnderTest;

    protected function setUp(): void
    {
        $twig = WebUiTestHelper::createTwigEnvironment('McpInstancesManagement');
        $accountId = 'test-account-id';

        $this->domainService = $this->createMock(McpInstancesDomainServiceInterface::class);
        $accountFacade = $this->createMock(AccountFacadeInterface::class);
        $dockerFacade = $this->createMock(DockerManagementFacadeInterface::class);
        $typesConfig = $this->createMock(InstanceTypesConfigFacadeInterface::class);

        $presentationService = new McpInstancesPresentationService(
            $this->domainService,
            $accountFacade,
            $dockerFacade,
            $typesConfig
        );

        $this->unitUnderTest = new class($this->domainService, $presentationService, $twig, $accountId) extends InstancesController {
            public function __construct(
                McpInstancesDomainServiceInterface $domain,
                McpInstancesPresentationService $presentation,
                private Environment $twig,
                private string $accountId
            ) {
                parent::__construct($domain, $presentation);
            }

            protected function render(string $view, array $parameters = [], ?Response $response = null): Response
            {
                $html = $this->twig->render($view, $parameters);
                return new Response($html);
            }

            public function getUser(): UserInterface
            {
                $user = new AccountCore('test@example.com', 'hash');
                $ref = new ReflectionProperty(AccountCore::class, 'id');
                $ref->setAccessible(true);
                $ref->setValue($user, $this->accountId);
                return $user;
            }

            protected function addFlash(string $type, mixed $message): void
            {
            }

            protected function redirectToRoute(string $route, array $parameters = [], int $status = 302): RedirectResponse
            {
                $url = $route;
                if (!empty($parameters)) {
                    $url .= '?' . http_build_query($parameters);
                }
                return new RedirectResponse($url, $status);
            }
        };
    }

    public function testUpdateEnvironmentVariablesSuccessfully(): void
    {
        $instanceId = 'instance-uuid-123';
        $accountId = 'test-account-id';

        $request = new Request();
        $request->request->set('instanceId', $instanceId);
        $request->request->set('env_keys', ['METABASE_URL', 'METABASE_API_KEY']);
        $request->request->set('env_values', ['https://demo.metabase.com', 'secret-key-123']);

        $this->domainService
            ->expects($this->once())
            ->method('updateEnvironmentVariables')
            ->with(
                $accountId,
                $instanceId,
                [
                    'METABASE_URL' => 'https://demo.metabase.com',
                    'METABASE_API_KEY' => 'secret-key-123'
                ]
            )
            ->willReturn(true);

        $response = $this->unitUnderTest->updateEnvironmentVariables($request);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('mcp_instances_management.presentation.detail', $response->getTargetUrl());
        $this->assertStringContainsString('id=' . $instanceId, $response->getTargetUrl());
    }

    public function testUpdateEnvironmentVariablesThrowsExceptionForMissingInstanceId(): void
    {
        $request = new Request();
        $request->request->set('env_keys', ['TEST_KEY']);
        $request->request->set('env_values', ['test_value']);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Invalid instance ID');

        $this->unitUnderTest->updateEnvironmentVariables($request);
    }

    public function testUpdateEnvironmentVariablesThrowsExceptionForInvalidKeys(): void
    {
        $request = new Request();
        $request->request->set('instanceId', 'instance-123');
        $request->request->set('env_keys', 'not_an_array');
        $request->request->set('env_values', ['test_value']);

        $this->expectException(\Symfony\Component\HttpFoundation\Exception\BadRequestException::class);
        $this->expectExceptionMessage('Unexpected value for parameter "env_keys": expecting "array", got "string".');

        $this->unitUnderTest->updateEnvironmentVariables($request);
    }

    public function testUpdateEnvironmentVariablesThrowsExceptionForMismatchedArrays(): void
    {
        $request = new Request();
        $request->request->set('instanceId', 'instance-123');
        $request->request->set('env_keys', ['KEY1', 'KEY2']);
        $request->request->set('env_values', ['value1']);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Keys and values count mismatch');

        $this->unitUnderTest->updateEnvironmentVariables($request);
    }

    public function testUpdateEnvironmentVariablesValidatesKeyFormat(): void
    {
        $instanceId = 'instance-uuid-123';
        $accountId = 'test-account-id';

        $request = new Request();
        $request->request->set('instanceId', $instanceId);
        $request->request->set('env_keys', ['invalid-key-with-dashes']);
        $request->request->set('env_values', ['test_value']);

        $response = $this->unitUnderTest->updateEnvironmentVariables($request);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('mcp_instances_management.presentation.detail', $response->getTargetUrl());
        $this->assertStringContainsString('id=' . $instanceId, $response->getTargetUrl());
    }

    public function testUpdateEnvironmentVariablesSkipsEmptyKeys(): void
    {
        $instanceId = 'instance-uuid-123';
        $accountId = 'test-account-id';

        $request = new Request();
        $request->request->set('instanceId', $instanceId);
        $request->request->set('env_keys', ['', 'VALID_KEY']);
        $request->request->set('env_values', ['empty_key_value', 'valid_value']);

        $this->domainService
            ->expects($this->once())
            ->method('updateEnvironmentVariables')
            ->with(
                $accountId,
                $instanceId,
                ['VALID_KEY' => 'valid_value']
            )
            ->willReturn(true);

        $response = $this->unitUnderTest->updateEnvironmentVariables($request);

        $this->assertSame(302, $response->getStatusCode());
    }
}
