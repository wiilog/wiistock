<?php

namespace App\Form;

use App\Entity\Preparation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use App\Entity\Article;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

class PreparationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('statut')
            // ->add('destination')
            // ->add('utilisateur')
            // ->add('article',  EntityType::class, array(
            //     'class' => Articles::class,
            //     'choice_label' =>  'nom',
            //     'multiple' => true,
            // ))
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Preparation::class,
        ]);
    }
}
