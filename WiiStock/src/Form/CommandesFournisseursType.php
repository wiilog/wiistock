<?php

namespace App\Form;

use App\Entity\CommandesFournisseurs;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

class CommandesFournisseursType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('libelle')
            ->add('date_commande')
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
            'data_class' => CommandesFournisseurs::class,
        ]);
    }
}
