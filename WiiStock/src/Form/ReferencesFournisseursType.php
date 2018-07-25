<?php

namespace App\Form;

use App\Entity\ReferencesFournisseurs;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReferencesFournisseursType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('libelle')
            ->add('poids')
            ->add('dimension')
            ->add('description')
            ->add('reference')
            ->add('fournisseur')
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => ReferencesFournisseurs::class,
        ]);
    }
}
