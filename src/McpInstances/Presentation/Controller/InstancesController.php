<?php

declare(strict_types=1);

namespace App\McpInstances\Presentation\Controller;

use App\Account\Facade\Dto\AccountCoreInfoDto;
use App\McpInstances\Facade\McpInstancesFacadeInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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

    #[Route('/account/mcp-instances', name: 'mcp_instances.presentation.dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException();
        }
        $accountCoreInfoDto = new AccountCoreInfoDto($user->getUserIdentifier());
        $instances          = $this->facade->getMcpInstanceInfosForAccount($accountCoreInfoDto);
        $instance           = $instances[0] ?? null;
        $instanceId         = str_replace('-', '', $instance->id ?? '');

        return $this->render('@mcp_instances.presentation/instances_dashboard.html.twig', [
            'instance'             => $instance,
            'instance_id_nohyphen' => $instanceId,
        ]);
    }

    #[Route('/account/mcp-instances/create', name: 'mcp_instances.presentation.create', methods: ['POST'])]
    public function create(): Response
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

    #[Route('/account/mcp-instances/stop', name: 'mcp_instances.presentation.stop', methods: ['POST'])]
    public function stop(): Response
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
}
