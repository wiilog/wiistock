<?php

namespace App\Form;

use App\Entity\Transferts;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TransfertsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('statut')
            ->add('quantite')
            ->add('date_transfert')
            ->add('emplacement_debut')
            ->add('emplacement_fin')
            ->add('zone_debut')
            ->add('zone_fin')
            ->add('article')
            ->add('historique')
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Transferts::class,
        ]);
    }
}
