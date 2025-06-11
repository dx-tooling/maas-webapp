<?php

declare(strict_types=1);

namespace App\OsProcessManagement\Presentation\Controller;

use App\OsProcessManagement\Facade\OsProcessManagementFacadeInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class OsProcessManagementController extends AbstractController
{
    public function __construct(
        private readonly OsProcessManagementFacadeInterface $facade
    ) {
    }

    #[Route(
        path   : '/os-process-management/dashboard',
        name   : 'os_process_management.presentation.dashboard',
        methods: [Request::METHOD_GET]
    )]
    public function dashboardAction(
        \App\OsProcessManagement\Domain\Service\OsProcessManagementDomainService $service
    ): Response {
        $virtualFramebuffers = $service->getRunningVirtualFramebuffers();
        $playwrightMcps      = $service->getRunningPlaywrightMcps();
        $vncServers          = $service->getRunningVncServers();
        $vncWebsockets       = $service->getRunningVncWebsockets();

        return $this->render('@os_process_management.presentation/dashboard.html.twig', [
            'virtualFramebuffers' => $virtualFramebuffers,
            'playwrightMcps'      => $playwrightMcps,
            'vncServers'          => $vncServers,
            'vncWebsockets'       => $vncWebsockets,
        ]);
    }

    #[Route(
        path   : '/os-process-management/launch',
        name   : 'os_process_management.presentation.launch',
        methods: [Request::METHOD_POST]
    )]
    public function launchPlaywrightSetupAction(Request $request): RedirectResponse
    {
        $displayNumber = (int) $request->request->get('displayNumber', 99);
        $screenWidth   = (int) $request->request->get('screenWidth', 1280);
        $screenHeight  = (int) $request->request->get('screenHeight', 720);
        $colorDepth    = (int) $request->request->get('colorDepth', 24);
        $mcpPort       = (int) $request->request->get('mcpPort', 11111);
        $vncPort       = (int) $request->request->get('vncPort', 22222);
        $websocketPort = (int) $request->request->get('websocketPort', 33333);
        $vncPassword   = (string) $request->request->get('vncPassword', '');

        $this->facade->launchPlaywrightSetup(
            $displayNumber,
            $screenWidth,
            $screenHeight,
            $colorDepth,
            $mcpPort,
            $vncPort,
            $websocketPort,
            $vncPassword
        );

        $this->addFlash('success', 'Playwright setup launched successfully.');

        return $this->redirectToRoute('os_process_management.presentation.dashboard');
    }
}
