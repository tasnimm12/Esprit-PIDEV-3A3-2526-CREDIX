<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260418140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add latitude, longitude, and location_address columns to sinistre table for Maps API integration';
    }

    public function up(Schema $schema): void
    {
        // Using raw SQL to add columns directly
        $this->addSql('ALTER TABLE sinistre ADD COLUMN latitude DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE sinistre ADD COLUMN longitude DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE sinistre ADD COLUMN location_address VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // Remove columns
        $this->addSql('ALTER TABLE sinistre DROP COLUMN IF EXISTS latitude');
        $this->addSql('ALTER TABLE sinistre DROP COLUMN IF EXISTS longitude');
        $this->addSql('ALTER TABLE sinistre DROP COLUMN IF EXISTS location_address');
    }
}
