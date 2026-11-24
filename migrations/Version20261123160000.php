<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261123160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Relations réflexives DemandeAmi et AccepterDemande de User.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE user_demande (user_id INT NOT NULL, demander_id INT NOT NULL, INDEX IDX_DEMANDE_SOURCE (user_id), INDEX IDX_DEMANDE_CIBLE (demander_id), PRIMARY KEY(user_id, demander_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user_accepter (user_id INT NOT NULL, accepter_id INT NOT NULL, INDEX IDX_ACCEPTER_SOURCE (user_id), INDEX IDX_ACCEPTER_CIBLE (accepter_id), PRIMARY KEY(user_id, accepter_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE user_demande ADD CONSTRAINT FK_DEMANDE_SOURCE FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE, ADD CONSTRAINT FK_DEMANDE_CIBLE FOREIGN KEY (demander_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_accepter ADD CONSTRAINT FK_ACCEPTER_SOURCE FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE, ADD CONSTRAINT FK_ACCEPTER_CIBLE FOREIGN KEY (accepter_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_demande');
        $this->addSql('DROP TABLE user_accepter');
    }
}
