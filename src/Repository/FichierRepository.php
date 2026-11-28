<?php

namespace App\Repository;

use App\Entity\Fichier;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Fichier>
 */
class FichierRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Fichier::class);
    }

    public function trouverParProprietaire(?User $user = null): array
    {
        $qb = $this->createQueryBuilder('f')
            ->join('f.user', 'u')->addSelect('u')
            ->leftJoin('f.scategories', 's')->addSelect('s')
            ->leftJoin('s.categorie', 'c')->addSelect('c')
            ->orderBy('f.dateEnvoi', 'DESC')
            ->addOrderBy('f.id', 'DESC');

        // Le contrôleur passe null pour afficher à l'administrateur les fichiers de tous les membres.
        if ($user !== null) {
            $qb->andWhere('f.user = :user')->setParameter('user', $user);
        }

        return $qb->getQuery()->getResult();
    }

}
