<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260403083000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add SMS verification fields to users table';
    }

    public function up(Schema $schema): void
    {
        // Check if users table exists and add columns if they don't
        $table = $schema->getTable('users');
        
        if (!$table->hasColumn('verification_code')) {
            $this->addSql('ALTER TABLE users ADD verification_code VARCHAR(6) DEFAULT NULL');
        }
        if (!$table->hasColumn('phone_verified')) {
            $this->addSql('ALTER TABLE users ADD phone_verified TINYINT(1) NOT NULL DEFAULT 0');
        }
        if (!$table->hasColumn('verification_code_expiry')) {
            $this->addSql('ALTER TABLE users ADD verification_code_expiry DATETIME DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('users');
        
        if ($table->hasColumn('verification_code')) {
            $this->addSql('ALTER TABLE users DROP verification_code');
        }
        if ($table->hasColumn('phone_verified')) {
            $this->addSql('ALTER TABLE users DROP phone_verified');
        }
        if ($table->hasColumn('verification_code_expiry')) {
            $this->addSql('ALTER TABLE users DROP verification_code_expiry');
        }
    }
}
