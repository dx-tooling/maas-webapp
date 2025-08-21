<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250813150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add unique index on account_cores.email to prevent duplicate accounts per email';
    }

    public function up(Schema $schema): void
    {
        // Deduplicate existing emails by keeping the lowest UUID per email
        // Works on MySQL/MariaDB
        $this->addSql(<<<'SQL'
DELETE t1 FROM account_cores t1
INNER JOIN account_cores t2
  ON t1.email = t2.email
 AND t1.id > t2.id;
SQL);

        // Add a unique index to enforce uniqueness at the database level with the expected name
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CB89C7FEE7927C74 ON account_cores (email)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_CB89C7FEE7927C74 ON account_cores');
    }
}
