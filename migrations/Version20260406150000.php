<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260406150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add prix_assurance and connect assurance with compte_bancaire and depense';
    }

    public function up(Schema $schema): void
    {
        // Add prix_assurance column to assurance table if not exists
        $assuranceTable = $schema->getTable('assurance');
        if (!$assuranceTable->hasColumn('prix_assurance')) {
            $this->addSql('ALTER TABLE assurance ADD prix_assurance NUMERIC(15, 2) DEFAULT NULL');
        }
        
        // Add compte_bancaire_id column to assurance table if not exists
        if (!$assuranceTable->hasColumn('compte_bancaire_id')) {
            $this->addSql('ALTER TABLE assurance ADD compte_bancaire_id BIGINT DEFAULT NULL');
            
            // Add foreign key for compte_bancaire in assurance
            if (!$assuranceTable->hasForeignKey('FK_4BD0C039F2DBA776')) {
                $this->addSql('ALTER TABLE assurance ADD CONSTRAINT FK_4BD0C039F2DBA776 FOREIGN KEY (compte_bancaire_id) REFERENCES compte_bancaire (id)');
            }
            
            // Create index for the foreign key
            if (!$assuranceTable->hasIndex('IDX_4BD0C039F2DBA776')) {
                $this->addSql('CREATE INDEX IDX_4BD0C039F2DBA776 ON assurance (compte_bancaire_id)');
            }
        }
        
        // Add assurance_id column to depense table if not exists
        $depenseTable = $schema->getTable('depense');
        if (!$depenseTable->hasColumn('assurance_id')) {
            $this->addSql('ALTER TABLE depense ADD assurance_id INT DEFAULT NULL');
            
            // Add foreign key for assurance in depense
            if (!$depenseTable->hasForeignKey('FK_CD0D768DFE806C3B')) {
                $this->addSql('ALTER TABLE depense ADD CONSTRAINT FK_CD0D768DFE806C3B FOREIGN KEY (assurance_id) REFERENCES assurance (id)');
            }
            
            // Create index for the foreign key  
            if (!$depenseTable->hasIndex('IDX_CD0D768DFE806C3B')) {
                $this->addSql('CREATE INDEX IDX_CD0D768DFE806C3B ON depense (assurance_id)');
            }
        }
        
        // Convert compte_id to a proper foreign key in depense (if not already done)
        if ($depenseTable->hasColumn('compte_id')) {
            if (!$depenseTable->hasForeignKey('FK_CD0D768DF2DBA776')) {
                $this->addSql('ALTER TABLE depense ADD CONSTRAINT FK_CD0D768DF2DBA776 FOREIGN KEY (compte_id) REFERENCES compte_bancaire (id)');
            }
            
            // Create index for deposit account if not exists
            if (!$depenseTable->hasIndex('IDX_CD0D768DF2DBA776')) {
                try {
                    $this->addSql('CREATE INDEX IDX_CD0D768DF2DBA776 ON depense (compte_id)');
                } catch (\Exception $e) {
                    // Index might already exist
                }
            }
        }
    }

    public function down(Schema $schema): void
    {
        // Drop foreign keys
        $this->addSql('ALTER TABLE assurance DROP FOREIGN KEY FK_4BD0C039F2DBA776');
        $this->addSql('ALTER TABLE depense DROP FOREIGN KEY FK_CD0D768DFE806C3B');
        $this->addSql('ALTER TABLE depense DROP FOREIGN KEY FK_CD0D768DF2DBA776');
        
        // Drop indexes
        $this->addSql('DROP INDEX IDX_4BD0C039F2DBA776 ON assurance');
        $this->addSql('DROP INDEX IDX_CD0D768DFE806C3B ON depense');
        $this->addSql('DROP INDEX IDX_CD0D768DF2DBA776 ON depense');
        
        // Drop columns
        $this->addSql('ALTER TABLE assurance DROP COLUMN prix_assurance');
        $this->addSql('ALTER TABLE assurance DROP COLUMN compte_bancaire_id');
        $this->addSql('ALTER TABLE depense DROP COLUMN assurance_id');
    }
}
