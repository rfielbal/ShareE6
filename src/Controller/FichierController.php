<?php

namespace App\Controller;

use App\Entity\Fichier;
use App\Form\ClassementFichierType;
use App\Form\FichierType;
use App\Form\FichierUserType;
use App\Repository\FichierRepository;
use App\Repository\ScategorieRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class FichierController extends AbstractController
{
    #[Route('/fichiers', name: 'app_liste_fichiers', methods: ['GET'])]
    public function listeFichiers(FichierRepository $repository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $administrateur = $this->isGranted('ROLE_ADMIN');
        return $this->render('fichier/liste-fichiers.html.twig', [
            'fichiers' => $repository->trouverParProprietaire($administrateur ? null : $this->getUser()),
            'titre' => $administrateur ? 'Tous les fichiers' : 'Mes fichiers',
        ]);
    }

    #[Route('/admin/fichiers/par-utilisateur', name: 'app_fichiers_par_utilisateur', methods: ['GET'])]
    public function fichiersParUtilisateur(UserRepository $repository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        return $this->render('fichier/par-utilisateur.html.twig', [
            'users' => $repository->findBy([], ['nom' => 'ASC', 'prenom' => 'ASC']),
        ]);
    }

    #[Route('/fichiers/ajouter', name: 'app_ajout_fichier', methods: ['GET', 'POST'])]
    #[Route('/profil/fichiers/ajouter', name: 'app_ajout_fichier_user', methods: ['GET', 'POST'])]
    public function ajoutFichier(Request $request, ScategorieRepository $repository, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $fichier = new Fichier();
        $fichier->setUser($this->getUser());
        $scategories = $repository->trouverPourClassement();
        $depotPersonnel = $request->attributes->get('_route') === 'app_ajout_fichier_user';
        $type = $this->isGranted('ROLE_ADMIN') && !$depotPersonnel ? FichierType::class : FichierUserType::class;
        $form = $this->createForm($type, $fichier, ['scategories' => $scategories]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            foreach ($form->get('scategories')->getData() as $scategorie) {
                $fichier->addScategory($scategorie);
            }
            $file = $form->get('fichier')->getData();
            $nom = $slugger->slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))->slice(0, 150);
            $nomServeur = $nom.'-'.uniqid().'.'.$file->guessExtension();
            $fichier->setNomOriginal($file->getClientOriginalName());
            $fichier->setNomServeur($nomServeur);
            $fichier->setExtension($file->guessExtension());
            $fichier->setTaille($file->getSize());

            try {
                // Le fichier doit être présent sur le disque avant d'enregistrer ses informations.
                $file->move($this->getParameter('file_directory'), $nomServeur);
            } catch (FileException $exception) {
                $form->get('fichier')->addError(new FormError('Le fichier n’a pas pu être enregistré. Réessayez.'));
            }

            if ($form->get('fichier')->getErrors()->count() === 0) {
                try {
                    $em->persist($fichier);
                    $em->flush();
                } catch (\Throwable $exception) {
                    // Éviter un fichier abandonné si l'enregistrement en base échoue.
                    unlink($this->getParameter('file_directory').'/'.$nomServeur);
                    throw $exception;
                }
                $this->addFlash('success', 'Le fichier a été déposé et classé.');
                return $this->redirectToRoute($depotPersonnel ? 'app_profile' : 'app_liste_fichiers');
            }
        }

        return $this->render('fichier/ajout-fichier.html.twig', [
            'form' => $form->createView(), 'scategories' => $scategories,
        ]);
    }

    #[Route('/fichiers/{id}/classement', name: 'app_modifier_classement', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function modifierClassement(Fichier $fichier, Request $request, ScategorieRepository $repository, EntityManagerInterface $em): Response
    {
        $this->verifierAcces($fichier);
        $scategories = $repository->trouverPourClassement();
        $form = $this->createForm(ClassementFichierType::class, $fichier, ['scategories' => $scategories]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            foreach ($fichier->getScategories()->toArray() as $scategorie) {
                $fichier->removeScategory($scategorie);
            }
            foreach ($form->get('scategories')->getData() as $scategorie) {
                $fichier->addScategory($scategorie);
            }
            $em->flush();
            $this->addFlash('success', 'Le classement du fichier a été modifié.');
            return $this->redirectToRoute('app_liste_fichiers');
        }
        return $this->render('fichier/classement.html.twig', [
            'form' => $form->createView(), 'scategories' => $scategories, 'fichier' => $fichier,
        ]);
    }

    #[Route('/fichiers/{id}/telecharger', name: 'app_telechargement_fichier', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function telechargerFichier(Fichier $fichier): Response
    {
        $this->verifierAcces($fichier);
        $chemin = $this->getParameter('file_directory').'/'.$fichier->getNomServeur();
        if (!is_file($chemin)) {
            throw $this->createNotFoundException('Le fichier est introuvable sur le serveur.');
        }
        $response = $this->file($chemin, $fichier->getNomOriginal());
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        return $response;
    }

    #[Route('/fichiers/{id}/supprimer', name: 'app_supprimer_fichier', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function supprimerFichier(Fichier $fichier, Request $request, EntityManagerInterface $em): Response
    {
        $this->verifierAcces($fichier);
        $jeton = 'supprimer-fichier-'.$fichier->getId();
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid($jeton, $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException();
            }
            $chemin = $this->getParameter('file_directory').'/'.$fichier->getNomServeur();
            // Mettre le fichier de côté permet de le restaurer si la suppression en base échoue.
            $retrait = $chemin.'.suppression';
            $present = is_file($chemin);
            if ($present && !rename($chemin, $retrait)) {
                $this->addFlash('error', 'Le fichier ne peut pas être supprimé pour le moment.');
                return $this->redirectToRoute('app_liste_fichiers');
            }
            try {
                $em->remove($fichier);
                $em->flush();
            } catch (\Throwable $exception) {
                if ($present) {
                    rename($retrait, $chemin);
                }
                throw $exception;
            }
            if ($present) {
                unlink($retrait);
            }
            $this->addFlash('success', 'Le fichier et son classement ont été supprimés.');
            return $this->redirectToRoute('app_liste_fichiers');
        }
        return $this->render('commun/confirmer-suppression.html.twig', [
            'titre' => 'Supprimer un fichier', 'libelle' => $fichier->getNomOriginal(),
            'message' => 'Le fichier sera retiré du serveur et de votre liste. Cette action est définitive.',
            'jeton' => $jeton, 'retour' => 'app_liste_fichiers',
        ]);
    }

    private function verifierAcces(Fichier $fichier): void
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        if (!$this->isGranted('ROLE_ADMIN') && $fichier->getUser()->getId() !== $this->getUser()->getId()) {
            throw $this->createAccessDeniedException('Vous n’êtes pas le propriétaire de ce fichier.');
        }
    }
}
