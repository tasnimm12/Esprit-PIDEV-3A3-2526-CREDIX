<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260405120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add created_at column to depense table and add user_id foreign key';
    }

    public function up(Schema $schema): void
    {
        $depenseTable = $schema->getTable('depense');
        
        // Add user_id if it doesn't exist
        if (!$depenseTable->hasColumn('user_id')) {
            $this->addSql('ALTER TABLE depense ADD user_id BIGINT');
            $this->addSql('ALTER TABLE depense ADD CONSTRAINT FK_D4A60D73A76ED395 FOREIGN KEY (user_id) REFERENCES users (id_user)');
            $this->addSql('CREATE INDEX IDX_D4A60D73A76ED395 ON depense (user_id)');
        }
        
        // Add created_at if it doesn't exist
        if (!$depenseTable->hasColumn('created_at')) {
            $this->addSql('ALTER TABLE depense ADD created_at DATETIME DEFAULT CURRENT_TIMESTAMP');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE depense DROP FOREIGN KEY FK_D4A60D73A76ED395');
        $this->addSql('DROP INDEX IDX_D4A60D73A76ED395 ON depense');
        $this->addSql('ALTER TABLE depense DROP user_id, DROP created_at');
    }
}
