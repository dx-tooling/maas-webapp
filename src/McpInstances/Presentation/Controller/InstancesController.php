<?php

declare(strict_types=1);

namespace App\McpInstances\Presentation\Controller;

use App\Common\Presentation\Controller\AbstractAccountAwareController;
use App\McpInstances\Domain\Enum\InstanceType;
use App\McpInstances\Domain\Service\McpInstancesDomainServiceInterface;
use App\McpInstances\Presentation\McpInstancesPresentationService;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
        name   : 'mcp_instances.presentation.dashboard',
        methods: ['GET']
    )]
    public function dashboardAction(): Response
    {
        $accountCoreInfoDto = $this->getAuthenticatedAccountCoreInfo();
        $dashboardData      = $this->presentationService->getDashboardData($accountCoreInfoDto);

        $instanceId = str_replace('-', '', $dashboardData->instance->id ?? '');

        return $this->render(
            '@mcp_instances.presentation/instances_dashboard.html.twig', [
                'instance'             => $dashboardData->instance,
                'instance_id_nohyphen' => $instanceId,
                'process_status'       => $dashboardData->processStatus,
                'available_types'      => $dashboardData->availableTypes,
            ]
        );
    }

    /**
     * @throws Exception
     */
    #[Route(
        path   : '/account/mcp-instances/create',
        name   : 'mcp_instances.presentation.create',
        methods: ['POST']
    )]
    public function createAction(Request $request): Response
    {
        $accountCoreInfoDto = $this->getAuthenticatedAccountCoreInfo();
        $typeValue          = (string) $request->request->get('instanceType', '');

        $instanceType = null;
        if ($typeValue !== '') {
            try {
                $instanceType = InstanceType::from($typeValue);
            } catch (ValueError) {
                $this->addFlash('error', 'Invalid instance type selected.');

                return $this->redirectToRoute('mcp_instances.presentation.dashboard');
            }
        }

        $this->domainService->createMcpInstanceForAccount($accountCoreInfoDto, $instanceType);
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
