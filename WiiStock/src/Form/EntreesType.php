<?php

namespace App\Form;

use App\Entity\Entrees;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

class EntreesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('statut')
            ->add('quantite')
            ->add('date_entree')
            ->add('quai_entree', EntityType::class, array(
                'class' => 'App\Entity\Quais',
                'choice_label' => 'nom',
                'multiple' => false,
                ))
            ->add('article', EntityType::class, array(
                'class' => 'App\Entity\Articles',
                'choice_label' => 'id',
                'multiple' => false,
                ))
            ->add('reception', EntityType::class, array(
                'class' => 'App\Entity\Receptions',
                'choice_label' => 'id',
                'multiple' => false,
                ))
            ->add('historique', EntityType::class, array(
                'class' => 'App\Entity\Historiques',
                'choice_label' => 'id',
                'multiple' => false,
                ))
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Entrees::class,
        ]);
    }
}
