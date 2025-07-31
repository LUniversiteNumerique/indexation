<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241219155745 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE UNIQUE INDEX UNIQ_20FD592C6C6E55B5 ON etablissement (nom)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_20FD592C9D6501A ON etablissement (abrege)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_20FD592C6C6E55B5 ON etablissement');
        $this->addSql('DROP INDEX UNIQ_20FD592C9D6501A ON etablissement');
    }
}
