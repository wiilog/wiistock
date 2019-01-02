<?php

namespace App\Form;

use App\Entity\Receptions;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReceptionsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('numeroArrivage')
            ->add('numeroReception')
            ->add('fournisseur')
            ->add('statut')
            ->add('utilisateur')
            ->add('commentaire')
            ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Receptions::class,
        ]);
    }
}
