<?php

namespace App\EventListener;

use App\Entity\Fichier;

class FichierListener
{
    // Chapitre 11 : la date est renseignée lors du premier enregistrement.
    public function prePersist(Fichier $fichier): void
    {
        $fichier->setDateEnvoi(new \DateTime());
    }
}
