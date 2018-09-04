<?php

namespace App\Form;

use App\Entity\Chariots;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class ChariotsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('proprietaire', TextType::class, array('label' => 'Propriétaire'))
			->add('n_serie', TextType::class, array('label' => 'Numéro de série'))
            ->add('parc')
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Chariots::class,
        ]);
    }
}
