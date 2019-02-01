<?php
namespace App\Form;
use App\Entity\Articles;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

use App\Entity\ReferencesArticles;

class ArticlesType extends AbstractType

{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('nom')
            ->add('etat', ChoiceType::class, array(
                'choices' => [
                    'conforme' => true,
                    'non conforme' => false, 
                ]
            ))
            ->add('commentaire')
            ->add('position')
            ->add('direction')
            ->add('quantite', IntegerType::class, array(
                'attr' => array(
                    'placeholder' => 'quantité',
                    'min' => 1, 'max' => 10000
            )))
            ->add('refArticle', EntityType::class,[
                'class'=> ReferencesArticles::class,
                'choice_label'=> 'libelle'
                ])           
        ;
    }
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Articles::class,
        ]);
    }
}