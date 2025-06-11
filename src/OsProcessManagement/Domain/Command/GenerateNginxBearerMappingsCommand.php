<?php

declare(strict_types=1);

namespace App\OsProcessManagement\Domain\Command;

use App\McpInstances\Facade\McpInstancesFacadeInterface;
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
use Throwable;

#[AsCommand(
    name       : 'app:os-process-management:domain:generate-nginx-bearer-mappings',
    description: 'Generates nginx bearer token mappings configuration file based on MCP instance information',
    aliases    : ['gen-nginx-maps']
)]
class GenerateNginxBearerMappingsCommand extends EnhancedCommand
{
    public function __construct(
        RolloutService                               $rolloutService,
        EntityManagerInterface                       $entityManager,
        LoggerInterface                              $logger,
        LockService                                  $lockService,
        ParameterBagInterface                        $parameterBag,
        private readonly McpInstancesFacadeInterface $mcpInstancesFacade,
    ) {
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
        $this
            ->addArgument(
                'output_file',
                InputArgument::REQUIRED,
                'Path to the output nginx mappings configuration file',
                null
            );
    }

    public function execute(
        InputInterface  $input,
        OutputInterface $output
    ): int {
        $outputFile = $input->getArgument('output_file');
        if (!is_string($outputFile)) {
            $output->writeln('<error>Output file path must be a string.</error>');

            return self::FAILURE;
        }

        $instanceInfos = $this->mcpInstancesFacade->getMcpInstanceInfos();
        if (empty($instanceInfos)) {
            $output->writeln('<error>No MCP instances found.</error>');

            return self::FAILURE;
        }

        $config = $this->generateNginxConfig($instanceInfos);

        try {
            if (file_put_contents($outputFile, $config) === false) {
                $output->writeln(sprintf('<error>Failed to write configuration to %s</error>', $outputFile));

                return self::FAILURE;
            }

            $output->writeln(sprintf('<info>Successfully generated nginx mappings configuration at %s</info>', $outputFile));

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->logger->error('Failed to generate nginx mappings configuration', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $output->writeln(sprintf('<error>Failed to generate configuration: %s</error>', $e->getMessage()));

            return self::FAILURE;
        }
    }

    /**
     * @param array<\App\McpInstances\Facade\Dto\McpInstanceInfoDto> $instanceInfos
     */
    private function generateNginxConfig(array $instanceInfos): string
    {
        $config = "# Port mappings\n";
        $config .= "map \$instance_id \$backend_port {\n";
        $config .= "    default \"\";\n";
        foreach ($instanceInfos as $instance) {
            $config .= sprintf("    %s \"%d\";\n", $instance->id, $instance->mcpPort);
        }
        $config .= "}\n\n";

        // Generate token validation maps for each instance
        foreach ($instanceInfos as $instance) {
            $config .= sprintf("# Token validation for instance %s\n", $instance->id);
            $config .= sprintf("map \$http_authorization \$is_valid_%s {\n", $instance->id);
            $config .= "    default \"0\";\n";
            $config .= sprintf("    \"Bearer %s\" \"1\";\n", $instance->password);
            $config .= "}\n\n";
        }

        // Generate final validation map
        $config .= "# Final validation map\n";
        $config .= "map \$instance_id \$is_valid {\n";
        $config .= "    default \"0\";\n";
        foreach ($instanceInfos as $instance) {
            $config .= sprintf("    %s \$is_valid_%s;\n", $instance->id, $instance->id);
        }
        $config .= "}\n";

        return $config;
    }
}
