<?php

namespace App\Form;

use App\Entity\ReferencesArticles;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class ReferencesArticlesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('libelle', TextType::class, array(
				'label' => 'Libellé',
			))
            ->add('reference', TextType::class, array(
				'label' => 'Référence',
			))
            ->add('photo_article')
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => ReferencesArticles::class,
        ]);
    }
}
