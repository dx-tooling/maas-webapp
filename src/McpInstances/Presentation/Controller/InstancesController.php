<?php

declare(strict_types=1);

namespace App\McpInstances\Presentation\Controller;

use App\Common\Presentation\Controller\AbstractAccountAwareController;
use App\McpInstances\Domain\Service\McpInstancesDomainService;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Throwable;

#[IsGranted('ROLE_USER')]
class InstancesController extends AbstractAccountAwareController
{
    public function __construct(
        private McpInstancesDomainService $domainService
    ) {
    }

    #[Route(
        path   : '/account/mcp-instances',
        name   : 'mcp_instances.presentation.dashboard',
        methods: ['GET']
    )]
    public function dashboardAction(): Response
    {
        $accountCoreInfoDto = $this->getAuthenticatedAccountCoreInfo();
        $instances          = $this->domainService->getMcpInstanceInfosForAccount($accountCoreInfoDto);
        $instance           = $instances[0] ?? null;
        $instanceId         = str_replace('-', '', $instance?->getId() ?? '');

        // Get process status if instance exists
        $processStatus = null;
        if ($instance) {
            try {
                $processStatus = $this->domainService->getProcessStatusForInstance($instance->getId() ?? '');
            } catch (Exception $e) {
                // If there's an error getting process status, we'll show the instance without status
                $processStatus = null;
            }
        }

        return $this->render(
            '@mcp_instances.presentation/instances_dashboard.html.twig', [
                'instance'             => $instance,
                'instance_id_nohyphen' => $instanceId,
                'process_status'       => $processStatus,
            ]
        );
    }

    #[Route(
        path   : '/account/mcp-instances/create',
        name   : 'mcp_instances.presentation.create',
        methods: ['POST']
    )]
    public function createAction(): Response
    {
        $accountCoreInfoDto = $this->getAuthenticatedAccountCoreInfo();
        $this->domainService->createMcpInstanceForAccount($accountCoreInfoDto);
        $this->addFlash('success', 'MCP Instance created.');

        return $this->redirectToRoute('mcp_instances.presentation.dashboard');
    }

    #[Route(
        path   : '/account/mcp-instances/stop',
        name   : 'mcp_instances.presentation.stop',
        methods: ['POST']
    )]
    public function stopAction(): Response
    {
        $accountCoreInfoDto = $this->getAuthenticatedAccountCoreInfo();
        $this->domainService->stopAndRemoveMcpInstanceForAccount($accountCoreInfoDto);
        $this->addFlash('success', 'MCP Instance stopped and removed.');

        return $this->redirectToRoute('mcp_instances.presentation.dashboard');
    }

    #[Route(
        path   : '/account/mcp-instances/restart-processes',
        name   : 'mcp_instances.presentation.restart_processes',
        methods: ['POST']
    )]
    public function restartProcessesAction(Request $request): Response
    {
        $instanceId = (string)$request->request->get('instanceId');
        if (!$instanceId) {
            $this->addFlash('error', 'Instance ID is required.');

            return $this->redirectToRoute('mcp_instances.presentation.dashboard');
        }

        // Verify the user owns this instance
        $accountCoreInfoDto = $this->getAuthenticatedAccountCoreInfo();
        $userInstances      = $this->domainService->getMcpInstanceInfosForAccount($accountCoreInfoDto);

        $userOwnsInstance = false;
        foreach ($userInstances as $instance) {
            if ($instance->getId() === $instanceId) {
                $userOwnsInstance = true;
                break;
            }
        }

        if (!$userOwnsInstance) {
            $this->addFlash('error', 'You can only restart your own processes.');

            return $this->redirectToRoute('mcp_instances.presentation.dashboard');
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

        return $this->redirectToRoute('mcp_instances.presentation.dashboard');
    }

    #[Route(
        path   : '/account/mcp-instances/recreate-container',
        name   : 'mcp_instances.presentation.recreate_container',
        methods: ['POST']
    )]
    public function recreateContainerAction(Request $request): Response
    {
        $instanceId = (string)$request->request->get('instanceId');
        if ($instanceId === '') {
            $this->addFlash('error', 'Instance ID is required.');

            return $this->redirectToRoute('mcp_instances.presentation.dashboard');
        }

        // Verify ownership
        $accountCoreInfoDto = $this->getAuthenticatedAccountCoreInfo();
        $userInstances      = $this->domainService->getMcpInstanceInfosForAccount($accountCoreInfoDto);

        $userOwnsInstance = false;
        foreach ($userInstances as $instance) {
            if ($instance->getId() === $instanceId) {
                $userOwnsInstance = true;
                break;
            }
        }

        if (!$userOwnsInstance) {
            $this->addFlash('error', 'You can only recreate your own instance container.');

            return $this->redirectToRoute('mcp_instances.presentation.dashboard');
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

        return $this->redirectToRoute('mcp_instances.presentation.dashboard');
    }
}
