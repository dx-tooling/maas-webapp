<?php

declare(strict_types=1);

namespace App\McpInstancesManagement\Domain\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;

#[ORM\Entity]
#[ORM\Table(name: 'mcp_instance_environment_variables')]
class McpInstanceEnvironmentVariable
{
    public function __construct(
        string      $key,
        string      $value,
        McpInstance $mcpInstance
    ) {
        $this->key         = $key;
        $this->value       = $value;
        $this->mcpInstance = $mcpInstance;
    }

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\Column(type: Types::GUID, unique: true)]
    private ?string $id = null;

    #[ORM\Column(name: 'env_key', type: Types::STRING, length: 255)]
    private string $key;

    #[ORM\Column(type: Types::TEXT)]
    private string $value;

    #[ORM\ManyToOne(targetEntity: McpInstance::class, inversedBy: 'environmentVariables')]
    #[ORM\JoinColumn(nullable: false)]
    private McpInstance $mcpInstance;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function setKey(string $key): void
    {
        $this->key = $key;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): void
    {
        $this->value = $value;
    }

    public function getMcpInstance(): McpInstance
    {
        return $this->mcpInstance;
    }

    public function setMcpInstance(McpInstance $mcpInstance): void
    {
        $this->mcpInstance = $mcpInstance;
    }
}
