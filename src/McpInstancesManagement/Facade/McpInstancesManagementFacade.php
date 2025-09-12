<?php

declare(strict_types=1);

namespace App\McpInstancesManagement\Facade;

use App\McpInstancesManagement\Domain\Entity\McpInstance;
use App\McpInstancesManagement\Domain\Entity\McpInstance as McpInstanceEntity;
use App\McpInstancesManagement\Facade\Dto\McpInstanceDto;
use Doctrine\ORM\EntityManagerInterface;

final readonly class McpInstancesManagementFacade implements McpInstancesManagementFacadeInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function getMcpInstanceById(string $id): ?McpInstanceDto
    {
        $repo = $this->entityManager->getRepository(McpInstanceEntity::class);
        $ent  = $repo->find($id);

        return $ent?->toDto();
    }

    public function getMcpInstanceBySlug(string $slug): ?McpInstanceDto
    {
        $repo = $this->entityManager->getRepository(McpInstance::class);
        $ent  = $repo->findOneBy(['instanceSlug' => $slug]);

        return $ent?->toDto();
    }
}
