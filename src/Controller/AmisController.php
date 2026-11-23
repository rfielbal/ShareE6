<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\AjoutAmiType;
use App\Repository\UserRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class AmisController extends AbstractController
{
    #[Route('/amis', name: 'app_amis', methods: ['GET', 'POST'])]
    public function index(Request $request, #[CurrentUser] User $user, UserRepository $users, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(AjoutAmiType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $ami = $users->findOneBy(['email' => trim($form->get('email')->getData()), 'estActif' => true]);
            if (!$ami) {
                $this->addFlash('error', 'Aucun compte actif ne correspond à cette adresse email.');
            } else {
                $this->modifierRelation($em, $user, $ami, fn () => $user->addDemander($ami), 'Invitation envoyée.');
            }

            return $this->redirectToRoute('app_amis', status: Response::HTTP_SEE_OTHER);
        }

        return $this->render('amis/index.html.twig', ['form' => $form, 'user' => $user]);
    }

    #[Route('/amis/{id}/{action}', name: 'app_amis_action', requirements: ['id' => '\d+', 'action' => 'accepter|refuser|annuler|supprimer'], methods: ['POST'])]
    public function action(Request $request, int $id, string $action, #[CurrentUser] User $user, UserRepository $users, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('ami-'.$action.'-'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton de sécurité invalide.');
        }
        $ami = $users->find($id);
        if (!$ami) {
            throw $this->createNotFoundException('Utilisateur introuvable.');
        }

        $messages = [
            'accepter' => 'Demande acceptée : vous êtes maintenant amis.',
            'refuser' => 'Demande refusée.',
            'annuler' => 'Invitation annulée.',
            'supprimer' => 'Ami retiré de votre liste.',
        ];
        $this->modifierRelation($em, $user, $ami, function () use ($action, $user, $ami): void {
            // Toujours partir de l'utilisateur connecté : aucun identifiant d'expéditeur fourni par le client.
            $autorise = match ($action) {
                'accepter', 'refuser' => $user->getUsersDemande()->contains($ami),
                'annuler' => $user->getDemander()->contains($ami),
                'supprimer' => $user->estAmiAvec($ami),
            };
            if (!$autorise) {
                throw new \DomainException('Cette relation n’existe plus ou ne vous concerne pas.');
            }
            match ($action) {
                'accepter' => $user->accepterDemande($ami),
                'refuser' => $user->removeUsersDemande($ami),
                'annuler' => $user->removeDemander($ami),
                'supprimer' => $user->removeAccepter($ami),
            };
        }, $messages[$action]);

        return $this->redirectToRoute('app_amis', status: Response::HTTP_SEE_OTHER);
    }

    private function modifierRelation(EntityManagerInterface $em, User $user, User $ami, callable $modifier, string $message): void
    {
        // Même ordre de verrouillage pour éviter deux invitations croisées simultanées.
        $participants = $user === $ami ? [$user] : [$user, $ami];
        usort($participants, fn (User $a, User $b) => $a->getId() <=> $b->getId());
        $em->getConnection()->beginTransaction();
        try {
            foreach ($participants as $participant) {
                $em->refresh($participant, LockMode::PESSIMISTIC_WRITE);
            }
            $modifier();
            $em->flush();
            $em->getConnection()->commit();
            $this->addFlash('success', $message);
        } catch (\DomainException $exception) {
            $em->getConnection()->rollBack();
            $this->addFlash('error', $exception->getMessage());
        } catch (\Throwable $exception) {
            $em->getConnection()->rollBack();
            throw $exception;
        }
    }
}
