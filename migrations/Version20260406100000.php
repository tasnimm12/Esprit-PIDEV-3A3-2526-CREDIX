<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260406100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add cashback_pourcentage and montant_cashback columns to remboursement table';
    }

    public function up(Schema $schema): void
    {
        $rembourTable = $schema->getTable('remboursement');
        
        // Add cashback_pourcentage if it doesn't exist
        if (!$rembourTable->hasColumn('cashback_pourcentage')) {
            $this->addSql('ALTER TABLE remboursement ADD cashback_pourcentage NUMERIC(5, 2) UNSIGNED DEFAULT NULL');
        }
        
        // Add montant_cashback if it doesn't exist
        if (!$rembourTable->hasColumn('montant_cashback')) {
            $this->addSql('ALTER TABLE remboursement ADD montant_cashback NUMERIC(15, 2) UNSIGNED DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE remboursement DROP cashback_pourcentage, DROP montant_cashback');
    }
}
