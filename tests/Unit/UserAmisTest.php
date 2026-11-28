<?php

namespace App\Tests\Unit;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class UserAmisTest extends TestCase
{
    public function testInvitationAcceptationEtSuppressionReciproque(): void
    {
        $alice = new User();
        $bob = new User();
        $alice->addDemander($bob);
        self::assertTrue($bob->getUsersDemande()->contains($alice));
        self::assertFalse($alice->estAmiAvec($bob));

        $bob->accepterDemande($alice);
        self::assertCount(0, $alice->getDemander());
        self::assertCount(0, $bob->getUsersDemande());
        self::assertTrue($alice->estAmiAvec($bob));
        self::assertTrue($bob->estAmiAvec($alice));
        self::assertCount(1, $alice->getAccepter());
        self::assertCount(1, $bob->getAccepter());

        $alice->removeAccepter($bob);
        self::assertFalse($alice->estAmiAvec($bob));
        self::assertFalse($bob->estAmiAvec($alice));
        $bob->addDemander($alice); // Une nouvelle invitation est possible après suppression.
        self::assertTrue($alice->getUsersDemande()->contains($bob));
    }

    public function testRefusEtAnnulationRetirentLesDeuxCotes(): void
    {
        $alice = new User();
        $bob = new User();
        $alice->addDemander($bob);
        $bob->removeUsersDemande($alice);
        self::assertCount(0, $alice->getDemander());
        self::assertCount(0, $bob->getUsersDemande());
        $alice->addDemander($bob);
        $alice->removeDemander($bob);
        self::assertCount(0, $alice->getDemander());
        self::assertCount(0, $bob->getUsersDemande());
    }

    public function testImpossibleDeSinviter(): void
    {
        $alice = new User();
        $this->expectException(\DomainException::class);
        $alice->addDemander($alice);
    }

    public function testImpossibleDeDoublerUneInvitation(): void
    {
        $alice = new User();
        $bob = new User();
        $alice->addDemander($bob);
        $this->expectException(\DomainException::class);
        $alice->addDemander($bob);
    }

    public function testInvitationCroiseeDoitEtreAcceptee(): void
    {
        $alice = new User();
        $bob = new User();
        $alice->addDemander($bob);
        $this->expectException(\DomainException::class);
        $bob->addDemander($alice);
    }

    public function testExpediteurNePeutPasAccepterSaPropreDemande(): void
    {
        $alice = new User();
        $bob = new User();
        $alice->addDemander($bob);
        $this->expectException(\DomainException::class);
        $alice->accepterDemande($bob);
    }

    public function testCompteSuspenduNePeutPasDevenirAmi(): void
    {
        $alice = new User();
        $bob = new User();
        $alice->addDemander($bob);
        $alice->setEstActif(false);
        $this->expectException(\DomainException::class);
        $bob->accepterDemande($alice);
    }

    public function testSessionNeContientPasLesComptesAmis(): void
    {
        $alice = (new User())->setEmail('alice@example.test')->setPassword('hash-alice');
        $bob = (new User())->setEmail('bob@example.test')->setPassword('hash-bob');
        $alice->addDemander($bob);
        $bob->accepterDemande($alice);
        $serialized = serialize($alice);
        self::assertStringNotContainsString('bob@example.test', $serialized);
        self::assertStringNotContainsString('hash-bob', $serialized);
        self::assertStringNotContainsString('hash-alice', $serialized);
        $sessionUser = unserialize($serialized);
        self::assertTrue($sessionUser->isEqualTo($alice));
        self::assertCount(0, $sessionUser->getAccepter());
    }
}
