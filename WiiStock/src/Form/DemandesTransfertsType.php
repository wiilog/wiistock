<?php

namespace App\Form;

use App\Entity\DemandesTransferts;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DemandesTransfertsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('date_transfert')
            ->add('emplacement_debut')
            ->add('emplacement_fin')
            ->add('zone_debut')
            ->add('zone_fin')
            ->add('demande')
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => DemandesTransferts::class,
        ]);
    }
}
