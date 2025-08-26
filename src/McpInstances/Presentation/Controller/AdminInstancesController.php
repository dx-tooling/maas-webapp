<?php

declare(strict_types=1);

namespace App\McpInstances\Presentation\Controller;

use App\Common\Presentation\Controller\AbstractAccountAwareController;
use App\McpInstances\Facade\McpInstancesFacadeInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/mcp-instances')]
class AdminInstancesController extends AbstractAccountAwareController
{
    public function __construct(
        private readonly McpInstancesFacadeInterface $facade
    ) {
    }

    #[Route(
        path: '',
        name: 'mcp_instances.presentation.admin_overview',
        methods: ['GET']
    )]
    public function overviewAction(): Response
    {
        $instances = $this->facade->getMcpInstanceAdminOverview();

        return $this->render(
            '@mcp_instances.presentation/admin_overview.html.twig',
            [
                'instances' => $instances,
            ]
        );
    }
}
