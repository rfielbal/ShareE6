<?php

namespace App\Tests\Functional;

use App\Entity\Categorie;
use App\Entity\Fichier;
use App\Entity\Scategorie;
use App\Entity\User;
use App\Tests\Support\TestDatabase;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class FichierTest extends WebTestCase
{
    private KernelBrowser $client;
    private TestDatabase $database;
    private User $proprietaire;
    private string $dossier;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->database = new TestDatabase($em->getConnection(), static::$kernel->getEnvironment());
        $this->database->reset();
        $dossier = static::getContainer()->getParameter('file_directory');
        // Le nettoyage de fin de test doit rester dans le répertoire des dépôts de test.
        self::assertSame(static::getContainer()->getParameter('kernel.project_dir').'/var/test/uploads/fichiers', $dossier);
        $this->dossier = $dossier;
        (new Filesystem())->mkdir($this->dossier);

        $categorie = (new Categorie())->setLibelle('Informatique')->setDateEnvoi(new \DateTime());
        $em->persist($categorie);
        $em->flush();
        $classement = (new Scategorie())->setLibelle('Cours')->setNumero(1)->setCategorie($categorie);
        $em->persist($classement);
        $this->proprietaire = $this->creerMembre('lea');
        $this->client->loginUser($this->proprietaire);
    }

    protected function tearDown(): void
    {
        try {
            if (isset($this->dossier)) {
                (new Filesystem())->remove($this->dossier);
            }
            if (isset($this->database)) {
                $this->database->reset();
                $this->database->close();
            }
        } finally {
            parent::tearDown();
        }
    }

    #[TestDox('Le propriétaire dépose et télécharge un PDF de 2 Mo')]
    public function testLeProprietaireDeposeEtTelechargeUnPdfDeDeuxMo(): void
    {
        $this->deposerPdf(2_000_000);
        self::assertResponseRedirects('/profil');

        $fichier = $this->fichierDepose();
        self::assertSame(2_000_000, $fichier->getTaille());
        self::assertSame($this->proprietaire->getId(), $fichier->getUser()->getId());
        self::assertSame('Cours', $fichier->getScategories()->first()->getLibelle());

        $this->client->request('GET', '/fichiers/'.$fichier->getId().'/telecharger');
        self::assertResponseIsSuccessful();
        $reponse = $this->client->getResponse();
        self::assertInstanceOf(BinaryFileResponse::class, $reponse);
        self::assertFileEquals($this->dossier.'/cours.pdf', $reponse->getFile()->getPathname());
    }

    #[TestDox('Le dépôt refuse un fichier de 2 000 001 octets')]
    public function testLeDepotRefuseUnOctetDePlus(): void
    {
        $this->deposerPdf(2_000_001);
        self::assertSelectorTextContains('form', 'Le fichier dépasse la limite de 2 Mo.');
        self::assertSame(0, static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(Fichier::class)->count([]));
        self::assertSame([], glob($this->dossier.'/cours-*.pdf'));
    }

    #[TestDox('Un autre membre reçoit une réponse 403 au téléchargement')]
    public function testUnAutreMembreNePeutPasTelechargerLeFichier(): void
    {
        $this->deposerPdf(1_000);
        self::assertResponseRedirects('/profil');
        $fichier = $this->fichierDepose();
        $this->client->loginUser($this->creerMembre('nolan'));
        $this->client->request('GET', '/fichiers/'.$fichier->getId().'/telecharger');
        self::assertResponseStatusCodeSame(403);
    }

    #[TestDox('Le propriétaire remplace la sous-catégorie de son fichier')]
    public function testLeProprietaireReclasseSonFichier(): void
    {
        $this->deposerPdf(1_000);
        $fichier = $this->fichierDepose();
        $categorie = $fichier->getScategories()->first()->getCategorie();
        $exercices = (new Scategorie())->setLibelle('Exercices')->setNumero(2)->setCategorie($categorie);
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist($exercices);
        $em->flush();

        $page = $this->client->request('GET', '/fichiers/'.$fichier->getId().'/classement');
        $formulaire = $page->selectButton('Enregistrer le classement')->form();
        $cases = $formulaire['classement_fichier[scategories]'];
        $cases[0]->untick(); // Cours, n° 1
        $cases[1]->tick();   // Exercices, n° 2
        $this->client->submit($formulaire);
        self::assertResponseRedirects('/fichiers');

        $this->client->followRedirect();
        self::assertSelectorCount(1, '.classification-tag');
        self::assertSelectorTextContains('.classification-tag', 'Informatique / Exercices');
    }

    #[TestDox('Après confirmation, le fichier est supprimé de la base et du disque')]
    public function testLeProprietaireSupprimeSonFichier(): void
    {
        $this->deposerPdf(1_000);
        $fichier = $this->fichierDepose();
        $id = $fichier->getId();
        $chemin = $this->dossier.'/'.$fichier->getNomServeur();

        $this->client->request('GET', '/fichiers/'.$id.'/supprimer');
        self::assertFileExists($chemin);
        $this->client->submitForm('Confirmer la suppression');
        self::assertResponseRedirects('/fichiers');

        $fichiers = static::getContainer()->get(EntityManagerInterface::class)->getRepository(Fichier::class);
        self::assertSame(0, $fichiers->count(['id' => $id]));
        self::assertFileDoesNotExist($chemin);
        self::assertFileDoesNotExist($chemin.'.suppression');
    }

    private function deposerPdf(int $taille): void
    {
        $pdf = require __DIR__.'/../Fixtures/page-blanche.php';
        // Les espaces avant startxref donnent au PDF la taille voulue.
        $espaces = str_repeat(' ', $taille - strlen($pdf));
        $chemin = $this->dossier.'/cours.pdf';
        file_put_contents($chemin, str_replace('startxref', $espaces.'startxref', $pdf));

        $page = $this->client->request('GET', '/profil/fichiers/ajouter');
        $form = $page->selectButton('Déposer le fichier')->form();
        $form['fichier_user[scategories]'][0]->tick();
        $form['fichier_user[fichier]']->upload($chemin);
        $this->client->submit($form);
    }

    private function fichierDepose(): Fichier
    {
        $fichier = static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(Fichier::class)->findOneBy(['nomOriginal' => 'cours.pdf']);
        self::assertInstanceOf(Fichier::class, $fichier);

        return $fichier;
    }

    private function creerMembre(string $prenom): User
    {
        $membre = (new User())->setPrenom($prenom)->setNom('Exemple')->setEmail($prenom.'@example.test')
            ->setAdrRue('1 rue du Test')->setCp('62000')->setVille('Arras')->setPassword('inutilisé');
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist($membre);
        $em->flush();

        return $membre;
    }
}
