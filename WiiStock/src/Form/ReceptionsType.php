<?php

namespace App\Form;

use App\Entity\Receptions;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

class ReceptionsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('statut')
            ->add('date_reception')
            ->add('quai_reception', EntityType::class, array(
                'class' => 'App\Entity\Quais',
                'choice_label' => 'nom',
                'multiple' => false,
                ))
            ->add('fournisseur', EntityType::class, array(
                'class' => 'App\Entity\Fournisseurs',
                'choice_label' => 'nom',
                'multiple' => false,
                ))
            ->add('commande_fournisseur', EntityType::class, array(
                'class' => 'App\Entity\CommandesFournisseurs',
                'choice_label' => 'libelle',
                'multiple' => false,
                ))
            ->add('historique', EntityType::class, array(
                'class' => 'App\Entity\Historiques',
                'choice_label' => 'id',
                'multiple' => false,
                ))
            ->add('ordre', EntityType::class, array(
                'class' => 'App\Entity\Ordres',
                'choice_label' => 'id',
                'multiple' => false,
            ))
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Receptions::class,
        ]);
    }
}
