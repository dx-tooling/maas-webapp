<?php

declare(strict_types=1);

namespace App\OsProcessManagement\Domain\Command;

use App\McpInstances\Facade\McpInstancesFacadeInterface;
use App\OsProcessManagement\Domain\Service\OsProcessManagementDomainService;
use Doctrine\ORM\EntityManagerInterface;
use EnterpriseToolingForSymfony\SharedBundle\Commandline\Command\EnhancedCommand;
use EnterpriseToolingForSymfony\SharedBundle\Locking\Service\LockService;
use EnterpriseToolingForSymfony\SharedBundle\Rollout\Service\RolloutService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;


#[AsCommand(
    name       : 'app:os-process-management:domain:generate-nginx-bearer-mappings',
    description: '',
    aliases    : ['gen-nginx-maps']
)]
class GenerateNginxBearerMappingsCommand
    extends EnhancedCommand
{
    public function __construct(
        RolloutService                                    $rolloutService,
        EntityManagerInterface                            $entityManager,
        LoggerInterface                                   $logger,
        LockService                                       $lockService,
        ParameterBagInterface                             $parameterBag,
        private readonly McpInstancesFacadeInterface      $mcpInstancesFacade,
    )
    {
        parent::__construct(
            $rolloutService,
            $entityManager,
            $logger,
            $lockService,
            $parameterBag
        );
    }

    public function configure(): void
    {
        // tbd: nginx mappings file output file path
    }

    public function execute(
        InputInterface  $input,
        OutputInterface $output
    ): int
    {
        $instanceInfos = $this->mcpInstancesFacade->getMcpInstanceInfos();



        return self::SUCCESS;
    }
}
