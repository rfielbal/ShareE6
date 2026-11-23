<?php

namespace App\Tests\Functional;

use App\Entity\User;
use App\Tests\Support\TestDatabase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AmisTest extends WebTestCase
{
    private KernelBrowser $client;
    private ?TestDatabase $database = null;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $database = new TestDatabase($this->em()->getConnection(), static::$kernel->getEnvironment());
        $database->reset();
        $this->database = $database;
    }

    protected function tearDown(): void
    {
        try {
            $this->database?->reset();
        } finally {
            $this->database?->close();
            parent::tearDown();
        }
    }

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    private function user(string $prenom): User
    {
        $user = (new User())->setEmail(strtolower($prenom).'@example.test')
            ->setNom('Test')->setPrenom($prenom)->setAdrRue('1 rue du Test')
            ->setVille('Arras')->setCp('62000')->setPassword('hash-test');
        $this->em()->persist($user);
        $this->em()->flush();

        return $user;
    }

    private function countRows(string $table): int
    {
        return (int) $this->em()->getConnection()->fetchOne('SELECT COUNT(*) FROM '.$table);
    }

    public function testCycleCompletPersistantEtAffichageChezLesDeuxAmis(): void
    {
        $alice = $this->user('Alice');
        $bob = $this->user('Bob');
        $aliceId = $alice->getId();
        $bobId = $bob->getId();
        $this->client->loginUser($alice);
        $this->client->request('GET', '/amis');
        self::assertResponseIsSuccessful();
        $this->client->submitForm('Envoyer l’invitation', ['ajout_ami[email]' => 'bob@example.test']);
        self::assertResponseRedirects('/amis', 303);
        self::assertSame(1, $this->countRows('user_demande'));
        self::assertSame(0, $this->countRows('user_accepter'));

        $this->client->loginUser($this->em()->find(User::class, $bobId));
        $this->client->request('GET', '/amis');
        $this->client->submitForm('Accepter');
        self::assertResponseRedirects('/amis', 303);
        self::assertSame(0, $this->countRows('user_demande'));
        self::assertSame(2, $this->countRows('user_accepter'));
        $this->client->followRedirect();
        self::assertSelectorTextContains('h2#liste-amis', '(1)');
        self::assertSelectorExists('form[action="/amis/'.$aliceId.'/supprimer"]');

        $this->client->loginUser($this->em()->find(User::class, $aliceId));
        $this->client->request('GET', '/amis');
        self::assertSelectorTextContains('h2#liste-amis', '(1)');
        $this->client->submitForm('Retirer cet ami');
        self::assertResponseRedirects('/amis', 303);
        self::assertSame(0, $this->countRows('user_accepter'));
    }

    public function testGetEtJetonInvalideNeModifientRien(): void
    {
        $alice = $this->user('Alice');
        $bob = $this->user('Bob');
        $alice->addDemander($bob);
        $this->em()->flush();
        $aliceId = $alice->getId();
        $this->client->loginUser($bob);
        $this->client->request('GET', '/amis/'.$aliceId.'/accepter');
        self::assertResponseStatusCodeSame(405);
        $this->client->request('POST', '/amis/'.$aliceId.'/accepter', ['_token' => 'invalide']);
        self::assertResponseStatusCodeSame(403);
        self::assertSame(1, $this->countRows('user_demande'));
        self::assertSame(0, $this->countRows('user_accepter'));
    }

    public function testJetonValideNePermetPasDaccepterLaDemandeDestineeAUnAutre(): void
    {
        $alice = $this->user('Alice');
        $bob = $this->user('Bob');
        $charlie = $this->user('Charlie');
        $alice->addDemander($bob);
        $alice->addDemander($charlie);
        $this->em()->flush();
        $aliceId = $alice->getId();
        $charlieId = $charlie->getId();
        $this->client->loginUser($charlie);
        $crawler = $this->client->request('GET', '/amis');
        $form = $crawler->selectButton('Accepter')->form();
        // Alice annule celle de Charlie pendant que son formulaire est encore ouvert.
        // La demande Alice -> Bob reste présente, mais ne doit jamais être acceptée par Charlie.
        $this->em()->getConnection()->executeStatement(
            'DELETE FROM user_demande WHERE user_id = ? AND demander_id = ?', [$aliceId, $charlieId]
        );
        $this->client->submit($form);
        self::assertResponseRedirects('/amis', 303);
        self::assertSame(1, $this->countRows('user_demande'));
        self::assertSame(0, $this->countRows('user_accepter'));
    }

    public function testVisiteurDoitSeConnecter(): void
    {
        $this->client->request('GET', '/amis');
        self::assertResponseRedirects('/connexion');
    }
}
