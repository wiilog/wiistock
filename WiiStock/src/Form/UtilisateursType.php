<?php

namespace App\Form;

use App\Entity\Utilisateurs;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

class UtilisateursType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('email', EmailType::class)
            ->add('username', TextType::class)
            ->add('plainPassword', RepeatedType::class, array(
                'type' => PasswordType::class,
                'first_options'  => array('label' => 'Password'),
                'second_options' => array('label' => 'Repeat Password'),
            ))
            ->add('groupe', EntityType::class, array(
                'class' => 'App\Entity\Groupes',
                'choice_label' => 'nom',
                'multiple' => false,
                ))
            ->add('theme', EntityType::class, array(
                'class' => 'App\Entity\Themes',
                'choice_label' => 'nom',
                'multiple' => false,
                ))
            ->add('roles', ChoiceType::class, array(
                'label' => "Rôles",
                'choices' => array(
                    'ROLE_PARC' => 'ROLE_PARC',
                    'ROLE_USER' => 'ROLE_USER',
                    'ROLE_ADMIN' => 'ROLE_ADMIN',
                    'ROLE_PARC_ADMIN' => 'ROLE_PARC_ADMIN',
                    'ROLE_API' => 'ROLE_API',
                    ),
                'multiple' => true,
                ))
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Utilisateurs::class,
        ]);
    }
}
