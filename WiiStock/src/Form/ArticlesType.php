<?php

namespace App\Form;

use App\Entity\Articles;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;

class ArticlesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('etat')
            // ->add('valeur', MoneyType::class, array(
            //     'divisor' => 100,
            // ))
            // ->add('commentaire')
            ->add('emplacement', EntityType::class, array(
                'class' => 'App\Entity\Emplacements',
                'choice_label' => 'nom',
                'multiple' => false,
            ))
            ->add('zone', EntityType::class, array(
                'class' => 'App\Entity\Zones',
                'choice_label' => 'nom',
                'multiple' => false,
            ))
            ->add('quai', EntityType::class, array(
                'class' => 'App\Entity\Quais',
                'choice_label' => 'nom',
                'multiple' => false,
            ))
            ->add('reference', EntityType::class, array(
                'class' => 'App\Entity\ReferencesArticles',
                'choice_label' => 'reference',
                'multiple' => false,
            ));
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Articles::class,
        ]);
    }
}
