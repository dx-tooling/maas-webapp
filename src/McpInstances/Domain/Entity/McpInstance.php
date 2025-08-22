<?php

declare(strict_types=1);

namespace App\McpInstances\Domain\Entity;

use App\McpInstances\Domain\Enum\ContainerState;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use EnterpriseToolingForSymfony\SharedBundle\DateAndTime\Service\DateAndTimeService;
use Exception;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;

#[ORM\Entity]
#[ORM\Table(name: 'mcp_instances')]
class McpInstance
{
    /**
     * @throws Exception
     */
    public function __construct(
        string $accountCoreId,
        int    $screenWidth,
        int    $screenHeight,
        int    $colorDepth,
        string $vncPassword,
        string $mcpBearer
    ) {
        $this->accountCoreId = $accountCoreId;
        $this->screenWidth   = $screenWidth;
        $this->screenHeight  = $screenHeight;
        $this->colorDepth    = $colorDepth;
        $this->vncPassword   = $vncPassword;
        $this->mcpBearer     = $mcpBearer;
        $this->createdAt     = DateAndTimeService::getDateTimeImmutable();

        // Generate derived fields after ID is set
        $this->containerState = ContainerState::CREATED;
    }

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\Column(
        type  : Types::GUID,
        unique: true
    )]
    private ?string $id = null;

    public function getId(): ?string
    {
        return $this->id;
    }

    #[ORM\Column(
        type    : Types::DATETIME_IMMUTABLE,
        nullable: false
    )]
    private readonly DateTimeImmutable $createdAt;

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    #[ORM\Column(type: Types::STRING, length: 64, nullable: false)]
    private string $accountCoreId;

    #[ORM\Column(type: Types::STRING, length: 32, nullable: true)]
    private ?string $instanceSlug = null;

    #[ORM\Column(type: Types::STRING, length: 128, nullable: true)]
    private ?string $containerName = null;

    #[ORM\Column(type: Types::STRING, enumType: ContainerState::class, nullable: false)]
    private ContainerState $containerState;

    #[ORM\Column(type: Types::INTEGER, nullable: false)]
    private int $screenWidth;

    #[ORM\Column(type: Types::INTEGER, nullable: false)]
    private int $screenHeight;

    #[ORM\Column(type: Types::INTEGER, nullable: false)]
    private int $colorDepth;

    #[ORM\Column(type: Types::STRING, length: 128, nullable: false)]
    private string $vncPassword;

    #[ORM\Column(type: Types::STRING, length: 128, nullable: false)]
    private string $mcpBearer;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $mcpSubdomain = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $vncSubdomain = null;

    public function getAccountCoreId(): string
    {
        return $this->accountCoreId;
    }

    public function getInstanceSlug(): ?string
    {
        return $this->instanceSlug;
    }

    public function getContainerName(): ?string
    {
        return $this->containerName;
    }

    public function getContainerState(): ContainerState
    {
        return $this->containerState;
    }

    public function getScreenWidth(): int
    {
        return $this->screenWidth;
    }

    public function getScreenHeight(): int
    {
        return $this->screenHeight;
    }

    public function getColorDepth(): int
    {
        return $this->colorDepth;
    }

    public function getVncPassword(): string
    {
        return $this->vncPassword;
    }

    public function getMcpBearer(): string
    {
        return $this->mcpBearer;
    }

    public function getMcpSubdomain(): ?string
    {
        return $this->mcpSubdomain;
    }

    public function getVncSubdomain(): ?string
    {
        return $this->vncSubdomain;
    }

    public function setInstanceSlug(string $instanceSlug): void
    {
        $this->instanceSlug = $instanceSlug;
    }

    public function setContainerName(string $containerName): void
    {
        $this->containerName = $containerName;
    }

    public function setContainerState(ContainerState $containerState): void
    {
        $this->containerState = $containerState;
    }

    public function setMcpSubdomain(string $mcpSubdomain): void
    {
        $this->mcpSubdomain = $mcpSubdomain;
    }

    public function setVncSubdomain(string $vncSubdomain): void
    {
        $this->vncSubdomain = $vncSubdomain;
    }

    public function generateDerivedFields(string $rootDomain = 'mcp-as-a-service.com'): void
    {
        if ($this->id !== null) {
            // Derive a short, DNS-safe slug from the UUID using base36 (0-9a-z)
            // 1) Strip hyphens, lowercase to get 32 hex chars (128 bits)
            // 2) Take first 16 hex chars (64 bits), convert to base36 → ~13 chars max
            // 3) Trim to 8 chars for compactness; uniqueness is still extremely high
            $hex                 = strtolower(str_replace('-', '', $this->id));
            $hex64               = substr($hex, 0, 16);
            $base36              = strtolower(base_convert($hex64, 16, 36));
            $shortSlug           = substr($base36, 0, 8);
            $this->instanceSlug  = $shortSlug;
            $this->containerName = 'mcp-instance-' . $shortSlug;
            $this->mcpSubdomain  = 'mcp-' . $shortSlug . '.' . $rootDomain;
            $this->vncSubdomain  = 'vnc-' . $shortSlug . '.' . $rootDomain;
        }
    }

    public static function generateRandomPassword(int $length = 24): string
    {
        if ($length < 1) {
            $length = 24;
        }

        return rtrim(strtr(base64_encode(random_bytes($length)), '+/', '-_'), '=');
    }

    public static function generateRandomBearer(int $length = 32): string
    {
        if ($length < 1) {
            $length = 32;
        }

        return rtrim(strtr(base64_encode(random_bytes($length)), '+/', '-_'), '=');
    }
}
