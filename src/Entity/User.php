<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\EquatableInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[ORM\Table(name: 'user')]
// EXIGENCE 1 — Unicité en base + validation serveur des coordonnées via Assert.
#[UniqueEntity(fields: ['email'], message: 'Cette adresse email est déjà utilisée.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface, EquatableInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private ?string $nom = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private ?string $prenom = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $adrRue = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private ?string $ville = null;

    #[ORM\Column(length: 10)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 10)]
    #[Assert\Regex(pattern: '/^[0-9]{5}$/D', message: 'Le code postal doit contenir exactement cinq chiffres.')]
    private ?string $cp = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $dateInscription = null;

    // EXIGENCE 5 — Suspension sans supprimer le compte ni ses historiques.
    #[ORM\Column]
    private bool $estActif = true;

    /**
     * @var Collection<int, Fichier>
     */
    #[ORM\OneToMany(targetEntity: Fichier::class, mappedBy: 'user')]
    private Collection $fichiers;

    /** @var Collection<int, self> DemandeAmi : invitations envoyées. */
    #[ORM\ManyToMany(targetEntity: self::class, inversedBy: 'usersDemande')]
    #[ORM\JoinTable(name: 'user_demande')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'demander_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $demander;

    /** @var Collection<int, self> Invitations reçues. */
    #[ORM\ManyToMany(targetEntity: self::class, mappedBy: 'demander')]
    private Collection $usersDemande;

    /** @var Collection<int, self> AccepterDemande : amis, enregistrés dans les deux sens. */
    #[ORM\ManyToMany(targetEntity: self::class, inversedBy: 'userAccepte')]
    #[ORM\JoinTable(name: 'user_accepter')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'accepter_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $accepter;

    /** @var Collection<int, self> Côté inverse de la relation d'amitié. */
    #[ORM\ManyToMany(targetEntity: self::class, mappedBy: 'accepter')]
    private Collection $userAccepte;

    public function __construct()
    {
        $this->dateInscription = new \DateTimeImmutable();
        $this->fichiers = new ArrayCollection();
        $this->demander = new ArrayCollection();
        $this->usersDemande = new ArrayCollection();
        $this->accepter = new ArrayCollection();
        $this->userAccepte = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        // Ne pas embarquer les comptes amis et leurs mots de passe dans la session.
        foreach (['demander', 'usersDemande', 'accepter', 'userAccepte'] as $relation) {
            $data["\0" . self::class . "\0" . $relation] = new ArrayCollection();
        }
        $data["\0" . self::class . "\0password"] = hash('crc32c', (string) $this->password);

        return $data;
    }

    // EXIGENCE 5 — À chaque requête, Symfony compare la session avec le compte en base.
    // Une suspension ou un changement de rôle invalide donc aussi une session déjà ouverte.
    public function isEqualTo(UserInterface $user): bool
    {
        return $user instanceof self
            && $this->email === $user->email
            && $this->estActif === $user->estActif
            && $this->getRoles() === $user->getRoles()
            // Symfony stocke l'empreinte CRC32C du hachage dans la session (__serialize).
            && ($this->password === $user->password
                || $this->password === hash('crc32c', (string) $user->password));
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // @deprecated, to be removed when upgrading to Symfony 8
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;

        return $this;
    }

    public function getAdrRue(): ?string
    {
        return $this->adrRue;
    }

    public function setAdrRue(string $adrRue): static
    {
        $this->adrRue = $adrRue;

        return $this;
    }

    public function getVille(): ?string
    {
        return $this->ville;
    }

    public function setVille(string $ville): static
    {
        $this->ville = $ville;

        return $this;
    }

    public function getCp(): ?string
    {
        return $this->cp;
    }

    public function setCp(string $cp): static
    {
        $this->cp = $cp;

        return $this;
    }

    public function getDateInscription(): ?\DateTimeImmutable
    {
        return $this->dateInscription;
    }

    public function setDateInscription(\DateTimeImmutable $dateInscription): static
    {
        $this->dateInscription = $dateInscription;

        return $this;
    }

    public function isActif(): bool
    {
        return $this->estActif;
    }

    public function getEstActif(): bool
    {
        return $this->estActif;
    }

    public function setEstActif(bool $estActif): static
    {
        $this->estActif = $estActif;

        return $this;
    }

    /**
     * @return Collection<int, Fichier>
     */
    public function getFichiers(): Collection
    {
        return $this->fichiers;
    }

    public function addFichier(Fichier $fichier): static
    {
        if (!$this->fichiers->contains($fichier)) {
            $this->fichiers->add($fichier);
            $fichier->setUser($this);
        }

        return $this;
    }

    public function removeFichier(Fichier $fichier): static
    {
        if ($this->fichiers->removeElement($fichier)) {
            // set the owning side to null (unless already changed)
            if ($fichier->getUser() === $this) {
                $fichier->setUser(null);
            }
        }

        return $this;
    }

    /** @return Collection<int, self> */
    public function getDemander(): Collection
    {
        return $this->demander;
    }

    /** @return Collection<int, self> */
    public function getUsersDemande(): Collection
    {
        return $this->usersDemande;
    }

    /** @return Collection<int, self> */
    public function getAccepter(): Collection
    {
        return $this->accepter;
    }

    /** @return Collection<int, self> */
    public function getUserAccepte(): Collection
    {
        return $this->userAccepte;
    }

    public function estAmiAvec(self $user): bool
    {
        return $this->accepter->contains($user) || $this->userAccepte->contains($user);
    }

    public function addDemander(self $destinataire): static
    {
        if ($destinataire === $this) {
            throw new \DomainException('Vous ne pouvez pas vous ajouter vous-même.');
        }
        if (!$this->estActif || !$destinataire->estActif) {
            throw new \DomainException('Ce compte ne peut pas recevoir de demande.');
        }
        if ($this->estAmiAvec($destinataire)) {
            throw new \DomainException('Vous êtes déjà amis.');
        }
        if ($this->usersDemande->contains($destinataire)) {
            throw new \DomainException('Cette personne vous a déjà invité : acceptez sa demande.');
        }
        if ($this->demander->contains($destinataire)) {
            throw new \DomainException('Une demande a déjà été envoyée à cette personne.');
        }
        $this->demander->add($destinataire);
        $destinataire->usersDemande->add($this);

        return $this;
    }

    public function removeDemander(self $destinataire): static
    {
        $this->demander->removeElement($destinataire);
        $destinataire->usersDemande->removeElement($this);

        return $this;
    }

    public function removeUsersDemande(self $expediteur): static
    {
        $expediteur->removeDemander($this);

        return $this;
    }

    // Seul le destinataire d'une invitation peut transformer celle-ci en amitié.
    public function accepterDemande(self $expediteur): static
    {
        if (!$this->usersDemande->contains($expediteur)) {
            throw new \DomainException('Cette demande reçue n’existe plus.');
        }
        if (!$this->estActif || !$expediteur->estActif) {
            throw new \DomainException('Impossible d’accepter la demande d’un compte suspendu.');
        }
        $this->removeUsersDemande($expediteur);
        $this->removeDemander($expediteur);
        foreach ([[$this, $expediteur], [$expediteur, $this]] as [$user, $ami]) {
            if (!$user->accepter->contains($ami)) {
                $user->accepter->add($ami);
            }
            if (!$ami->userAccepte->contains($user)) {
                $ami->userAccepte->add($user);
            }
        }

        return $this;
    }

    public function removeAccepter(self $ami): static
    {
        // Une suppression retire l'amitié pour les deux utilisateurs.
        $this->accepter->removeElement($ami);
        $this->userAccepte->removeElement($ami);
        $ami->accepter->removeElement($this);
        $ami->userAccepte->removeElement($this);

        return $this;
    }
}
