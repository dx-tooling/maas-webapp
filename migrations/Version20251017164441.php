<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251017164441 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE etfs_shared_bundle_command_run_summaries (
              id CHAR(36) NOT NULL COMMENT '(DC2Type:guid)',
              command_name VARCHAR(512) NOT NULL,
              arguments VARCHAR(1024) NOT NULL,
              options VARCHAR(1024) NOT NULL,
              hostname VARCHAR(1024) NOT NULL,
              envvars VARCHAR(8192) NOT NULL,
              started_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
              finished_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
              finished_due_to_no_initial_lock TINYINT(1) NOT NULL,
              finished_due_to_got_behind_lock TINYINT(1) NOT NULL,
              finished_due_to_failed_to_update_lock TINYINT(1) NOT NULL,
              finished_due_to_rollout_signal TINYINT(1) NOT NULL,
              finished_normally TINYINT(1) NOT NULL,
              number_of_handled_elements INT NOT NULL,
              max_allocated_memory INT NOT NULL,
              INDEX command_name_started_at_idx (command_name, started_at),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE etfs_shared_bundle_signals (
              name VARCHAR(64) NOT NULL,
              created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
              PRIMARY KEY(name)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql('DROP TABLE command_run_summaries');
        $this->addSql('DROP TABLE signals');
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE command_run_summaries (
              id CHAR(36) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci` COMMENT '(DC2Type:guid)',
              command_name VARCHAR(512) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`,
              arguments VARCHAR(1024) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`,
              options VARCHAR(1024) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`,
              hostname VARCHAR(1024) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`,
              envvars VARCHAR(8192) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`,
              started_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
              finished_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
              finished_due_to_no_initial_lock TINYINT(1) NOT NULL,
              finished_due_to_got_behind_lock TINYINT(1) NOT NULL,
              finished_due_to_failed_to_update_lock TINYINT(1) NOT NULL,
              finished_due_to_rollout_signal TINYINT(1) NOT NULL,
              finished_normally TINYINT(1) NOT NULL,
              number_of_handled_elements INT NOT NULL,
              max_allocated_memory INT NOT NULL,
              INDEX command_name_started_at_idx (command_name, started_at),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = ''
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE signals (
              name VARCHAR(64) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`,
              created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
              PRIMARY KEY(name)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = ''
        SQL);
        $this->addSql('DROP TABLE etfs_shared_bundle_command_run_summaries');
        $this->addSql('DROP TABLE etfs_shared_bundle_signals');
    }
}
