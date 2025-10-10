<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251009202727 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE niveau ADD ordre INT NOT NULL');
        // Réaffecte un ordre unique à chaque niveau (par id croissant)
        $this->addSql('
        SET @rownum := 0;
    ');
        $this->addSql('
        UPDATE niveau
        JOIN (
            SELECT id, (@rownum := @rownum + 1) AS new_ordre
            FROM niveau
            ORDER BY id
        ) AS ordered ON niveau.id = ordered.id
        SET niveau.ordre = ordered.new_ordre;
    ');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_4BDFF36B737992C9 ON niveau (ordre)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE niveau DROP ordre');
        $this->addSql('DROP INDEX UNIQ_4BDFF36B737992C9 ON niveau');
    }
}
