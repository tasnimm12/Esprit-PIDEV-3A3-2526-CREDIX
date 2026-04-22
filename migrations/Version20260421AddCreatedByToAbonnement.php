<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260421AddCreatedByToAbonnement extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add created_by column to abonnement table to track custom plan creator';
    }

    public function up(Schema $schema): void
    {
        // Check if created_by column already exists
        $table = $schema->getTable('abonnement');
        if (!$table->hasColumn('created_by')) {
            $this->addSql('ALTER TABLE abonnement ADD created_by INT DEFAULT NULL');
            $this->addSql('ALTER TABLE abonnement ADD CONSTRAINT FK_169C6FB5DE12AB56 FOREIGN KEY (created_by) REFERENCES users (id_user) ON DELETE CASCADE');
            $this->addSql('CREATE INDEX IDX_169C6FB5DE12AB56 ON abonnement (created_by)');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE abonnement DROP FOREIGN KEY FK_169C6FB5DE12AB56');
        $this->addSql('DROP INDEX IDX_169C6FB5DE12AB56 ON abonnement');
        $this->addSql('ALTER TABLE abonnement DROP created_by');
    }
}
