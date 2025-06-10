<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250207134706 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            CREATE TABLE command_run_summaries (
                id CHAR(36) NOT NULL COMMENT '(DC2Type:guid)',
                command_name VARCHAR(512) NOT NULL,
                arguments VARCHAR(1024) NOT NULL,
                options VARCHAR(1024) NOT NULL,
                hostname VARCHAR(1024) NOT NULL,
                envvars VARCHAR(8192) NOT NULL,
                started_at DATETIME NOT NULL,
                finished_at DATETIME DEFAULT NULL,
                finished_due_to_no_initial_lock TINYINT(1) NOT NULL,
                finished_due_to_got_behind_lock TINYINT(1) NOT NULL,
                finished_due_to_failed_to_update_lock TINYINT(1) NOT NULL,
                finished_due_to_rollout_signal TINYINT(1) NOT NULL,
                finished_normally TINYINT(1) NOT NULL,
                number_of_handled_elements INT NOT NULL,
                max_allocated_memory INT NOT NULL,
                INDEX command_name_started_at_idx (command_name, started_at),
                PRIMARY KEY(id)
            )
            DEFAULT CHARACTER SET utf8mb4
            COLLATE `utf8mb4_unicode_ci`
            ENGINE = InnoDB
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE command_run_summaries');
    }
}
