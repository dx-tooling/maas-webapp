<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250209133707 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            ALTER TABLE command_run_summaries
            CHANGE started_at started_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            CHANGE finished_at finished_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'
        ");
        $this->addSql("
            ALTER TABLE signals
            CHANGE created_at created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)'
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('
            ALTER TABLE command_run_summaries
            CHANGE started_at started_at DATETIME NOT NULL,
            CHANGE finished_at finished_at DATETIME DEFAULT NULL
        ');
        $this->addSql('
            ALTER TABLE shared_signals
            CHANGE created_at created_at DATETIME NOT NULL
        ');
    }
}
