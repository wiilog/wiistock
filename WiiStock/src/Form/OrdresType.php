<?php

namespace App\Form;

use App\Entity\Ordres;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

class OrdresType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('statut')
            ->add('type')
            ->add('date_ordre')
            ->add('auteur', EntityType::class, array(
                'class' => 'App\Entity\Utilisateurs',
                'choice_label' => 'username',
                'multiple' => false,
                ))
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Ordres::class,
        ]);
    }
}
