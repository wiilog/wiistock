<?php

namespace App\Form;

use App\Entity\Language;
use App\Entity\Utilisateur;
use App\Service\LanguageService;
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
            ->add('email', EmailType::class, array(
                'label' => "Adresse email*",
                "translation_domain" => false,
            ))
            ->add('username', TextType::class, array(
                'label' => "Nom d'utilisateur*",
                "translation_domain" => false,
            ))
            ->add('plainPassword', RepeatedType::class, array(
                'type' => PasswordType::class,
                'first_options' => array('label' => 'Mot de passe*'),
                'second_options' => array('label' => 'Confirmer mot de passe*'),
                "translation_domain" => false,
            ))
            ->add('language', ChoiceType::class, array(
                'label' => "Langue*",
                'required' => true,
                'choices' => $languageChoices,
                'choice_attr' => $languagesAttr,
                'attr' => array(
                    'class' => 'select2',
                ),
                "translation_domain" => false,
            ))
            ->add('dateFormat', ChoiceType::class, array(
                'label' => "Format de date*",
                'required' => true,
                'choices' => Language::DATE_FORMATS,
                'attr' => array(
                    'class' => 'select2',
                ),
                "translation_domain" => false,
            ));
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Utilisateur::class,
        ]);
    }
}
