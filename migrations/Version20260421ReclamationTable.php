<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260421ReclamationTable extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create reclamation table for user complaints and claims';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE reclamation (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            abonnement_id INT,
            subject VARCHAR(100) NOT NULL,
            description LONGTEXT NOT NULL,
            category VARCHAR(50) DEFAULT \'general\' NOT NULL,
            status VARCHAR(20) DEFAULT \'pending\' NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
            resolved_at DATETIME,
            admin_response LONGTEXT,
            PRIMARY KEY(id),
            FOREIGN KEY (user_id) REFERENCES users(id_user) ON DELETE CASCADE,
            FOREIGN KEY (abonnement_id) REFERENCES abonnement(id_abonnement) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS reclamation');
    }
}
