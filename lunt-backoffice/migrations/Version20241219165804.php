<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241219165804 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE UNIQUE INDEX UNIQ_80F1ADD1EA750E8 ON univerique (label)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_80F1ADD15E237E06 ON univerique (name)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_80F1ADD1EA750E8 ON univerique');
        $this->addSql('DROP INDEX UNIQ_80F1ADD15E237E06 ON univerique');
    }
}
