<?php

declare(strict_types=1);

namespace App\McpInstancesManagement\Presentation\Controller;

use App\Common\Presentation\Controller\AbstractAccountAwareController;
use App\McpInstancesManagement\Domain\Service\McpInstancesDomainServiceInterface;
use App\McpInstancesManagement\Facade\Enum\InstanceType;
use App\McpInstancesManagement\Presentation\McpInstancesPresentationService;
use Exception;
use LogicException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Throwable;
use ValueError;

#[IsGranted('ROLE_USER')]
class InstancesController extends AbstractAccountAwareController
{
    public function __construct(
        private readonly McpInstancesDomainServiceInterface $domainService,
        private readonly McpInstancesPresentationService    $presentationService,
    ) {
    }

    #[Route(
        path   : '/account/mcp-instances',
        name   : 'mcp_instances_management.presentation.dashboard',
        methods: ['GET']
    )]
    public function dashboardAction(): Response
    {
        // Keep route name and method for backward compatibility, but render overview
        $accountId      = $this->getAuthenticatedAccountId();
        $instances      = $this->presentationService->getInstancesForAccount($accountId);
        $availableTypes = $this->presentationService->getAvailableTypes();
        $limit          = \App\McpInstancesManagement\Domain\Enum\UsageLimits::MAX_RUNNING_INSTANCES->value;

        return $this->render(
            '@mcp_instances_management.presentation/instances_overview.html.twig', [
                'instances'       => $instances,
                'available_types' => $availableTypes,
                'limit'           => $limit,
            ]
        );
    }

    /**
     * @throws Exception
     */
    #[Route(
        path   : '/account/mcp-instances/create',
        name   : 'mcp_instances_management.presentation.create',
        methods: ['POST']
    )]
    public function createAction(Request $request): Response
    {
        $accountId = $this->getAuthenticatedAccountId();
        $typeValue = (string) $request->request->get('instanceType', '');

        $instanceType = null;
        if ($typeValue !== '') {
            try {
                $instanceType = InstanceType::from($typeValue);
            } catch (ValueError) {
                $this->addFlash('error', 'Invalid instance type selected.');

                return $this->redirectToRoute('mcp_instances_management.presentation.dashboard');
            }
        }

        try {
            $instance = $this->domainService->createMcpInstanceForAccount($accountId, $instanceType);
            $this->addFlash('success', 'MCP Instance created.');

            return $this->redirectToRoute('mcp_instances_management.presentation.detail', ['id' => $instance->getId()]);
        } catch (LogicException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('mcp_instances_management.presentation.dashboard');
    }

    #[Route(
        path   : '/account/mcp-instances/{id}',
        name   : 'mcp_instances_management.presentation.detail',
        methods: ['GET']
    )]
    public function detailAction(string $id): Response
    {
        $accountId   = $this->getAuthenticatedAccountId();
        $instanceDto = $this->presentationService->getMcpInstanceInfoById($id);
        if ($instanceDto === null || $instanceDto->accountCoreId !== $accountId) {
            $this->addFlash('error', 'Instance not found.');

            return $this->redirectToRoute('mcp_instances_management.presentation.dashboard');
        }

        $processStatus = null;
        try {
            $processStatus = $this->presentationService->getProcessStatusForInstance($id);
        } catch (Throwable) {
            $processStatus = null;
        }

        $genericStatus = null;
        try {
            $genericStatus = $this->presentationService->getInstanceStatusForInstance($id);
        } catch (Throwable) {
            $genericStatus = null;
        }

        return $this->render('@mcp_instances_management.presentation/Resources/templates/instances_detail.html.twig', [
            'instance'       => $instanceDto,
            'process_status' => $processStatus,
            'generic_status' => $genericStatus,
        ]);
    }

    #[Route(
        path   : '/account/mcp-instances/stop',
        name   : 'mcp_instances_management.presentation.stop',
        methods: ['POST']
    )]
    public function stopAction(): Response
    {
        $accountId = $this->getAuthenticatedAccountId();
        $this->domainService->stopAndRemoveMcpInstanceForAccount($accountId);
        $this->addFlash('success', 'All MCP Instances for your account stopped and removed.');

        return $this->redirectToRoute('mcp_instances_management.presentation.dashboard');
    }

    #[Route(
        path   : '/account/mcp-instances/{id}/stop',
        name   : 'mcp_instances_management.presentation.stop_single',
        methods: ['POST']
    )]
    public function stopSingleAction(string $id): Response
    {
        $accountId = $this->getAuthenticatedAccountId();
        // Verify ownership
        $userInstances    = $this->domainService->getMcpInstanceInfosForAccount($accountId);
        $userOwnsInstance = false;
        foreach ($userInstances as $inst) {
            if ($inst->getId() === $id) {
                $userOwnsInstance = true;
                break;
            }
        }
        if (!$userOwnsInstance) {
            $this->addFlash('error', 'You can only stop your own instance.');

            return $this->redirectToRoute('mcp_instances_management.presentation.dashboard');
        }

        $this->domainService->stopAndRemoveMcpInstanceById($id);
        $this->addFlash('success', 'MCP Instance stopped and removed.');

        return $this->redirectToRoute('mcp_instances_management.presentation.dashboard');
    }

    #[Route(
        path   : '/account/mcp-instances/restart-processes',
        name   : 'mcp_instances_management.presentation.restart_processes',
        methods: ['POST']
    )]
    public function restartProcessesAction(Request $request): Response
    {
        $instanceId = (string)$request->request->get('instanceId');
        if (!$instanceId) {
            $this->addFlash('error', 'Instance ID is required.');

            return $this->redirectToRoute('mcp_instances_management.presentation.dashboard');
        }

        // Verify the user owns this instance
        $accountId     = $this->getAuthenticatedAccountId();
        $userInstances = $this->domainService->getMcpInstanceInfosForAccount($accountId);

        $userOwnsInstance = false;
        foreach ($userInstances as $instance) {
            if ($instance->getId() === $instanceId) {
                $userOwnsInstance = true;
                break;
            }
        }

        if (!$userOwnsInstance) {
            $this->addFlash('error', 'You can only restart your own processes.');

            return $this->redirectToRoute('mcp_instances_management.presentation.dashboard');
        }

        try {
            $success = $this->domainService->restartMcpInstance($instanceId);
            if ($success) {
                $this->addFlash('success', 'All processes have been restarted successfully.');
            } else {
                $this->addFlash('error', 'Failed to restart some processes. Please try again.');
            }
        } catch (Exception $e) {
            $this->addFlash('error', 'An error occurred while restarting processes: ' . $e->getMessage());
        }

        return $this->redirectToRoute('mcp_instances_management.presentation.dashboard');
    }

    #[Route(
        path   : '/account/mcp-instances/recreate-container',
        name   : 'mcp_instances_management.presentation.recreate_container',
        methods: ['POST']
    )]
    public function recreateContainerAction(Request $request): Response
    {
        $instanceId = (string)$request->request->get('instanceId');
        if ($instanceId === '') {
            $this->addFlash('error', 'Instance ID is required.');

            return $this->redirectToRoute('mcp_instances_management.presentation.dashboard');
        }

        // Verify ownership
        $accountId     = $this->getAuthenticatedAccountId();
        $userInstances = $this->domainService->getMcpInstanceInfosForAccount($accountId);

        $userOwnsInstance = false;
        foreach ($userInstances as $instance) {
            if ($instance->getId() === $instanceId) {
                $userOwnsInstance = true;
                break;
            }
        }

        if (!$userOwnsInstance) {
            $this->addFlash('error', 'You can only recreate your own instance container.');

            return $this->redirectToRoute('mcp_instances_management.presentation.dashboard');
        }

        try {
            $success = $this->domainService->recreateMcpInstanceContainer($instanceId);
            if ($success) {
                $this->addFlash('success', 'Your container has been recreated successfully.');
            } else {
                $this->addFlash('error', 'Failed to recreate your container. Please try again.');
            }
        } catch (Throwable $e) {
            $this->addFlash('error', 'An error occurred while recreating the container: ' . $e->getMessage());
        }

        return $this->redirectToRoute('mcp_instances_management.presentation.detail', ['id' => $instanceId]);
    }

    #[Route('/account/mcp-instances/update-env-vars', name: 'mcp_instances_management.presentation.update_env_vars', methods: ['POST'])]
    public function updateEnvironmentVariables(Request $request): Response
    {
        $instanceId = $request->request->get('instanceId');
        if (!is_string($instanceId) || $instanceId === '') {
            throw new BadRequestHttpException('Invalid instance ID');
        }

        $accountId = $this->getAuthenticatedAccountId();

        $keys   = $request->request->all('env_keys');
        $values = $request->request->all('env_values');

        if (!is_array($keys) || !is_array($values)) {
            throw new BadRequestHttpException('Invalid environment variables data');
        }

        if (count($keys) !== count($values)) {
            throw new BadRequestHttpException('Keys and values count mismatch');
        }

        $envVars = [];
        for ($i = 0; $i < count($keys); ++$i) {
            $key   = trim((string) $keys[$i]);
            $value = trim((string) $values[$i]);

            if ($key === '') {
                continue;
            }

            if (!preg_match('/^[A-Z_][A-Z0-9_]*$/', $key)) {
                $this->addFlash('error', "Invalid environment variable key: {$key}. Keys must contain only uppercase letters, numbers, and underscores, and start with a letter or underscore.");

                return $this->redirectToRoute('mcp_instances_management.presentation.detail', ['id' => $instanceId]);
            }

            $envVars[$key] = $value;
        }

        $success = $this->domainService->updateEnvironmentVariables($accountId, $instanceId, $envVars);
        if (!$success) {
            $this->addFlash('error', 'Failed to update environment variables');
        } else {
            $this->addFlash('success', 'Environment variables updated successfully');
        }

        return $this->redirectToRoute('mcp_instances_management.presentation.detail', ['id' => $instanceId]);
    }
}
