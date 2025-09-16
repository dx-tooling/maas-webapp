<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250916000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add registryBearer column to mcp_instances table for separate registry authentication';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mcp_instances ADD registry_bearer VARCHAR(128) DEFAULT NULL');
        // Generate random tokens for existing instances using UUID
        $this->addSql("UPDATE mcp_instances SET registry_bearer = CONCAT('reg_', MD5(CONCAT(UUID(), RAND()))) WHERE registry_bearer IS NULL");
        // Make the column NOT NULL after setting values
        $this->addSql('ALTER TABLE mcp_instances MODIFY registry_bearer VARCHAR(128) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mcp_instances DROP COLUMN registry_bearer');
    }
}
