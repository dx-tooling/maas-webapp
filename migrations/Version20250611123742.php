<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250611123742 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE account_cores (
              id CHAR(36) NOT NULL COMMENT '(DC2Type:guid)',
              created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
              email VARCHAR(1024) NOT NULL,
              password_hash VARCHAR(1024) NOT NULL,
              roles JSON NOT NULL COMMENT '(DC2Type:json)',
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DROP TABLE account_cores
        SQL);
    }
}
