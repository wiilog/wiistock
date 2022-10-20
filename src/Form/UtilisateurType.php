<?php

namespace App\Form;

use App\Entity\Language;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Contracts\Service\Attribute\Required;
use WiiCommon\Helper\Stream;

class UtilisateurType extends AbstractType
{
    #[Required]
    public EntityManagerInterface $entityManager;

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $languages = $this->entityManager->getRepository(Language::class)->findBy(['hidden' => false]);
        $languageChoices = Stream::from($languages)
            ->keyMap(fn(Language $language) =>[$language->getLabel(), $language])
            ->toArray();
        $languagesAttr = Stream::from($languages)
            ->keyMap(fn(Language $language) => [$language->getLabel(), ['data-icon' => $language->getFlag()]])
            ->toArray();
       $builder
            ->add('email', EmailType::class, [
                'label' => "Adresse email*",
                'label_attr' => [
                    'class' => 'wii-field-name'
                ],
                "translation_domain" => false,
            ])
            ->add('username', TextType::class, [
                'label' => "Nom d'utilisateur*",
                'label_attr' => [
                    'class' => 'wii-field-name'
                ],
                "translation_domain" => false,
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
                "translation_domain" => false,
            ])
            ->add('language', ChoiceType::class, [
                'label' => "Langue*",
                'label_attr' => [
                    'class' => 'wii-field-name'
                ],
                'required' => true,
                'choices' => $languageChoices,
                'choice_attr' => $languagesAttr,
                'attr' => [
                    'class' => 'select2',
                ],
                "translation_domain" => false,
            ])
            ->add('dateFormat', ChoiceType::class, [
                'label' => "Format de date*",
                'label_attr' => [
                    'class' => 'wii-field-name'
                ],
                'required' => true,
                'choices' => Language::DATE_FORMATS,
                'attr' => [
                    'class' => 'select2',
                ],
                "translation_domain" => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Utilisateur::class,
        ]);
    }
}
