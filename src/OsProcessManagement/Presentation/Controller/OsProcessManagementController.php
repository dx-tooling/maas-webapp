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
                if ($type === 'xvfb' && array_key_exists($proc->displayNumber, $displayToInstance)) {
                    $instanceId = $displayToInstance[$proc->displayNumber];
                } elseif ($type === 'mcp' && array_key_exists($proc->mcpPort, $mcpPortToInstance)) {
                    $instanceId = $mcpPortToInstance[$proc->mcpPort];
                } elseif ($type === 'vnc' && array_key_exists($proc->port, $vncPortToInstance)) {
                    $instanceId = $vncPortToInstance[$proc->port];
                } elseif ($type === 'ws' && array_key_exists($proc->httpPort, $websocketPortToInstance)) {
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

        // Collect sets of numbers/ports present in the process lists
        $displayNumbers = [];
        foreach ($service->getRunningVirtualFramebuffers() as $proc) {
            $displayNumbers[$proc->displayNumber] = true;
        }
        $mcpPorts = [];
        foreach ($service->getRunningPlaywrightMcps() as $proc) {
            $mcpPorts[$proc->mcpPort] = true;
        }
        $vncPorts = [];
        foreach ($service->getRunningVncServers() as $proc) {
            $vncPorts[$proc->port] = true;
        }
        $websocketPorts = [];
        foreach ($service->getRunningVncWebsockets() as $proc) {
            $websocketPorts[$proc->httpPort] = true;
        }

        return $this->render(
            '@os_process_management.presentation/dashboard.html.twig',
            [
                'virtualFramebuffers' => $virtualFramebuffers,
                'playwrightMcps'      => $playwrightMcps,
                'vncServers'          => $vncServers,
                'vncWebsockets'       => $vncWebsockets,
                'mcpInstances'        => $mcpInstances,
                'selectedInstanceId'  => $selectedInstanceId,
                'displayNumbers'      => $displayNumbers,
                'mcpPorts'            => $mcpPorts,
                'vncPorts'            => $vncPorts,
                'websocketPorts'      => $websocketPorts,
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

    #[Route(
        path   : '/os-process-management/stop-instance',
        name   : 'os_process_management.presentation.stop_instance',
        methods: [Request::METHOD_POST]
    )]
    public function stopInstanceAction(Request $request, OsProcessManagementDomainService $service): RedirectResponse
    {
        $displayNumber = (int)$request->request->get('displayNumber');
        $mcpPort       = (int)$request->request->get('mcpPort');
        $vncPort       = (int)$request->request->get('vncPort');
        $websocketPort = (int)$request->request->get('websocketPort');

        // Stop processes in reverse order of their dependencies
        $service->stopPlaywrightMcp($mcpPort);
        $service->stopVncWebsocket($websocketPort);
        $service->stopVncServer($vncPort, $displayNumber);
        $service->stopVirtualFramebuffer($displayNumber);

        $this->addFlash('success', 'Stopped all processes for instance.');

        return $this->redirectToRoute('os_process_management.presentation.dashboard');
    }

    #[Route(
        path   : '/os-process-management/stop-process',
        name   : 'os_process_management.presentation.stop_process',
        methods: [Request::METHOD_POST]
    )]
    public function stopProcessAction(Request $request, OsProcessManagementDomainService $service): RedirectResponse
    {
        $type  = (string)$request->request->get('type');
        $extra = $request->request->all();
        // Stop the process by type
        if ($type === 'xvfb') {
            $service->stopVirtualFramebuffer((int)($extra['displayNumber'] ?? 0));
        } elseif ($type === 'mcp') {
            $service->stopPlaywrightMcp((int)($extra['mcpPort'] ?? 0));
        } elseif ($type === 'vnc') {
            $service->stopVncServer((int)($extra['vncPort'] ?? 0), (int)($extra['displayNumber'] ?? 0));
        } elseif ($type === 'ws') {
            $service->stopVncWebsocket((int)($extra['websocketPort'] ?? 0));
        }
        $this->addFlash('success', 'Stopped process.');

        return $this->redirectToRoute('os_process_management.presentation.dashboard');
    }
}
