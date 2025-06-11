<?php

declare(strict_types=1);

namespace App\OsProcessManagement\Domain\Command;

use App\OsProcessManagement\Domain\Service\OsProcessManagementDomainService;
use Doctrine\ORM\EntityManagerInterface;
use EnterpriseToolingForSymfony\SharedBundle\Commandline\Command\EnhancedCommand;
use EnterpriseToolingForSymfony\SharedBundle\Locking\Service\LockService;
use EnterpriseToolingForSymfony\SharedBundle\Rollout\Service\RolloutService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(
    name       : 'app:os-process-management:domain:launch-playwright-setup',
    description: '',
    aliases    : ['launch-pw']
)]
class LaunchPlaywrightSetupCommand extends EnhancedCommand
{
    public function __construct(
        RolloutService                                    $rolloutService,
        EntityManagerInterface                            $entityManager,
        LoggerInterface                                   $logger,
        LockService                                       $lockService,
        ParameterBagInterface                             $parameterBag,
        private readonly OsProcessManagementDomainService $processMgmtService
    ) {
        parent::__construct(
            $rolloutService,
            $entityManager,
            $logger,
            $lockService,
            $parameterBag
        );
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'displayNumber',
                InputArgument::REQUIRED,
                'Display number',
                null
            )
            ->addArgument(
                'mcpPort',
                InputArgument::REQUIRED,
                'Port for MCP service',
                null
            )
            ->addArgument(
                'vncPort',
                InputArgument::REQUIRED,
                'Port for VNC service',
                null
            )
            ->addArgument(
                'websocketPort',
                InputArgument::REQUIRED,
                'Port for WebSocket service',
                null
            );
    }

    public function execute(
        InputInterface  $input,
        OutputInterface $output
    ): int {
        $displayNumber = self::toInt($input->getArgument('displayNumber'));
        $mcpPort       = self::toInt($input->getArgument('mcpPort'));
        $vncPort       = self::toInt($input->getArgument('vncPort'));
        $websocketPort = self::toInt($input->getArgument('websocketPort'));

        if (!$this->validatePorts($mcpPort, $vncPort, $websocketPort)) {
            $output->writeln('<error>Port conflict detected. Each service must use a unique port.</error>');

            return self::FAILURE;
        }

        $launched = $this->processMgmtService->launchVirtualFramebuffer(
            $displayNumber,
            1280,
            720,
            24,
        );

        $launched = $this->processMgmtService->launchPlaywrightMcp(
            $mcpPort,
            $displayNumber
        );

        $launched = $this->processMgmtService->launchVncServer(
            $vncPort,
            $displayNumber,
            'test123'
        );

        $launched = $this->processMgmtService->launchVncWebsocket(
            $websocketPort,
            $vncPort
        );

        return self::SUCCESS;
    }

    private static function toInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return 0;
    }

    private function validatePorts(
        int $mcpPort,
        int $vncPort,
        int $websocketPort
    ): bool {
        $ports = [$mcpPort, $vncPort, $websocketPort];

        return count(array_unique($ports)) === count($ports);
    }
}
