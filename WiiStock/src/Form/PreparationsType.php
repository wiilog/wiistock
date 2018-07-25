<?php

namespace App\Form;

use App\Entity\Preparations;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PreparationsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('statut')
            ->add('date_preparation')
            ->add('quai_preparation')
            ->add('client')
            ->add('commande_client')
            ->add('historique')
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Preparations::class,
        ]);
    }
}
