<?php

namespace App\EventListener;

use App\Entity\Scategorie;
use Doctrine\ORM\Event\PrePersistEventArgs;

class ScategorieListener
{
    // Chapitre 9 : un numéro ne doit pas être répété dans une même catégorie.
    public function prePersist(Scategorie $scategorie, PrePersistEventArgs $event): void
    {
        $repository = $event->getObjectManager()->getRepository(Scategorie::class);
        if ($repository->trouverDoublon($scategorie->getNumero(), $scategorie->getCategorie()) > 0) {
            throw new \RuntimeException('Ce numéro est déjà utilisé dans cette catégorie.');
        }
    }
}
