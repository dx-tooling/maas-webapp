<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250611150035 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE mcp_instances (
              id CHAR(36) NOT NULL COMMENT '(DC2Type:guid)',
              created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
              account_core_id VARCHAR(64) NOT NULL,
              display_number INT NOT NULL,
              screen_width INT NOT NULL,
              screen_height INT NOT NULL,
              color_depth INT NOT NULL,
              mcp_port INT NOT NULL,
              vnc_port INT NOT NULL,
              websocket_port INT NOT NULL,
              vnc_password VARCHAR(128) NOT NULL,
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DROP TABLE mcp_instances
        SQL);
    }
}
