<?php

namespace App\Form;

use App\Entity\DemandesTransferts;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

class DemandesTransfertsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('date_transfert')
            ->add('emplacement_debut', EntityType::class, array(
                'class' => 'App\Entity\Emplacements',
                'choice_label' => 'nom',
                'multiple' => false,
                ))
            ->add('emplacement_fin', EntityType::class, array(
                'class' => 'App\Entity\Emplacements',
                'choice_label' => 'nom',
                'multiple' => false,
                ))
            ->add('zone_debut', EntityType::class, array(
                'class' => 'App\Entity\Zones',
                'choice_label' => 'nom',
                'multiple' => false,
                ))
            ->add('zone_fin', EntityType::class, array(
                'class' => 'App\Entity\Zones',
                'choice_label' => 'nom',
                'multiple' => false,
                ))
            ->add('demande', EntityType::class, array(
                'class' => 'App\Entity\Demandes',
                'choice_label' => 'id',
                'multiple' => false,
                ))
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => DemandesTransferts::class,
        ]);
    }
}
