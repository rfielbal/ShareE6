<?php

namespace App\Form;

use App\Entity\Categorie;
use App\Entity\Scategorie;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ScategorieType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('categorie', EntityType::class, [
                'class' => Categorie::class,
                'choice_label' => 'libelle',
                'label' => 'Catégorie',
                'placeholder' => 'Choisissez une catégorie',
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('c')->orderBy('c.libelle', 'ASC');
                },
            ])
            ->add('numero', IntegerType::class, ['label' => 'Numéro dans la catégorie', 'attr' => ['min' => 1]])
            ->add('libelle', TextType::class, ['label' => 'Libellé', 'attr' => ['maxlength' => 50]])
            ->add('enregistrer', SubmitType::class, ['label' => 'Enregistrer la sous-catégorie']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Scategorie::class]);
    }
}
