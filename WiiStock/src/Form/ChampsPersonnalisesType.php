<?php

namespace App\Form;

use App\Entity\ChampsPersonnalises;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class ChampsPersonnalisesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('nom', TextType::class, array(
                'label' => 'Nom du champ',
            ))
            ->add('type', ChoiceType::class, array(
                'label' => 'Type de champ',
                'choices' => array(
                    'Texte' => 'texte',
                    'Nombre' => 'nombre',
                ),
            ))
            ->add('entite_cible', ChoiceType::class, array(
                'label' => 'Entité cible',
                'choices' => array(
                    'Référence article' => 'references_articles',
                    'Article' => 'articles',
                ),
            ))
            ->add('unicite', CheckboxType::class, array(
                'label' => 'Unicité',
                'required' => false,
            ))
            ->add('nullable', CheckboxType::class, array(
                'label' => 'Peut être vide',
                'required' => false,
            ))
            ->add('valeur_defaut', TextType::class, array(
                'label' => 'Valeur par défaut',
            ));
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => ChampsPersonnalises::class,
        ]);
    }
}
