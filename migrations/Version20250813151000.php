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
        // On MySQL/MariaDB, rename index if the older name exists
        $this->addSql('ALTER TABLE account_cores RENAME INDEX uniq_account_cores_email TO UNIQ_CB89C7FEE7927C74');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE account_cores RENAME INDEX UNIQ_CB89C7FEE7927C74 TO uniq_account_cores_email');
    }
}
