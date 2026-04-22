<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add is_custom field to abonnement table for custom plan support
 */
final class Version20260421100002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add is_custom field to abonnement table to support custom subscription plans';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE abonnement ADD is_custom TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE abonnement DROP COLUMN is_custom');
    }
}
