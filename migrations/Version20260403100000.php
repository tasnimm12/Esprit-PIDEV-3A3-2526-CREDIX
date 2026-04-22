<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration: Convert subscription from ManyToOne to ManyToMany
 */
final class Version20260403100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Convert subscription from ManyToOne to ManyToMany relationship';
    }

    public function up(Schema $schema): void
    {
        // Create the join table for ManyToMany relationship - only if table doesn't exist
        if (!$schema->hasTable('user_abonnement')) {
            $this->addSql('CREATE TABLE user_abonnement (id_user INT NOT NULL, id_abonnement INT NOT NULL, PRIMARY KEY (id_user, id_abonnement), CONSTRAINT FK_USER_ID FOREIGN KEY (id_user) REFERENCES users (id_user) ON DELETE CASCADE, CONSTRAINT FK_ABONNEMENT_ID FOREIGN KEY (id_abonnement) REFERENCES abonnement (id_abonnement) ON DELETE CASCADE)');

            // Copy existing data from users.id_abonnement to the new join table
            $this->addSql('INSERT INTO user_abonnement (id_user, id_abonnement) SELECT id_user, id_abonnement FROM users WHERE id_abonnement IS NOT NULL');

            // Drop the old relationship if columns exist
            $usersTable = $schema->getTable('users');
            if ($usersTable->hasIndex('IDX_USERS_ABONNEMENT')) {
                $this->addSql('DROP INDEX IDX_USERS_ABONNEMENT ON users');
            }
            if ($usersTable->hasForeignKey('FK_ABONNEMENT')) {
                $this->addSql('ALTER TABLE users DROP FOREIGN KEY FK_ABONNEMENT');
            }
            if ($usersTable->hasColumn('id_abonnement')) {
                $this->addSql('ALTER TABLE users DROP COLUMN id_abonnement');
            }
        }
    }

    public function down(Schema $schema): void
    {
        // Add the column back
        $this->addSql('ALTER TABLE users ADD id_abonnement INT DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD CONSTRAINT FK_ABONNEMENT FOREIGN KEY (id_abonnement) REFERENCES abonnement (id_abonnement) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_USERS_ABONNEMENT ON users (id_abonnement)');

        // Restore data from join table (take the first subscription for each user)
        $this->addSql('UPDATE users u SET id_abonnement = (SELECT id_abonnement FROM user_abonnement WHERE id_user = u.id_user LIMIT 1)');

        // Drop the join table
        $this->addSql('DROP TABLE user_abonnement');
    }
}
