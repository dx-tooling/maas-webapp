<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250921120046 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE mcp_instance_environment_variables (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', mcp_instance_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', `key` VARCHAR(255) NOT NULL, value LONGTEXT NOT NULL, INDEX IDX_92A499803C92E4F2 (mcp_instance_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE mcp_instance_environment_variables ADD CONSTRAINT FK_92A499803C92E4F2 FOREIGN KEY (mcp_instance_id) REFERENCES mcp_instances (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE mcp_instance_environment_variables DROP FOREIGN KEY FK_92A499803C92E4F2');
        $this->addSql('DROP TABLE mcp_instance_environment_variables');
    }
}
