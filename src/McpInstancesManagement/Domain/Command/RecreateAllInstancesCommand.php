<?php

declare(strict_types=1);

namespace App\McpInstancesManagement\Domain\Command;

use App\McpInstancesManagement\Domain\Service\McpInstancesDomainServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use EnterpriseToolingForSymfony\SharedBundle\Commandline\Command\EnhancedCommand;
use EnterpriseToolingForSymfony\SharedBundle\Locking\Service\LockService;
use EnterpriseToolingForSymfony\SharedBundle\Rollout\Service\RolloutService;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(
    name       : 'app:mcp-instances-management:domain:recreate-all-instances',
    description: 'Recreate all MCP instances',
    aliases    : ['recreate-all-instances']
)]
final class RecreateAllInstancesCommand extends EnhancedCommand
{
    public function __construct(
        RolloutService                                      $rolloutService,
        EntityManagerInterface                              $entityManager,
        LoggerInterface                                     $logger,
        LockService                                         $lockService,
        ParameterBagInterface                               $parameterBag,
        private readonly McpInstancesDomainServiceInterface $mcpInstancesDomainService,
    ) {
        parent::__construct(
            $rolloutService,
            $entityManager,
            $logger,
            $lockService,
            $parameterBag
        );
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Starting recreation of all MCP instances...</info>');

        try {
            $success = $this->mcpInstancesDomainService->recreateAllMcpInstances();

            if ($success) {
                $output->writeln('<info>All MCP instances have been recreated successfully.</info>');

                return self::SUCCESS;
            } else {
                $output->writeln('<error>Failed to recreate some MCP instances. Check the logs for details.</error>');

                return self::FAILURE;
            }
        } catch (Exception $e) {
            $output->writeln(sprintf('<error>An error occurred while recreating MCP instances: %s</error>', $e->getMessage()));

            return self::FAILURE;
        }
    }
}
