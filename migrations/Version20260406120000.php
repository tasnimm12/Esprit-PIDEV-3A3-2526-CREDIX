<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260406120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add montant_collecte, admin_id, created_at, updated_at to projet table and update investissement relationships';
    }

    public function up(Schema $schema): void
    {
        // Add missing columns to projet table - check if they exist first
        $projetTable = $schema->getTable('projet');
        
        if (!$projetTable->hasColumn('montant_collecte')) {
            $this->addSql('ALTER TABLE projet ADD montant_collecte NUMERIC(15, 2) DEFAULT NULL');
        }
        if (!$projetTable->hasColumn('admin_id')) {
            $this->addSql('ALTER TABLE projet ADD admin_id INT NOT NULL DEFAULT 1');
            // Add foreign key only if column was just added
            $this->addSql('ALTER TABLE projet ADD CONSTRAINT FK_2FB3D0EE642B8210 FOREIGN KEY (admin_id) REFERENCES utilisateur (id_user)');
        }
        if (!$projetTable->hasColumn('created_at')) {
            $this->addSql('ALTER TABLE projet ADD created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
        }
        if (!$projetTable->hasColumn('updated_at')) {
            $this->addSql('ALTER TABLE projet ADD updated_at DATETIME DEFAULT NULL');
        }
        
        // Update investissement table - add columns if they don't exist
        $investissementTable = $schema->getTable('investissement');
        
        if (!$investissementTable->hasColumn('compte_id')) {
            $this->addSql('ALTER TABLE investissement ADD compte_id INT DEFAULT NULL');
            $this->addSql('ALTER TABLE investissement ADD CONSTRAINT FK_F0D9DDE8F2DBA776 FOREIGN KEY (compte_id) REFERENCES compte_bancaire (id)');
        }
        
        if (!$investissementTable->hasColumn('created_at')) {
            $this->addSql('ALTER TABLE investissement ADD created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
        }
        if (!$investissementTable->hasColumn('updated_at')) {
            $this->addSql('ALTER TABLE investissement ADD updated_at DATETIME DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        // Drop foreign keys first
        $this->addSql('ALTER TABLE projet DROP FOREIGN KEY FK_2FB3D0EE642B8210');
        $this->addSql('ALTER TABLE investissement DROP FOREIGN KEY FK_F0D9DDE8F2DBA776');
        
        // Drop columns
        $this->addSql('ALTER TABLE projet DROP COLUMN montant_collecte');
        $this->addSql('ALTER TABLE projet DROP COLUMN admin_id');
        $this->addSql('ALTER TABLE projet DROP COLUMN created_at');
        $this->addSql('ALTER TABLE projet DROP COLUMN updated_at');
        
        $this->addSql('ALTER TABLE investissement DROP COLUMN compte_id');
        $this->addSql('ALTER TABLE investissement DROP COLUMN created_at');
        $this->addSql('ALTER TABLE investissement DROP COLUMN updated_at');
    }
}
