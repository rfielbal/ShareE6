<?php

namespace App\Repository;

use App\Entity\Scategorie;
use App\Entity\Categorie;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Scategorie>
 */
class ScategorieRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Scategorie::class);
    }

    public function trouverDoublon(int $numero, Categorie $categorie): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s)')
            ->where('s.numero = :numero')
            ->andWhere('s.categorie = :categorie')
            ->setParameter('numero', $numero)
            ->setParameter('categorie', $categorie)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function trouverPourClassement(): array
    {
        return $this->createQueryBuilder('s')
            ->join('s.categorie', 'c')->addSelect('c')
            ->orderBy('c.libelle', 'ASC')
            ->addOrderBy('s.numero', 'ASC')
            ->getQuery()->getResult();
    }

}
