<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260407110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add compte_bancaire_id foreign key to contrat_assurance table';
    }

    public function up(Schema $schema): void
    {
        // Add compte_bancaire_id column to contrat_assurance table if not exists
        $contratTable = $schema->getTable('contrat_assurance');
        if (!$contratTable->hasColumn('compte_bancaire_id')) {
            $this->addSql('ALTER TABLE contrat_assurance ADD compte_bancaire_id BIGINT DEFAULT NULL');
            
            // Add foreign key for compte_bancaire in contrat_assurance
            if (!$contratTable->hasForeignKey('FK_9DE5BD26F2DBA776')) {
                $this->addSql('ALTER TABLE contrat_assurance ADD CONSTRAINT FK_9DE5BD26F2DBA776 FOREIGN KEY (compte_bancaire_id) REFERENCES compte_bancaire (id) ON DELETE SET NULL');
            }
            
            // Create index for the foreign key
            if (!$contratTable->hasIndex('IDX_9DE5BD26F2DBA776')) {
                $this->addSql('CREATE INDEX IDX_9DE5BD26F2DBA776 ON contrat_assurance (compte_bancaire_id)');
            }
        }
    }

    public function down(Schema $schema): void
    {
        // Remove the foreign key and column
        $contratTable = $schema->getTable('contrat_assurance');
        if ($contratTable->hasColumn('compte_bancaire_id')) {
            if ($contratTable->hasForeignKey('FK_9DE5BD26F2DBA776')) {
                $this->addSql('ALTER TABLE contrat_assurance DROP FOREIGN KEY FK_9DE5BD26F2DBA776');
            }
            if ($contratTable->hasIndex('IDX_9DE5BD26F2DBA776')) {
                $this->addSql('DROP INDEX IDX_9DE5BD26F2DBA776 ON contrat_assurance');
            }
            $this->addSql('ALTER TABLE contrat_assurance DROP COLUMN compte_bancaire_id');
        }
    }
}
