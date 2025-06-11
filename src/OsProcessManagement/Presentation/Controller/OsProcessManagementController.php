<?php

declare(strict_types=1);

namespace App\OsProcessManagement\Presentation\Controller;

use App\McpInstances\Facade\McpInstancesFacadeInterface;
use App\OsProcessManagement\Domain\Service\OsProcessManagementDomainService;
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
        OsProcessManagementDomainService $service,
        McpInstancesFacadeInterface      $mcpInstancesFacade,
        Request                          $request
    ): Response {
        $selectedInstanceId = $request->query->get('instance');
        $mcpInstances       = $mcpInstancesFacade->getMcpInstanceInfos();

        // Build lookup tables for fast matching
        $displayToInstance       = [];
        $mcpPortToInstance       = [];
        $vncPortToInstance       = [];
        $websocketPortToInstance = [];
        foreach ($mcpInstances as $instance) {
            $displayToInstance[$instance->displayNumber]       = $instance->id;
            $mcpPortToInstance[$instance->mcpPort]             = $instance->id;
            $vncPortToInstance[$instance->vncPort]             = $instance->id;
            $websocketPortToInstance[$instance->websocketPort] = $instance->id;
        }

        // Helper to filter/annotate
        $annotate = function (array $processes, string $type) use ($displayToInstance, $mcpPortToInstance, $vncPortToInstance, $websocketPortToInstance, $selectedInstanceId) {
            $result = [];
            foreach ($processes as $proc) {
                $instanceId = null;
                if ($type === 'xvfb' && isset($displayToInstance[$proc->displayNumber])) {
                    $instanceId = $displayToInstance[$proc->displayNumber];
                } elseif ($type === 'mcp' && isset($mcpPortToInstance[$proc->mcpPort])) {
                    $instanceId = $mcpPortToInstance[$proc->mcpPort];
                } elseif ($type === 'vnc' && isset($vncPortToInstance[$proc->port])) {
                    $instanceId = $vncPortToInstance[$proc->port];
                } elseif ($type === 'ws' && isset($websocketPortToInstance[$proc->httpPort])) {
                    $instanceId = $websocketPortToInstance[$proc->httpPort];
                }
                if ($selectedInstanceId === null || $instanceId === $selectedInstanceId) {
                    $result[] = [
                        'proc'       => $proc,
                        'instanceId' => $instanceId,
                    ];
                }
            }

            return $result;
        };

        $virtualFramebuffers = $annotate($service->getRunningVirtualFramebuffers(), 'xvfb');
        $playwrightMcps      = $annotate($service->getRunningPlaywrightMcps(), 'mcp');
        $vncServers          = $annotate($service->getRunningVncServers(), 'vnc');
        $vncWebsockets       = $annotate($service->getRunningVncWebsockets(), 'ws');

        return $this->render(
            '@os_process_management.presentation/dashboard.html.twig',
            [
                'virtualFramebuffers' => $virtualFramebuffers,
                'playwrightMcps'      => $playwrightMcps,
                'vncServers'          => $vncServers,
                'vncWebsockets'       => $vncWebsockets,
                'mcpInstances'        => $mcpInstances,
                'selectedInstanceId'  => $selectedInstanceId,
            ]
        );
    }

    #[Route(
        path   : '/os-process-management/launch',
        name   : 'os_process_management.presentation.launch',
        methods: [Request::METHOD_POST]
    )]
    public function launchPlaywrightSetupAction(Request $request): RedirectResponse
    {
        $displayNumber = (int)$request->request->get('displayNumber', 99);
        $screenWidth   = (int)$request->request->get('screenWidth', 1280);
        $screenHeight  = (int)$request->request->get('screenHeight', 720);
        $colorDepth    = (int)$request->request->get('colorDepth', 24);
        $mcpPort       = (int)$request->request->get('mcpPort', 11111);
        $vncPort       = (int)$request->request->get('vncPort', 22222);
        $websocketPort = (int)$request->request->get('websocketPort', 33333);
        $vncPassword   = (string)$request->request->get('vncPassword', '');

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
