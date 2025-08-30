<?php

declare(strict_types=1);

namespace App\McpInstances\Presentation\Controller;

use App\Common\Presentation\Controller\AbstractAccountAwareController;
use App\McpInstances\Domain\Service\McpInstancesDomainService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Throwable;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/mcp-instances')]
class AdminInstancesController extends AbstractAccountAwareController
{
    public function __construct(
        private readonly McpInstancesDomainService $domainService
    ) {
    }

    #[Route(
        path: '',
        name: 'mcp_instances.presentation.admin_overview',
        methods: ['GET']
    )]
    public function overviewAction(): Response
    {
        $instances = $this->domainService->getMcpInstanceAdminOverview();

        return $this->render(
            '@mcp_instances.presentation/admin_overview.html.twig',
            [
                'instances' => $instances,
            ]
        );
    }

    #[Route(
        path: '/{id}',
        name: 'mcp_instances.presentation.admin_detail',
        methods: ['GET']
    )]
    public function detailAction(string $id, Request $request): Response
    {
        $instance = $this->domainService->getMcpInstanceById($id);

        if ($instance === null) {
            return $this->redirectToRoute('mcp_instances.presentation.admin_overview');
        }

        // Attempt to fetch process status and container endpoints; ignore failures for resilience
        $processStatus = null;
        try {
            $processStatus = $this->domainService->getProcessStatusForInstance($instance->getId() ?? '');
        } catch (Throwable) {
            $processStatus = null;
        }

        return $this->render(
            '@mcp_instances.presentation/admin_detail.html.twig',
            [
                'instance'       => $instance,
                'process_status' => $processStatus,
            ]
        );
    }

    #[Route(
        path: '/{id}/recreate',
        name: 'mcp_instances.presentation.admin_recreate',
        methods: ['POST']
    )]
    public function recreateAction(string $id): Response
    {
        $success = $this->domainService->recreateMcpInstanceContainer($id);

        if ($success) {
            $this->addFlash('success', 'Container has been recreated successfully.');
        } else {
            $this->addFlash('error', 'Failed to recreate container.');
        }

        return $this->redirectToRoute('mcp_instances.presentation.admin_overview');
    }
}
