<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class AjoutAmiType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('email', EmailType::class, [
            'label' => 'Adresse email de votre ami',
            'attr' => ['maxlength' => 180, 'placeholder' => 'ami@exemple.fr'],
            'constraints' => [new Assert\NotBlank(), new Assert\Email(), new Assert\Length(max: 180)],
        ]);
    }
}
