<?php

namespace App\Form;

use App\Entity\User;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;

class FichierType extends ClassementFichierType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);
        $builder->remove('enregistrer');
        $builder
            ->add('fichier', FileType::class, [
                'label' => 'Fichier à déposer',
                'mapped' => false,
                'attr' => ['accept' => '.pdf,.png,.jpg,.jpeg'],
                'constraints' => [
                    new NotBlank(message: 'Sélectionnez un fichier.'),
                    new File(
                        maxSize: '2M',
                        extensions: ['pdf', 'png', 'jpg', 'jpeg'],
                        extensionsMessage: 'Choisissez un fichier PDF, PNG ou JPG dont le contenu correspond à son extension.',
                        maxSizeMessage: 'Le fichier dépasse la limite de 2 Mo.',
                    ),
                ],
            ])
            ->add('user', EntityType::class, [
                'class' => User::class,
                'label' => 'Propriétaire',
                'placeholder' => 'Choisissez un utilisateur',
                'choice_label' => function (User $user) {
                    return $user->getNom().' '.$user->getPrenom();
                },
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('u')->orderBy('u.nom', 'ASC')->addOrderBy('u.prenom', 'ASC');
                },
                'constraints' => [new NotBlank(message: 'Choisissez un propriétaire.')],
            ])
            ->add('envoyer', SubmitType::class, ['label' => 'Déposer le fichier']);
    }
}
