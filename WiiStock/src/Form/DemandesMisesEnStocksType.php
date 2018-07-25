<?php

namespace App\Form;

use App\Entity\DemandesMisesEnStocks;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DemandesMisesEnStocksType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('date_mise_en_stock')
            ->add('emplacement')
            ->add('demande')
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => DemandesMisesEnStocks::class,
        ]);
    }
}
