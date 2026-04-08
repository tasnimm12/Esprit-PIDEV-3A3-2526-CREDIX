<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260403090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add id_abonnement foreign key to users table';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $usersTable = $schema->getTable('users');
        if (!$usersTable->hasColumn('id_abonnement')) {
            $this->addSql('ALTER TABLE users ADD id_abonnement INT DEFAULT NULL');
        }
        
        if (!$usersTable->hasForeignKey('FK_ABONNEMENT')) {
            $this->addSql('ALTER TABLE users ADD CONSTRAINT FK_ABONNEMENT FOREIGN KEY (id_abonnement) REFERENCES abonnement (id_abonnement) ON DELETE SET NULL');
        }
        
        if (!$usersTable->hasIndex('IDX_USERS_ABONNEMENT')) {
            $this->addSql('CREATE INDEX IDX_USERS_ABONNEMENT ON users (id_abonnement)');
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX IDX_USERS_ABONNEMENT ON users');
        $this->addSql('ALTER TABLE users DROP FOREIGN KEY FK_ABONNEMENT');
        $this->addSql('ALTER TABLE users DROP COLUMN id_abonnement');
    }
}
