<?php

declare(strict_types=1);

namespace App\McpInstances\Domain\Entity;

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
        int    $displayNumber,
        int    $screenWidth,
        int    $screenHeight,
        int    $colorDepth,
        int    $mcpPort,
        int    $mcpProxyPort,
        int    $vncPort,
        int    $websocketPort,
        string $vncPassword
    ) {
        $this->accountCoreId = $accountCoreId;
        $this->displayNumber = $displayNumber;
        $this->screenWidth   = $screenWidth;
        $this->screenHeight  = $screenHeight;
        $this->colorDepth    = $colorDepth;
        $this->mcpPort       = $mcpPort;
        $this->mcpProxyPort  = $mcpProxyPort;
        $this->vncPort       = $vncPort;
        $this->websocketPort = $websocketPort;
        $this->vncPassword   = $vncPassword;
        $this->createdAt     = DateAndTimeService::getDateTimeImmutable();
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

    #[ORM\Column(type: Types::INTEGER, nullable: false)]
    private int $displayNumber;

    #[ORM\Column(type: Types::INTEGER, nullable: false)]
    private int $screenWidth;

    #[ORM\Column(type: Types::INTEGER, nullable: false)]
    private int $screenHeight;

    #[ORM\Column(type: Types::INTEGER, nullable: false)]
    private int $colorDepth;

    #[ORM\Column(type: Types::INTEGER, nullable: false)]
    private int $mcpPort;

    #[ORM\Column(type: Types::INTEGER, nullable: false)]
    private int $mcpProxyPort;

    #[ORM\Column(type: Types::INTEGER, nullable: false)]
    private int $vncPort;

    #[ORM\Column(type: Types::INTEGER, nullable: false)]
    private int $websocketPort;

    #[ORM\Column(type: Types::STRING, length: 128, nullable: false)]
    private string $vncPassword;

    public function getAccountCoreId(): string
    {
        return $this->accountCoreId;
    }

    public function getDisplayNumber(): int
    {
        return $this->displayNumber;
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

    public function getMcpPort(): int
    {
        return $this->mcpPort;
    }

    public function getMcpProxyPort(): int
    {
        return $this->mcpProxyPort;
    }

    public function getVncPort(): int
    {
        return $this->vncPort;
    }

    public function getWebsocketPort(): int
    {
        return $this->websocketPort;
    }

    public function getVncPassword(): string
    {
        return $this->vncPassword;
    }

    public static function generateRandomPassword(int $length = 24): string
    {
        if ($length < 1) {
            $length = 24;
        }

        return rtrim(strtr(base64_encode(random_bytes($length)), '+/', '-_'), '=');
    }
}
