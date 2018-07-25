<?php

namespace App\Form;

use App\Entity\Entrees;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EntreesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('statut')
            ->add('quantite')
            ->add('date_entree')
            ->add('quai_entree')
            ->add('article')
            ->add('reception')
            ->add('historique')
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Entrees::class,
        ]);
    }
}
