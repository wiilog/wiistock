<?php

namespace App\Form;

use App\Entity\ReferencesFournisseurs;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

class ReferencesFournisseursType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('libelle')
            ->add('poids')
            ->add('dimension')
            ->add('description')
            ->add('reference', EntityType::class, array(
                'class' => 'App\Entity\References',
                'choice_label' => 'id',
                'multiple' => false,
                ))
            ->add('fournisseur', EntityType::class, array(
                'class' => 'App\Entity\Fournisseurs',
                'choice_label' => 'nom',
                'multiple' => false,
                ))
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => ReferencesFournisseurs::class,
        ]);
    }
}
