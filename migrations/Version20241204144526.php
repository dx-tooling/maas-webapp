<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20241204144526 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            CREATE TABLE app_notifications (
                id CHAR(36) NOT NULL COMMENT '(DC2Type:guid)',
                created_at DATETIME NOT NULL COMMENT
                '(DC2Type:datetime_immutable)',
                message VARCHAR(1024) NOT NULL,
                url VARCHAR(1024) NOT NULL,
                type SMALLINT UNSIGNED NOT NULL,
                is_read TINYINT(1) NOT NULL,
                INDEX created_at_is_read_idx (created_at, is_read),
                PRIMARY KEY(id)
            )
            DEFAULT CHARACTER SET utf8mb4
            COLLATE `utf8mb4_unicode_ci`
            ENGINE = InnoDB
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE app_notifications');
    }
}
