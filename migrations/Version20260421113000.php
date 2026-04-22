<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260421113000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add email and role snapshot fields to login_historique and backfill existing rows';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE login_historique ADD COLUMN IF NOT EXISTS email VARCHAR(150) DEFAULT NULL');
        $this->addSql('ALTER TABLE login_historique ADD COLUMN IF NOT EXISTS role VARCHAR(50) DEFAULT NULL');
        $this->addSql('UPDATE login_historique lh LEFT JOIN users u ON lh.user_id = u.id_user SET lh.email = COALESCE(lh.email, u.email), lh.role = COALESCE(lh.role, u.role) WHERE lh.user_id IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE login_historique DROP COLUMN role');
        $this->addSql('ALTER TABLE login_historique DROP COLUMN email');
    }
}
