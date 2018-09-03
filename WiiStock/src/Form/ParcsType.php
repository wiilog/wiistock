<?php

namespace App\Form;

use App\Entity\Parcs;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ParcsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('modele')
            ->add('statut')
            ->add('n_parc')
            ->add('mise_en_circulation')
            ->add('fournisseur')
            ->add('poids')
            ->add('mode_acquisition')
            ->add('commentaire')
            ->add('incorporation')
            ->add('mise_en_service')
            ->add('sortie')
            ->add('motif')
            ->add('commentaire_sortie')
            ->add('chariots')
            ->add('vehicules')
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Parcs::class,
        ]);
    }
}
