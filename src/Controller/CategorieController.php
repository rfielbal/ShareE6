<?php

namespace App\Controller;

use App\Entity\Categorie;
use App\Form\CategorieType;
use App\Repository\CategorieRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CategorieController extends AbstractController
{
    #[Route('/categories', name: 'app_liste_categories', methods: ['GET'])]
    public function listeCategories(CategorieRepository $repository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        return $this->render('categorie/liste-categories.html.twig', [
            'categories' => $repository->findBy([], ['libelle' => 'ASC']),
        ]);
    }

    #[Route('/admin/categories/ajouter', name: 'app_ajout_categorie', methods: ['GET', 'POST'])]
    public function ajoutCategorie(Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $categorie = new Categorie();
        $form = $this->createForm(CategorieType::class, $categorie);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $categorie->setDateEnvoi(new \DateTime());
            $em->persist($categorie);
            $em->flush();
            $this->addFlash('success', 'La catégorie a été ajoutée.');
            return $this->redirectToRoute('app_liste_categories');
        }
        return $this->render('categorie/formulaire.html.twig', ['form' => $form->createView(), 'titre' => 'Ajouter une catégorie']);
    }

    #[Route('/admin/categories/{id}/modifier', name: 'app_modifier_categorie', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function modifierCategorie(Categorie $categorie, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $form = $this->createForm(CategorieType::class, $categorie);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'La catégorie a été modifiée.');
            return $this->redirectToRoute('app_liste_categories');
        }
        return $this->render('categorie/formulaire.html.twig', ['form' => $form->createView(), 'titre' => 'Modifier une catégorie']);
    }

    #[Route('/admin/categories/{id}/supprimer', name: 'app_supprimer_categorie', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function supprimerCategorie(Categorie $categorie, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $jeton = 'supprimer-categorie-'.$categorie->getId();
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid($jeton, $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException();
            }
            // Supprimer la catégorie supprime aussi ses sous-catégories. Chacune doit donc être vide.
            foreach ($categorie->getScategories() as $scategorie) {
                if (!$scategorie->getFichiers()->isEmpty()) {
                    $this->addFlash('error', 'Cette catégorie contient des fichiers. Reclassez-les avant de la supprimer.');
                    return $this->redirectToRoute('app_liste_categories');
                }
            }
            $em->remove($categorie);
            $em->flush();
            $this->addFlash('success', 'La catégorie et ses sous-catégories ont été supprimées.');
            return $this->redirectToRoute('app_liste_categories');
        }
        return $this->render('commun/confirmer-suppression.html.twig', [
            'titre' => 'Supprimer une catégorie',
            'libelle' => $categorie->getLibelle(),
            'message' => 'Les sous-catégories seront aussi supprimées. Une catégorie contenant des fichiers doit d’abord être vidée par reclassement.',
            'jeton' => $jeton,
            'retour' => 'app_liste_categories',
        ]);
    }
}
