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
            ->add('email', EmailType::class, array(
                'label' => "Adresse email"
            ))
            ->add('username', TextType::class, array(
                'label' => "Nom d'utilisateur",
            ))
            ->add('plainPassword', RepeatedType::class, array(
                'type' => PasswordType::class,
                'first_options'  => array('label' => 'Mot de Passe'),
                'second_options' => array('label' => 'Confirmer Mot de Passe'),
            ))
            // ->add('groupe', EntityType::class, array(
            //     'class' => 'App\Entity\Groupes',
            //     'choice_label' => 'nom',
            //     'multiple' => false,
            //     ))

            // ->add('roles', ChoiceType::class, array(
            //     'label' => "Rôles",
            //     'choices' => array(
            //         'ROLE_PARC' => 'ROLE_PARC',
            //         'ROLE_USER' => 'ROLE_USER',
            //         'ROLE_ADMIN' => 'ROLE_ADMIN',
            //         'ROLE_PARC_ADMIN' => 'ROLE_PARC_ADMIN',
            //         'ROLE_API' => 'ROLE_API',
            //         ),
            //     'multiple' => true,
            //     ))
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Utilisateur::class,
        ]);
    }
}
