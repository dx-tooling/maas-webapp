<?php

declare(strict_types=1);

namespace App\McpInstancesManagement\Facade;

use App\McpInstancesManagement\Domain\Entity\McpInstance as McpInstanceEntity;
use App\McpInstancesManagement\Facade\Dto\McpInstanceDto;
use Doctrine\ORM\EntityManagerInterface;

final readonly class McpInstancesManagementFacade implements McpInstancesManagementFacadeInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function toDto(McpInstanceEntity $e): McpInstanceDto
    {
        return new McpInstanceDto(
            $e->getId() ?? '',
            $e->getCreatedAt(),
            $e->getAccountCoreId(),
            $e->getInstanceSlug(),
            $e->getContainerName(),
            ContainerState::from($e->getContainerState()->value),
            InstanceType::from($e->getInstanceType()->value),
            $e->getScreenWidth(),
            $e->getScreenHeight(),
            $e->getColorDepth(),
            $e->getVncPassword(),
            $e->getMcpBearer(),
            $e->getMcpSubdomain(),
            $e->getVncSubdomain(),
        );
    }

    public function getById(string $id): ?McpInstanceDto
    {
        $repo = $this->entityManager->getRepository(McpInstanceEntity::class);
        $ent  = $repo->find($id);

        return $ent ? $this->toDto($ent) : null;
    }
}
