<?php

namespace App\Form;

use App\Entity\Collecte;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

use App\Entity\Articles;
use Doctrine\ORM\EntityRepository;


class CollecteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('articles', EntityType::class, array(
                'class' => Articles::class,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('a')
                        ->where('a.statu = :statut')
                        ->setParameter('statut', 'destokage')
                        ;
                       
                    },
                'label' => 'articles',
                'expanded' => true,
                'multiple' => true))
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Collecte::class,
        ]);
    }
}
