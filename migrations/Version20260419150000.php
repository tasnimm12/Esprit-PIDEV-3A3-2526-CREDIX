<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Insurance Claim Analyses Table
 */
final class Version20260419150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create claim_analyses table for insurance claim analysis storage and audit trail';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE claim_analyses (
            id INT AUTO_INCREMENT NOT NULL,
            insurance_type VARCHAR(50) NOT NULL,
            damage_percentage INT NOT NULL DEFAULT 0,
            estimated_cost INT NOT NULL DEFAULT 0,
            recommended_severity VARCHAR(50) NOT NULL,
            confidence_score INT NOT NULL DEFAULT 0,
            consistency_check TINYINT(1) NOT NULL DEFAULT 1,
            reasoning LONGTEXT NOT NULL,
            admin_review_required TINYINT(1) NOT NULL DEFAULT 1,
            admin_review_completed TINYINT(1) NOT NULL DEFAULT 0,
            admin_decision VARCHAR(255),
            analysis_timestamp DATETIME NOT NULL,
            admin_review_timestamp DATETIME,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY(id),
            INDEX insurance_type_idx (insurance_type),
            INDEX admin_review_idx (admin_review_completed),
            INDEX created_at_idx (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS claim_analyses');
    }
}
