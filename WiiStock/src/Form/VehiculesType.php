<?php

namespace App\Form;

use App\Entity\Vehicules;
use App\Entity\Parcs;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class VehiculesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('immatriculation')
            ->add('genre', ChoiceType::class, array(
				'label' => 'Genre (J1)',
				'choices' => array(
					'Genre 1' => 'Genre 1',
                    'Genre 2' => 'Genre 2',
                    'Genre 3' => 'Genre 3',
				),
			))
            ->add('ptac', IntegerType::class, array('label' => 'Ptac (F2)'))
            ->add('ptr', IntegerType::class, array('label' => 'Ptr (F3)'))
            ->add('puissance_fiscale')
            ->add('parc', ParcsType::class)
            ->add('validation', SubmitType::class, array('label' => 'Ajouter'));
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Vehicules::class,
        ]);
    }
}
