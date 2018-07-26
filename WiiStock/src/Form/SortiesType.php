<?php

namespace App\Form;

use App\Entity\Sorties;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

class SortiesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('statut')
            ->add('quantite')
            ->add('quai_sortie', EntityType::class, array(
                'class' => 'App\Entity\Quais',
                'choice_label' => 'nom',
                'multiple' => false,
                ))
            ->add('article', EntityType::class, array(
                'class' => 'App\Entity\Articles',
                'choice_label' => 'id',
                'multiple' => false,
                ))
            ->add('preparation', EntityType::class, array(
                'class' => 'App\Entity\Preparations',
                'choice_label' => 'id',
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
            'data_class' => Sorties::class,
        ]);
    }
}
