<?php

namespace App\Form;

use App\Entity\DemandesLivraisons;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

class DemandesLivraisonsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('date_livraison')
            ->add('adresse_livraison')
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
            'data_class' => DemandesLivraisons::class,
        ]);
    }
}
