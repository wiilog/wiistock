<?php

namespace App\Form;

use App\Entity\DemandesMisesEnStocks;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

class DemandesMisesEnStocksType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('date_mise_en_stock')
            ->add('emplacement', EntityType::class, array(
                'class' => 'App\Entity\Emplacements',
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
            'data_class' => DemandesMisesEnStocks::class,
        ]);
    }
}
