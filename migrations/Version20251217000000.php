<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251217000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create mcp_instance_data_registry table for storing instance-specific key-value data';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE mcp_instance_data_registry (
            id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\',
            instance_id VARCHAR(36) NOT NULL,
            registry_key VARCHAR(255) NOT NULL,
            registry_value LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            PRIMARY KEY(id),
            UNIQUE INDEX uniq_instance_key (instance_id, registry_key),
            INDEX idx_instance_key (instance_id, registry_key)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE mcp_instance_data_registry');
    }
}
