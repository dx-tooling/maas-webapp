<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250808133652 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Migrate McpInstance to Docker-based architecture';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mcp_instances ADD instance_slug VARCHAR(32) DEFAULT NULL, ADD container_name VARCHAR(128) DEFAULT NULL, ADD container_state VARCHAR(255) NOT NULL, ADD mcp_bearer VARCHAR(128) NOT NULL, ADD mcp_subdomain VARCHAR(255) DEFAULT NULL, ADD vnc_subdomain VARCHAR(255) DEFAULT NULL, DROP display_number, DROP mcp_port, DROP vnc_port, DROP websocket_port, DROP mcp_proxy_port');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mcp_instances ADD display_number INT NOT NULL, ADD mcp_port INT NOT NULL, ADD vnc_port INT NOT NULL, ADD websocket_port INT NOT NULL, ADD mcp_proxy_port INT NOT NULL, DROP instance_slug, DROP container_name, DROP container_state, DROP mcp_bearer, DROP mcp_subdomain, DROP vnc_subdomain');
    }
}
