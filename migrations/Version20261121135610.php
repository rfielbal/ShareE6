<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261121135610 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les fichiers, les catégories, les sous-catégories et leurs relations.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE categorie (id INT AUTO_INCREMENT NOT NULL, libelle VARCHAR(50) NOT NULL, date_envoi DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE fichier (id INT AUTO_INCREMENT NOT NULL, nom_original VARCHAR(255) NOT NULL, nom_serveur VARCHAR(255) NOT NULL, date_envoi DATETIME NOT NULL, extension VARCHAR(5) NOT NULL, taille INT NOT NULL, user_id INT NOT NULL, INDEX IDX_9B76551FA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE fichier_scategorie (fichier_id INT NOT NULL, scategorie_id INT NOT NULL, INDEX IDX_A1AB0BF6F915CFE (fichier_id), INDEX IDX_A1AB0BF6CF6A778A (scategorie_id), PRIMARY KEY (fichier_id, scategorie_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE scategorie (id INT AUTO_INCREMENT NOT NULL, libelle VARCHAR(50) NOT NULL, numero INT NOT NULL, categorie_id INT NOT NULL, UNIQUE INDEX UNIQ_SCATEGORIE_NUMERO (categorie_id, numero), INDEX IDX_51809477BCF5E72D (categorie_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE fichier ADD CONSTRAINT FK_9B76551FA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE fichier_scategorie ADD CONSTRAINT FK_A1AB0BF6F915CFE FOREIGN KEY (fichier_id) REFERENCES fichier (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE fichier_scategorie ADD CONSTRAINT FK_A1AB0BF6CF6A778A FOREIGN KEY (scategorie_id) REFERENCES scategorie (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE scategorie ADD CONSTRAINT FK_51809477BCF5E72D FOREIGN KEY (categorie_id) REFERENCES categorie (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE fichier DROP FOREIGN KEY FK_9B76551FA76ED395');
        $this->addSql('ALTER TABLE fichier_scategorie DROP FOREIGN KEY FK_A1AB0BF6F915CFE');
        $this->addSql('ALTER TABLE fichier_scategorie DROP FOREIGN KEY FK_A1AB0BF6CF6A778A');
        $this->addSql('ALTER TABLE scategorie DROP FOREIGN KEY FK_51809477BCF5E72D');
        $this->addSql('DROP TABLE categorie');
        $this->addSql('DROP TABLE fichier');
        $this->addSql('DROP TABLE fichier_scategorie');
        $this->addSql('DROP TABLE scategorie');
    }
}
