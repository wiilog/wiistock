<?php

namespace App\Form;

use App\Entity\Utilisateur;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;

class UtilisateurType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => "Adresse email*",
                'label_attr' => [
                    'class' => 'wii-field-name'
                ]
            ])
            ->add('username', TextType::class, [
                'label' => "Nom d'utilisateur*",
                'label_attr' => [
                    'class' => 'wii-field-name'
                ]
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'first_options'  => [
                    'label' => 'Mot de passe*',
                    'label_attr' => [
                        'class' => 'wii-field-name'
                    ]
                ],
                'second_options' => [
                    'label' => 'Confirmer mot de passe*',
                    'label_attr' => [
                        'class' => 'wii-field-name'
                    ]
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Utilisateur::class,
        ]);
    }
}
