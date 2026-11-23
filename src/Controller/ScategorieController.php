<?php

namespace App\Controller;

use App\Entity\Scategorie;
use App\Form\ScategorieType;
use App\Repository\CategorieRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ScategorieController extends AbstractController
{
    #[Route('/admin/sous-categories/ajouter', name: 'app_ajout_scategorie', methods: ['GET', 'POST'])]
    public function ajoutScategorie(Request $request, EntityManagerInterface $em, CategorieRepository $categories): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        if ($categories->count([]) === 0) {
            $this->addFlash('error', 'Ajoutez une catégorie avant de créer une sous-catégorie.');
            return $this->redirectToRoute('app_ajout_categorie');
        }
        $scategorie = new Scategorie();
        $form = $this->createForm(ScategorieType::class, $scategorie);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($scategorie);
            $em->flush();
            $this->addFlash('success', 'La sous-catégorie a été ajoutée.');
            return $this->redirectToRoute('app_liste_categories');
        }
        return $this->render('categorie/formulaire.html.twig', ['form' => $form->createView(), 'titre' => 'Ajouter une sous-catégorie']);
    }

    #[Route('/admin/sous-categories/{id}/modifier', name: 'app_modifier_scategorie', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function modifierScategorie(Scategorie $scategorie, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $form = $this->createForm(ScategorieType::class, $scategorie);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'La sous-catégorie a été modifiée.');
            return $this->redirectToRoute('app_liste_categories');
        }
        return $this->render('categorie/formulaire.html.twig', ['form' => $form->createView(), 'titre' => 'Modifier une sous-catégorie']);
    }

    #[Route('/admin/sous-categories/{id}/supprimer', name: 'app_supprimer_scategorie', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function supprimerScategorie(Scategorie $scategorie, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $jeton = 'supprimer-scategorie-'.$scategorie->getId();
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid($jeton, $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException();
            }
            if (!$scategorie->getFichiers()->isEmpty()) {
                $this->addFlash('error', 'Cette sous-catégorie contient des fichiers. Reclassez-les avant de la supprimer.');
                return $this->redirectToRoute('app_liste_categories');
            }
            $em->remove($scategorie);
            $em->flush();
            $this->addFlash('success', 'La sous-catégorie a été supprimée.');
            return $this->redirectToRoute('app_liste_categories');
        }
        return $this->render('commun/confirmer-suppression.html.twig', [
            'titre' => 'Supprimer une sous-catégorie',
            'libelle' => $scategorie->getCategorie()->getLibelle().' / '.$scategorie->getLibelle(),
            'message' => 'Cette suppression est possible lorsque la sous-catégorie ne contient plus de fichiers.',
            'jeton' => $jeton,
            'retour' => 'app_liste_categories',
        ]);
    }
}
