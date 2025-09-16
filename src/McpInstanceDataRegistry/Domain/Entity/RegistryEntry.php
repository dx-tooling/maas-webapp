<?php

declare(strict_types=1);

namespace App\McpInstanceDataRegistry\Domain\Entity;

use App\McpInstanceDataRegistry\Facade\Dto\RegistryEntryDto;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use EnterpriseToolingForSymfony\SharedBundle\DateAndTime\Service\DateAndTimeService;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;

#[ORM\Entity]
#[ORM\Table(name: 'mcp_instance_data_registry')]
#[ORM\Index(columns: ['instance_id', 'registry_key'], name: 'idx_instance_key')]
#[ORM\UniqueConstraint(columns: ['instance_id', 'registry_key'])]
class RegistryEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\Column(type: Types::GUID, unique: true)]
    private ?string $id = null;

    #[ORM\Column(type: Types::STRING, length: 36, nullable: false)]
    private string $instanceId;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: false)]
    private string $registryKey;

    #[ORM\Column(type: Types::TEXT, nullable: false)]
    private string $registryValue;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: false)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: false)]
    private DateTimeImmutable $updatedAt;

    public function __construct(
        string $instanceId,
        string $registryKey,
        string $registryValue
    ) {
        $this->instanceId    = $instanceId;
        $this->registryKey   = $registryKey;
        $this->registryValue = $registryValue;
        $this->createdAt     = DateAndTimeService::getDateTimeImmutable();
        $this->updatedAt     = $this->createdAt;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getInstanceId(): string
    {
        return $this->instanceId;
    }

    public function getRegistryKey(): string
    {
        return $this->registryKey;
    }

    public function getRegistryValue(): string
    {
        return $this->registryValue;
    }

    public function setRegistryValue(string $value): void
    {
        $this->registryValue = $value;
        $this->updatedAt     = DateAndTimeService::getDateTimeImmutable();
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function toDto(): RegistryEntryDto
    {
        return new RegistryEntryDto(
            $this->getId() ?? '',
            $this->getInstanceId(),
            $this->getRegistryKey(),
            $this->getRegistryValue(),
            $this->getCreatedAt(),
            $this->getUpdatedAt()
        );
    }
}
