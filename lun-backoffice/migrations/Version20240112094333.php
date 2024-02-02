<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240112094333 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE auteur (id INT AUTO_INCREMENT NOT NULL, ecole_id INT DEFAULT NULL, nom VARCHAR(255) NOT NULL, prenom VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, roles LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:simple_array)\', INDEX IDX_55AB14077EF1B1E (ecole_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE discipline (id INT AUTO_INCREMENT NOT NULL, parent_id INT DEFAULT NULL, nom VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, INDEX IDX_75BEEE3F727ACA70 (parent_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE etablissement (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE groupe (id INT AUTO_INCREMENT NOT NULL, label VARCHAR(255) NOT NULL, rights JSON NOT NULL COMMENT \'(DC2Type:json)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE keyword (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, valide TINYINT(1) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE licence (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(255) NOT NULL, valeur LONGTEXT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE niveau (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE notice (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, droit_id INT DEFAULT NULL, specialite_id INT DEFAULT NULL, titre VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, label VARCHAR(255) DEFAULT NULL, dewey VARCHAR(255) DEFAULT NULL, vignette VARCHAR(255) DEFAULT NULL, objectif VARCHAR(255) DEFAULT NULL, prop_user VARCHAR(255) DEFAULT NULL, taille INT DEFAULT NULL, dure_exec INT DEFAULT NULL, dure_appr INT DEFAULT NULL, ress_lang JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', user_lang JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', cree_le DATETIME NOT NULL, modifie_le DATETIME DEFAULT NULL, publie_le DATETIME DEFAULT NULL, etat VARCHAR(10) DEFAULT NULL, export_oai TINYINT(1) DEFAULT NULL, contenu_libelle VARCHAR(255) NOT NULL, contenu_url VARCHAR(255) NOT NULL, INDEX IDX_480D45C2A76ED395 (user_id), INDEX IDX_480D45C25AA93370 (droit_id), INDEX IDX_480D45C22195E0F0 (specialite_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE notice_etablissement (notice_id INT NOT NULL, etablissement_id INT NOT NULL, INDEX IDX_FE8F1E407D540AB (notice_id), INDEX IDX_FE8F1E40FF631228 (etablissement_id), PRIMARY KEY(notice_id, etablissement_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE notice_auteur (notice_id INT NOT NULL, auteur_id INT NOT NULL, INDEX IDX_5E4EF7DD7D540AB (notice_id), INDEX IDX_5E4EF7DD60BB6FE6 (auteur_id), PRIMARY KEY(notice_id, auteur_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE notice_tdocument (notice_id INT NOT NULL, tdocument_id INT NOT NULL, INDEX IDX_BF3FC46E7D540AB (notice_id), INDEX IDX_BF3FC46EF421B53F (tdocument_id), PRIMARY KEY(notice_id, tdocument_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE notice_tpedagogie (notice_id INT NOT NULL, tpedagogie_id INT NOT NULL, INDEX IDX_ADD3D7217D540AB (notice_id), INDEX IDX_ADD3D721501F7E90 (tpedagogie_id), PRIMARY KEY(notice_id, tpedagogie_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE notice_niveau (notice_id INT NOT NULL, niveau_id INT NOT NULL, INDEX IDX_10CBB5F67D540AB (notice_id), INDEX IDX_10CBB5F6B3E9C81 (niveau_id), PRIMARY KEY(notice_id, niveau_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE notice_keyword (notice_id INT NOT NULL, keyword_id INT NOT NULL, INDEX IDX_D4768A847D540AB (notice_id), INDEX IDX_D4768A84115D4552 (keyword_id), PRIMARY KEY(notice_id, keyword_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE notice_notice (notice_source INT NOT NULL, notice_target INT NOT NULL, INDEX IDX_1319035F49E39EE (notice_source), INDEX IDX_1319035F1D7B6961 (notice_target), PRIMARY KEY(notice_source, notice_target)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE tdocument (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE tpedagogie (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, group_id INT DEFAULT NULL, school_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), INDEX IDX_8D93D649FE54D947 (group_id), INDEX IDX_8D93D649C32A47EE (school_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user_discipline (user_id INT NOT NULL, discipline_id INT NOT NULL, INDEX IDX_D2D928D3A76ED395 (user_id), INDEX IDX_D2D928D3A5522701 (discipline_id), PRIMARY KEY(user_id, discipline_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', available_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE auteur ADD CONSTRAINT FK_55AB14077EF1B1E FOREIGN KEY (ecole_id) REFERENCES etablissement (id)');
        $this->addSql('ALTER TABLE discipline ADD CONSTRAINT FK_75BEEE3F727ACA70 FOREIGN KEY (parent_id) REFERENCES discipline (id)');
        $this->addSql('ALTER TABLE notice ADD CONSTRAINT FK_480D45C2A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE notice ADD CONSTRAINT FK_480D45C25AA93370 FOREIGN KEY (droit_id) REFERENCES licence (id)');
        $this->addSql('ALTER TABLE notice ADD CONSTRAINT FK_480D45C22195E0F0 FOREIGN KEY (specialite_id) REFERENCES discipline (id)');
        $this->addSql('ALTER TABLE notice_etablissement ADD CONSTRAINT FK_FE8F1E407D540AB FOREIGN KEY (notice_id) REFERENCES notice (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE notice_etablissement ADD CONSTRAINT FK_FE8F1E40FF631228 FOREIGN KEY (etablissement_id) REFERENCES etablissement (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE notice_auteur ADD CONSTRAINT FK_5E4EF7DD7D540AB FOREIGN KEY (notice_id) REFERENCES notice (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE notice_auteur ADD CONSTRAINT FK_5E4EF7DD60BB6FE6 FOREIGN KEY (auteur_id) REFERENCES auteur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE notice_tdocument ADD CONSTRAINT FK_BF3FC46E7D540AB FOREIGN KEY (notice_id) REFERENCES notice (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE notice_tdocument ADD CONSTRAINT FK_BF3FC46EF421B53F FOREIGN KEY (tdocument_id) REFERENCES tdocument (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE notice_tpedagogie ADD CONSTRAINT FK_ADD3D7217D540AB FOREIGN KEY (notice_id) REFERENCES notice (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE notice_tpedagogie ADD CONSTRAINT FK_ADD3D721501F7E90 FOREIGN KEY (tpedagogie_id) REFERENCES tpedagogie (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE notice_niveau ADD CONSTRAINT FK_10CBB5F67D540AB FOREIGN KEY (notice_id) REFERENCES notice (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE notice_niveau ADD CONSTRAINT FK_10CBB5F6B3E9C81 FOREIGN KEY (niveau_id) REFERENCES niveau (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE notice_keyword ADD CONSTRAINT FK_D4768A847D540AB FOREIGN KEY (notice_id) REFERENCES notice (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE notice_keyword ADD CONSTRAINT FK_D4768A84115D4552 FOREIGN KEY (keyword_id) REFERENCES keyword (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE notice_notice ADD CONSTRAINT FK_1319035F49E39EE FOREIGN KEY (notice_source) REFERENCES notice (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE notice_notice ADD CONSTRAINT FK_1319035F1D7B6961 FOREIGN KEY (notice_target) REFERENCES notice (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE `user` ADD CONSTRAINT FK_8D93D649FE54D947 FOREIGN KEY (group_id) REFERENCES groupe (id)');
        $this->addSql('ALTER TABLE `user` ADD CONSTRAINT FK_8D93D649C32A47EE FOREIGN KEY (school_id) REFERENCES etablissement (id)');
        $this->addSql('ALTER TABLE user_discipline ADD CONSTRAINT FK_D2D928D3A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_discipline ADD CONSTRAINT FK_D2D928D3A5522701 FOREIGN KEY (discipline_id) REFERENCES discipline (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE auteur DROP FOREIGN KEY FK_55AB14077EF1B1E');
        $this->addSql('ALTER TABLE discipline DROP FOREIGN KEY FK_75BEEE3F727ACA70');
        $this->addSql('ALTER TABLE notice DROP FOREIGN KEY FK_480D45C2A76ED395');
        $this->addSql('ALTER TABLE notice DROP FOREIGN KEY FK_480D45C25AA93370');
        $this->addSql('ALTER TABLE notice DROP FOREIGN KEY FK_480D45C22195E0F0');
        $this->addSql('ALTER TABLE notice_etablissement DROP FOREIGN KEY FK_FE8F1E407D540AB');
        $this->addSql('ALTER TABLE notice_etablissement DROP FOREIGN KEY FK_FE8F1E40FF631228');
        $this->addSql('ALTER TABLE notice_auteur DROP FOREIGN KEY FK_5E4EF7DD7D540AB');
        $this->addSql('ALTER TABLE notice_auteur DROP FOREIGN KEY FK_5E4EF7DD60BB6FE6');
        $this->addSql('ALTER TABLE notice_tdocument DROP FOREIGN KEY FK_BF3FC46E7D540AB');
        $this->addSql('ALTER TABLE notice_tdocument DROP FOREIGN KEY FK_BF3FC46EF421B53F');
        $this->addSql('ALTER TABLE notice_tpedagogie DROP FOREIGN KEY FK_ADD3D7217D540AB');
        $this->addSql('ALTER TABLE notice_tpedagogie DROP FOREIGN KEY FK_ADD3D721501F7E90');
        $this->addSql('ALTER TABLE notice_niveau DROP FOREIGN KEY FK_10CBB5F67D540AB');
        $this->addSql('ALTER TABLE notice_niveau DROP FOREIGN KEY FK_10CBB5F6B3E9C81');
        $this->addSql('ALTER TABLE notice_keyword DROP FOREIGN KEY FK_D4768A847D540AB');
        $this->addSql('ALTER TABLE notice_keyword DROP FOREIGN KEY FK_D4768A84115D4552');
        $this->addSql('ALTER TABLE notice_notice DROP FOREIGN KEY FK_1319035F49E39EE');
        $this->addSql('ALTER TABLE notice_notice DROP FOREIGN KEY FK_1319035F1D7B6961');
        $this->addSql('ALTER TABLE `user` DROP FOREIGN KEY FK_8D93D649FE54D947');
        $this->addSql('ALTER TABLE `user` DROP FOREIGN KEY FK_8D93D649C32A47EE');
        $this->addSql('ALTER TABLE user_discipline DROP FOREIGN KEY FK_D2D928D3A76ED395');
        $this->addSql('ALTER TABLE user_discipline DROP FOREIGN KEY FK_D2D928D3A5522701');
        $this->addSql('DROP TABLE auteur');
        $this->addSql('DROP TABLE discipline');
        $this->addSql('DROP TABLE etablissement');
        $this->addSql('DROP TABLE groupe');
        $this->addSql('DROP TABLE keyword');
        $this->addSql('DROP TABLE licence');
        $this->addSql('DROP TABLE niveau');
        $this->addSql('DROP TABLE notice');
        $this->addSql('DROP TABLE notice_etablissement');
        $this->addSql('DROP TABLE notice_auteur');
        $this->addSql('DROP TABLE notice_tdocument');
        $this->addSql('DROP TABLE notice_tpedagogie');
        $this->addSql('DROP TABLE notice_niveau');
        $this->addSql('DROP TABLE notice_keyword');
        $this->addSql('DROP TABLE notice_notice');
        $this->addSql('DROP TABLE tdocument');
        $this->addSql('DROP TABLE tpedagogie');
        $this->addSql('DROP TABLE `user`');
        $this->addSql('DROP TABLE user_discipline');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
