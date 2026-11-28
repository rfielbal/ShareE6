<?php

namespace App\Form;

use Symfony\Component\Form\FormBuilderInterface;

class FichierUserType extends FichierType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);
        // Chapitre 12 : le propriétaire est la personne connectée.
        $builder->remove('user');
    }
}
