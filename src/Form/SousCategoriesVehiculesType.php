<?php
namespace App\Form;
use App\Entity\SousCategoriesVehicules;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Doctrine\ORM\EntityRepository;
class SousCategoriesVehiculesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('nom')
            ->add('code')
            ->add('categorie', EntityType::class, array(
                'class' => 'App\Entity\CategoriesVehicules',
                'choice_label' => 'nom',
                'multiple' => false,
                'query_builder' => function (EntityRepository $er) {
					return $er->createQueryBuilder('c')
						->orderBy('c.nom', 'ASC');
				},
            ));
    }
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => SousCategoriesVehicules::class,
        ]);
    }
}