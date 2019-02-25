<?php
namespace App\Form;

use App\Entity\Article;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

use App\Entity\ReferenceArticle;

class ArticleType extends AbstractType

{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('nom')
            ->add('etat', ChoiceType::class, array(
                'choices' => [
                    'conforme' => true,
                    'non conforme' => false,
                ],
                'label' => 'État'
            ))
            ->add('commentaire')
            ->add('position')
            ->add('direction')
            ->add('quantite', IntegerType::class, array(
                'attr' => array(
                    'placeholder' => 'quantité',
                    'min' => 0, 'max' => 10000, 
                ),
                'label' => 'Quantité reçu'
            ))
            ->add('quantiteARecevoir', IntegerType::class, array(
                'attr' => array(
                    'placeholder' => 'quantité à recevoir',
                    'min' => 0, 'max' => 10000
                ),
                'label' => 'Quantité à recevoir'
            ))
            ->add('refArticle', EntityType::class, [
                'class' => ReferenceArticle::class,
                'choice_label' => 'libelle',
                'placeholder' => 'Référence article',
                'label'=> 'Référence article'
                
            ]);
    }
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Article::class,
        ]);
    }
}