<?php

namespace App\Form;

use App\Entity\Fichier;
use App\Entity\Scategorie;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Count;

class ClassementFichierType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('scategories', EntityType::class, [
                'class' => Scategorie::class,
                'choices' => $options['scategories'],
                'choice_label' => 'libelle',
                'expanded' => true,
                'multiple' => true,
                'label' => 'Sous-catégories',
                'mapped' => false,
                'data' => $builder->getData()->getScategories()->toArray(),
                'constraints' => [new Count(min: 1, minMessage: 'Choisissez au moins une sous-catégorie.')],
            ])
            ->add('enregistrer', SubmitType::class, ['label' => 'Enregistrer le classement']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Fichier::class, 'scategories' => []]);
    }
}
