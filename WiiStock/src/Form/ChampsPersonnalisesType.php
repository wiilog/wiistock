<?php

namespace App\Form;

use App\Entity\ChampsPersonnalises;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ChampsPersonnalisesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('nom')
            ->add('type')
            ->add('unicite')
            ->add('valeur_defaut')
            ->add('nullable')
            ->add('entite_cible')
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => ChampsPersonnalises::class,
        ]);
    }
}
