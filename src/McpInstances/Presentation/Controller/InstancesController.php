<?php

declare(strict_types=1);

namespace App\McpInstances\Presentation\Controller;

use App\Account\Facade\Dto\AccountCoreInfoDto;
use App\McpInstances\Facade\McpInstancesFacadeInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class InstancesController extends AbstractController
{
    public function __construct(
        private McpInstancesFacadeInterface $facade
    ) {
    }

    #[Route(
        path   : '/account/mcp-instances',
        name   : 'mcp_instances.presentation.dashboard',
        methods: ['GET']
    )]
    public function dashboardAction(): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException();
        }
        $accountCoreInfoDto = new AccountCoreInfoDto($user->getUserIdentifier());
        $instances          = $this->facade->getMcpInstanceInfosForAccount($accountCoreInfoDto);
        $instance           = $instances[0] ?? null;
        $instanceId         = str_replace('-', '', $instance->id ?? '');

        // Get process status if instance exists
        $processStatus = null;
        if ($instance) {
            try {
                $processStatus = $this->facade->getProcessStatusForInstance($instance->id ?? '');
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
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException();
        }
        $accountCoreInfoDto = new AccountCoreInfoDto($user->getUserIdentifier());
        $this->facade->createMcpInstance($accountCoreInfoDto);
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
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException();
        }
        $accountCoreInfoDto = new AccountCoreInfoDto($user->getUserIdentifier());
        $this->facade->stopAndRemoveMcpInstance($accountCoreInfoDto);
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
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException();
        }

        $instanceId = (string)$request->request->get('instanceId');
        if (!$instanceId) {
            $this->addFlash('error', 'Instance ID is required.');

            return $this->redirectToRoute('mcp_instances.presentation.dashboard');
        }

        // Verify the user owns this instance
        $accountCoreInfoDto = new AccountCoreInfoDto($user->getUserIdentifier());
        $userInstances      = $this->facade->getMcpInstanceInfosForAccount($accountCoreInfoDto);

        $userOwnsInstance = false;
        foreach ($userInstances as $instance) {
            if ($instance->id === $instanceId) {
                $userOwnsInstance = true;
                break;
            }
        }

        if (!$userOwnsInstance) {
            $this->addFlash('error', 'You can only restart your own processes.');

            return $this->redirectToRoute('mcp_instances.presentation.dashboard');
        }

        try {
            $success = $this->facade->restartProcessesForInstance($instanceId);
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
}
