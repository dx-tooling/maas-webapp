<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250813151000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename unique index on account_cores.email to Doctrine-expected name UNIQ_CB89C7FEE7927C74';
    }

    public function up(Schema $schema): void
    {
        // Conditionally rename only if the legacy index name exists (fresh DBs won't have it)
        $platform = $this->connection->getDatabasePlatform()->getName();
        if ($platform === 'mysql') {
            $exists = (int) $this->connection->fetchOne(
                "SELECT COUNT(1) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'account_cores' AND INDEX_NAME = 'uniq_account_cores_email'"
            );
            if ($exists > 0) {
                $this->addSql('ALTER TABLE account_cores RENAME INDEX uniq_account_cores_email TO UNIQ_CB89C7FEE7927C74');
            }
        }
    }

    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform()->getName();
        if ($platform === 'mysql') {
            $exists = (int) $this->connection->fetchOne(
                "SELECT COUNT(1) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'account_cores' AND INDEX_NAME = 'UNIQ_CB89C7FEE7927C74'"
            );
            if ($exists > 0) {
                $this->addSql('ALTER TABLE account_cores RENAME INDEX UNIQ_CB89C7FEE7927C74 TO uniq_account_cores_email');
            }
        }
    }
}
