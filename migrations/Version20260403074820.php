<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260403074820 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        // Check if table doesn't exist before creating
        if (!$schema->hasTable('damage_analysis')) {
            $this->addSql('CREATE TABLE damage_analysis (id INT AUTO_INCREMENT NOT NULL, severity VARCHAR(50) NOT NULL, damage_type LONGTEXT NOT NULL, affected_area_percentage INT NOT NULL, estimated_cost NUMERIC(10, 2) DEFAULT NULL, analysis_details LONGTEXT NOT NULL, analyzed_at DATETIME NOT NULL, ai_model VARCHAR(50) NOT NULL, preuve_id INT NOT NULL, sinistre_id INT NOT NULL, INDEX IDX_72B00D0E99D4B0DA (preuve_id), INDEX IDX_72B00D0E216966DF (sinistre_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
            $this->addSql('ALTER TABLE damage_analysis ADD CONSTRAINT FK_72B00D0E99D4B0DA FOREIGN KEY (preuve_id) REFERENCES sinistre_preuve (id) ON DELETE CASCADE');
            $this->addSql('ALTER TABLE damage_analysis ADD CONSTRAINT FK_72B00D0E216966DF FOREIGN KEY (sinistre_id) REFERENCES sinistre (id) ON DELETE CASCADE');
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE support_conversation (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL COMMENT \'client\', admin_id INT NOT NULL COMMENT \'admin chosen by client\', created_at DATETIME DEFAULT \'current_timestamp()\' NOT NULL, INDEX fk_support_conv_user (user_id), INDEX fk_support_conv_admin (admin_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE support_message (id INT AUTO_INCREMENT NOT NULL, conversation_id INT NOT NULL, sender_id INT NOT NULL, is_from_admin TINYINT DEFAULT 0 NOT NULL, content TEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, sent_at DATETIME DEFAULT \'current_timestamp()\' NOT NULL, INDEX fk_support_msg_conv (conversation_id), INDEX fk_support_msg_sender (sender_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE user_abonnement (id_user_abonnement INT AUTO_INCREMENT NOT NULL, id_user INT NOT NULL, id_abonnement INT NOT NULL, date_debut DATE NOT NULL, date_fin DATE NOT NULL, statut ENUM(\'actif\', \'expire\', \'annule\') CHARACTER SET utf8mb4 DEFAULT \'\'\'actif\'\'\' COLLATE `utf8mb4_unicode_ci`, renouvellement_auto TINYINT DEFAULT 0, date_souscription DATETIME DEFAULT \'current_timestamp()\', montant_custom NUMERIC(10, 2) DEFAULT \'NULL\' COMMENT \'Calculated price when custom plan\', custom_duree ENUM(\'mensuel\', \'annuel\') CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_unicode_ci` COMMENT \'Duration when custom\', custom_options VARCHAR(500) CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_unicode_ci` COMMENT \'e.g. accounts=3;support=Priority;features=Analytics\', INDEX fk_user_abon_user (id_user), INDEX fk_user_abon_plan (id_abonnement), PRIMARY KEY (id_user_abonnement)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE user_projet (user_id INT NOT NULL, projet_id INT NOT NULL, INDEX fk_userprojet_projet (projet_id), INDEX IDX_35478794A76ED395 (user_id), PRIMARY KEY (user_id, projet_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE support_conversation ADD CONSTRAINT `fk_support_conv_admin` FOREIGN KEY (admin_id) REFERENCES users (id_user) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE support_conversation ADD CONSTRAINT `fk_support_conv_user` FOREIGN KEY (user_id) REFERENCES users (id_user) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE support_message ADD CONSTRAINT `fk_support_msg_conv` FOREIGN KEY (conversation_id) REFERENCES support_conversation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE support_message ADD CONSTRAINT `fk_support_msg_sender` FOREIGN KEY (sender_id) REFERENCES users (id_user) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_abonnement ADD CONSTRAINT `fk_user_abon_plan` FOREIGN KEY (id_abonnement) REFERENCES abonnement (id_abonnement) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_abonnement ADD CONSTRAINT `fk_user_abon_user` FOREIGN KEY (id_user) REFERENCES users (id_user) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_projet ADD CONSTRAINT `fk_userprojet_projet` FOREIGN KEY (projet_id) REFERENCES projet (idprojet) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_projet ADD CONSTRAINT `fk_userprojet_user` FOREIGN KEY (user_id) REFERENCES users (id_user) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE damage_analysis DROP FOREIGN KEY FK_72B00D0E99D4B0DA');
        $this->addSql('ALTER TABLE damage_analysis DROP FOREIGN KEY FK_72B00D0E216966DF');
        $this->addSql('DROP TABLE damage_analysis');
        $this->addSql('ALTER TABLE abonnement CHANGE type_abonnement type_abonnement ENUM(\'Bronze\', \'Silver\', \'Gold\', \'Platinum\', \'Custom\') NOT NULL, CHANGE duree duree ENUM(\'mensuel\', \'annuel\') NOT NULL, CHANGE description description TEXT DEFAULT NULL, CHANGE avantages avantages TEXT DEFAULT NULL, CHANGE actif actif TINYINT DEFAULT 1');
        $this->addSql('ALTER TABLE assurance DROP FOREIGN KEY FK_386829AEFB88E14F');
        $this->addSql('ALTER TABLE assurance CHANGE type_assurance type_assurance ENUM(\'VIE\', \'SANTE\', \'AUTO\', \'HABITATION\', \'RESPONSABILITE_CIVILE\', \'SCOLAIRE\', \'VOYAGE\', \'PROFESSIONNELLE\') NOT NULL, CHANGE compagnie compagnie VARCHAR(150) NOT NULL, CHANGE numero_police numero_police VARCHAR(100) DEFAULT \'NULL\', CHANGE montant_couverture montant_couverture NUMERIC(15, 2) DEFAULT \'NULL\', CHANGE franchise franchise NUMERIC(15, 2) DEFAULT \'NULL\', CHANGE prime_annuelle prime_annuelle NUMERIC(15, 2) DEFAULT \'NULL\', CHANGE prime_mensuelle prime_mensuelle NUMERIC(15, 2) DEFAULT \'NULL\', CHANGE date_debut date_debut DATE DEFAULT \'NULL\', CHANGE date_echeance date_echeance DATE DEFAULT \'NULL\', CHANGE mode_paiement mode_paiement ENUM(\'MENSUEL\', \'TRIMESTRIEL\', \'SEMESTRIEL\', \'ANNUEL\') DEFAULT \'NULL\', CHANGE statut statut ENUM(\'ACTIF\', \'EXPIRE\', \'RESILIE\', \'SUSPENDU\') DEFAULT \'\'\'ACTIF\'\'\', CHANGE renouvellement_auto renouvellement_auto TINYINT DEFAULT NULL, CHANGE garanties_incluses garanties_incluses LONGTEXT DEFAULT NULL COLLATE `utf8mb4_bin`');
        $this->addSql('ALTER TABLE assurance ADD CONSTRAINT `fk_assurance_user` FOREIGN KEY (utilisateur_id) REFERENCES users (id_user) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('CREATE UNIQUE INDEX numero_police ON assurance (numero_police)');
        $this->addSql('ALTER TABLE assurance RENAME INDEX idx_386829aefb88e14f TO fk_assurance_user');
        $this->addSql('ALTER TABLE client_alert CHANGE user_id user_id INT NOT NULL, CHANGE message message TEXT NOT NULL, CHANGE created_at created_at DATETIME DEFAULT \'current_timestamp()\' NOT NULL, CHANGE is_read is_read TINYINT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE client_alert ADD CONSTRAINT `fk_alert_user` FOREIGN KEY (user_id) REFERENCES users (id_user) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX fk_alert_user ON client_alert (user_id)');
        $this->addSql('ALTER TABLE compte_bancaire CHANGE user_id user_id INT NOT NULL, CHANGE numero_compte numero_compte VARCHAR(50) NOT NULL, CHANGE titulaire titulaire VARCHAR(100) NOT NULL, CHANGE email email VARCHAR(100) DEFAULT \'NULL\', CHANGE telephone telephone VARCHAR(20) DEFAULT \'NULL\', CHANGE solde solde NUMERIC(15, 2) DEFAULT \'0.00\', CHANGE date_creation date_creation DATE NOT NULL, CHANGE actif actif TINYINT DEFAULT 1');
        $this->addSql('ALTER TABLE compte_bancaire ADD CONSTRAINT `fk_compte_user` FOREIGN KEY (user_id) REFERENCES users (id_user) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('CREATE INDEX fk_compte_user ON compte_bancaire (user_id)');
        $this->addSql('CREATE UNIQUE INDEX numero_compte ON compte_bancaire (numero_compte)');
        $this->addSql('ALTER TABLE contrat_assurance DROP FOREIGN KEY FK_B36F5E5EB288C3E3');
        $this->addSql('ALTER TABLE contrat_assurance DROP FOREIGN KEY FK_B36F5E5EFB88E14F');
        $this->addSql('ALTER TABLE contrat_assurance CHANGE numero_contrat numero_contrat VARCHAR(100) NOT NULL, CHANGE date_signature date_signature DATE DEFAULT \'NULL\', CHANGE date_fin_contrat date_fin_contrat DATE DEFAULT \'NULL\', CHANGE conditions_particulieres conditions_particulieres TEXT DEFAULT NULL, CHANGE exclusions exclusions TEXT DEFAULT NULL, CHANGE plafond_annuel plafond_annuel NUMERIC(15, 2) DEFAULT \'NULL\', CHANGE taux_remboursement taux_remboursement NUMERIC(5, 2) DEFAULT \'NULL\', CHANGE clause_beneficiaire clause_beneficiaire TEXT DEFAULT NULL, CHANGE document_contrat document_contrat VARCHAR(255) DEFAULT \'NULL\', CHANGE amendements amendements LONGTEXT DEFAULT NULL COLLATE `utf8mb4_bin`, CHANGE conseiller_attribue conseiller_attribue VARCHAR(150) DEFAULT \'NULL\', CHANGE contacts contacts TEXT DEFAULT NULL, CHANGE statut statut ENUM(\'ACTIF\', \'EXPIRE\', \'RESILIE\', \'EN_ATTENTE\', \'ACCEPTED\') DEFAULT \'NULL\', CHANGE assurance_id assurance_id INT NOT NULL');
        $this->addSql('ALTER TABLE contrat_assurance ADD CONSTRAINT `fk_contrat_assurance` FOREIGN KEY (assurance_id) REFERENCES assurance (id) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE contrat_assurance ADD CONSTRAINT `fk_contrat_user` FOREIGN KEY (utilisateur_id) REFERENCES users (id_user) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('CREATE UNIQUE INDEX numero_contrat ON contrat_assurance (numero_contrat)');
        $this->addSql('ALTER TABLE contrat_assurance RENAME INDEX idx_b36f5e5eb288c3e3 TO fk_contrat_assurance');
        $this->addSql('ALTER TABLE contrat_assurance RENAME INDEX idx_b36f5e5efb88e14f TO fk_contrat_user');
        $this->addSql('ALTER TABLE credit CHANGE user_id user_id INT NOT NULL, CHANGE compte_id compte_id BIGINT NOT NULL, CHANGE type_credit type_credit ENUM(\'3M\', \'6M\', \'12M\', \'24M\', \'36M\') NOT NULL COMMENT \'Duration in months\', CHANGE taux_interet taux_interet NUMERIC(5, 2) NOT NULL COMMENT \'Interest rate percentage\', CHANGE montant_total montant_total NUMERIC(15, 2) NOT NULL COMMENT \'Total amount to pay (principal + interest)\', CHANGE montant_restant montant_restant NUMERIC(15, 2) NOT NULL COMMENT \'Remaining amount to pay\', CHANGE date_debut date_debut DATE DEFAULT \'NULL\', CHANGE date_fin date_fin DATE DEFAULT \'NULL\', CHANGE statut_credit statut_credit ENUM(\'EN_ATTENTE\', \'ACCEPTE\', \'REFUSE\', \'EN_COURS\', \'TERMINE\', \'EN_RETARD\') DEFAULT \'\'\'EN_ATTENTE\'\'\' NOT NULL, CHANGE motif_refus motif_refus VARCHAR(255) DEFAULT \'NULL\', CHANGE mensualite mensualite NUMERIC(15, 2) DEFAULT \'NULL\' COMMENT \'Monthly payment amount\', CHANGE created_at created_at DATETIME DEFAULT \'current_timestamp()\'');
        $this->addSql('ALTER TABLE credit ADD CONSTRAINT `fk_credit_compte` FOREIGN KEY (compte_id) REFERENCES compte_bancaire (id) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE credit ADD CONSTRAINT `fk_credit_user` FOREIGN KEY (user_id) REFERENCES users (id_user) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('CREATE INDEX idx_compte_id ON credit (compte_id)');
        $this->addSql('CREATE INDEX idx_statut ON credit (statut_credit)');
        $this->addSql('CREATE INDEX idx_user_id ON credit (user_id)');
        $this->addSql('ALTER TABLE depense CHANGE description description VARCHAR(255) DEFAULT \'NULL\', CHANGE categorie categorie VARCHAR(100) DEFAULT \'NULL\', CHANGE mode_paiement mode_paiement VARCHAR(50) DEFAULT \'NULL\', CHANGE compte_id compte_id BIGINT NOT NULL');
        $this->addSql('ALTER TABLE depense ADD CONSTRAINT `fk_depense_compte` FOREIGN KEY (compte_id) REFERENCES compte_bancaire (id) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('CREATE INDEX fk_depense_compte ON depense (compte_id)');
        $this->addSql('ALTER TABLE investissement CHANGE montantinvesti montantinvesti INT DEFAULT NULL, CHANGE dateinves dateinves DATE DEFAULT \'NULL\', CHANGE modepaiement modepaiement VARCHAR(100) DEFAULT \'NULL\', CHANGE statut_investissement statut_investissement VARCHAR(100) DEFAULT \'NULL\', CHANGE idprojet idprojet INT NOT NULL');
        $this->addSql('ALTER TABLE investissement ADD CONSTRAINT `fk_investissement_projet` FOREIGN KEY (idprojet) REFERENCES projet (idprojet) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE investissement ADD CONSTRAINT `fk_investissement_user` FOREIGN KEY (user_id) REFERENCES users (id_user) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX fk_investissement_user ON investissement (user_id)');
        $this->addSql('CREATE INDEX fk_investissement_projet ON investissement (idprojet)');
        $this->addSql('ALTER TABLE login_historique CHANGE user_id user_id INT NOT NULL, CHANGE login_at login_at DATETIME DEFAULT \'current_timestamp()\' NOT NULL');
        $this->addSql('ALTER TABLE login_historique ADD CONSTRAINT `fk_login_historique_user` FOREIGN KEY (user_id) REFERENCES users (id_user) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX fk_login_historique_user ON login_historique (user_id)');
        $this->addSql('ALTER TABLE projet CHANGE nomprojet nomprojet VARCHAR(100) DEFAULT \'NULL\', CHANGE description description VARCHAR(100) DEFAULT \'NULL\', CHANGE secteur secteur VARCHAR(50) DEFAULT \'NULL\', CHANGE montant_objectif montant_objectif INT DEFAULT NULL, CHANGE date_debut date_debut DATE DEFAULT \'NULL\', CHANGE date_fin date_fin DATE DEFAULT \'NULL\', CHANGE statut_projet statut_projet VARCHAR(100) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE remboursement CHANGE credit_id credit_id INT DEFAULT NULL COMMENT \'For credit repayments, NULL for refunds\', CHANGE user_id user_id INT NOT NULL, CHANGE compte_id compte_id BIGINT NOT NULL, CHANGE type_remboursement type_remboursement ENUM(\'CREDIT_PAYMENT\', \'ABONNEMENT_REFUND\', \'ASSURANCE_REFUND\') NOT NULL, CHANGE statut statut ENUM(\'EN_ATTENTE\', \'COMPLETE\', \'ECHOUE\') DEFAULT \'\'\'EN_ATTENTE\'\'\' NOT NULL, CHANGE description description TEXT DEFAULT NULL, CHANGE reference_id reference_id INT DEFAULT NULL COMMENT \'Reference to cancelled subscription/insurance\', CHANGE created_at created_at DATETIME DEFAULT \'current_timestamp()\'');
        $this->addSql('ALTER TABLE remboursement ADD CONSTRAINT `fk_remboursement_compte` FOREIGN KEY (compte_id) REFERENCES compte_bancaire (id) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE remboursement ADD CONSTRAINT `fk_remboursement_credit` FOREIGN KEY (credit_id) REFERENCES credit (id_credit) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE remboursement ADD CONSTRAINT `fk_remboursement_user` FOREIGN KEY (user_id) REFERENCES users (id_user) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('CREATE INDEX idx_user_id ON remboursement (user_id)');
        $this->addSql('CREATE INDEX idx_compte_id ON remboursement (compte_id)');
        $this->addSql('CREATE INDEX idx_type ON remboursement (type_remboursement)');
        $this->addSql('CREATE INDEX idx_credit_id ON remboursement (credit_id)');
        $this->addSql('ALTER TABLE sinistre DROP FOREIGN KEY FK_F5AC7A671823061F');
        $this->addSql('ALTER TABLE sinistre DROP FOREIGN KEY FK_F5AC7A67FB88E14F');
        $this->addSql('ALTER TABLE sinistre CHANGE description description TEXT NOT NULL, CHANGE date_sinistre date_sinistre DATE DEFAULT \'NULL\' COMMENT \'Date of the incident\', CHANGE date_reclamation date_reclamation DATETIME DEFAULT \'current_timestamp()\' NOT NULL, CHANGE statut statut VARCHAR(32) DEFAULT \'\'\'EN_ATTENTE\'\'\' NOT NULL COMMENT \'EN_ATTENTE, EN_COURS, TRAITE, REFUSE\', CHANGE admin_reponse admin_reponse TEXT DEFAULT NULL COMMENT \'Admin response to the claim\', CHANGE date_reponse date_reponse DATETIME DEFAULT \'NULL\', CHANGE created_at created_at DATETIME DEFAULT \'current_timestamp()\', CHANGE contrat_id contrat_id INT NOT NULL COMMENT \'FK to contrat_assurance\', CHANGE utilisateur_id utilisateur_id INT NOT NULL COMMENT \'Claimant user\'');
        $this->addSql('ALTER TABLE sinistre ADD CONSTRAINT `fk_sinistre_contrat` FOREIGN KEY (contrat_id) REFERENCES contrat_assurance (id) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE sinistre ADD CONSTRAINT `fk_sinistre_user` FOREIGN KEY (utilisateur_id) REFERENCES users (id_user) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('CREATE INDEX idx_statut ON sinistre (statut)');
        $this->addSql('ALTER TABLE sinistre RENAME INDEX idx_f5ac7a671823061f TO idx_contrat_id');
        $this->addSql('ALTER TABLE sinistre RENAME INDEX idx_f5ac7a67fb88e14f TO idx_utilisateur_id');
        $this->addSql('ALTER TABLE sinistre_preuve DROP FOREIGN KEY FK_3A79D6AD216966DF');
        $this->addSql('ALTER TABLE sinistre_preuve CHANGE fichier fichier MEDIUMBLOB NOT NULL COMMENT \'Image bytes\', CHANGE type_fichier type_fichier VARCHAR(100) DEFAULT \'\'\'image/jpeg\'\'\' NOT NULL COMMENT \'e.g. image/jpeg, image/png\', CHANGE nom_fichier nom_fichier VARCHAR(255) DEFAULT \'NULL\' COMMENT \'Original filename\', CHANGE damage_type damage_type VARCHAR(64) DEFAULT \'NULL\' COMMENT \'AI-detected damage category e.g. car_damage, fire_damage, no_damage\', CHANGE created_at created_at DATETIME DEFAULT \'current_timestamp()\'');
        $this->addSql('ALTER TABLE sinistre_preuve ADD CONSTRAINT `fk_preuve_sinistre` FOREIGN KEY (sinistre_id) REFERENCES sinistre (id) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE sinistre_preuve RENAME INDEX idx_3a79d6ad216966df TO idx_sinistre_id');
        $this->addSql('ALTER TABLE users CHANGE nom nom VARCHAR(100) NOT NULL, CHANGE prenom prenom VARCHAR(100) NOT NULL, CHANGE telephone telephone VARCHAR(20) DEFAULT \'NULL\', CHANGE date_naissance date_naissance DATE DEFAULT \'NULL\', CHANGE role role ENUM(\'admin\', \'client\', \'organisateur\') NOT NULL, CHANGE statut_compte statut_compte ENUM(\'actif\', \'desactive\') DEFAULT \'NULL\', CHANGE points_fidelite points_fidelite INT DEFAULT 0 NOT NULL COMMENT \'Fidelity points: +10 per expense; 100 = 10% off next purchase\'');
        $this->addSql('CREATE UNIQUE INDEX email ON users (email)');
    }
}
