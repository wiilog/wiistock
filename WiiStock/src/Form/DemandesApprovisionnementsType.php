<?php

namespace App\Form;

use App\Entity\DemandesApprovisionnements;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

class DemandesApprovisionnementsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('date_approvisionnement')
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
            'data_class' => DemandesApprovisionnements::class,
        ]);
    }
}
