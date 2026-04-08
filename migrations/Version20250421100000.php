<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250421100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add abonnement_id and reduction_pourcentage to credit, add reduction_pourcentage to abonnement';
    }

    public function up(Schema $schema): void
    {
        // Add columns to credit table
        $this->addSql('ALTER TABLE credit ADD COLUMN abonnement_id INT NULL');
        $this->addSql('ALTER TABLE credit ADD COLUMN reduction_pourcentage DECIMAL(5,2) NULL');
        $this->addSql('ALTER TABLE credit ADD COLUMN updated_at DATETIME NULL');
        $this->addSql('ALTER TABLE credit MODIFY COLUMN date_debut DATE NULL');
        $this->addSql('ALTER TABLE credit MODIFY COLUMN date_fin DATE NULL');
        $this->addSql('ALTER TABLE credit ADD FOREIGN KEY (abonnement_id) REFERENCES abonnement(id_abonnement)');

        // Add reduction_pourcentage to abonnement table
        $this->addSql('ALTER TABLE abonnement ADD COLUMN reduction_pourcentage DECIMAL(5,2) NULL DEFAULT 0');

        // Set default discount percentages by subscription type
        $this->addSql("UPDATE abonnement SET reduction_pourcentage = 5 WHERE type_abonnement = 'Bronze'");
        $this->addSql("UPDATE abonnement SET reduction_pourcentage = 10 WHERE type_abonnement = 'Silver'");
        $this->addSql("UPDATE abonnement SET reduction_pourcentage = 15 WHERE type_abonnement = 'Gold'");
        $this->addSql("UPDATE abonnement SET reduction_pourcentage = 20 WHERE type_abonnement = 'Platinum'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE credit DROP FOREIGN KEY credit_ibfk_3');
        $this->addSql('ALTER TABLE credit DROP COLUMN abonnement_id');
        $this->addSql('ALTER TABLE credit DROP COLUMN reduction_pourcentage');
        $this->addSql('ALTER TABLE credit DROP COLUMN updated_at');
        $this->addSql('ALTER TABLE credit MODIFY COLUMN date_debut DATE NOT NULL');
        $this->addSql('ALTER TABLE credit MODIFY COLUMN date_fin DATE NOT NULL');

        $this->addSql('ALTER TABLE abonnement DROP COLUMN reduction_pourcentage');
    }
}
