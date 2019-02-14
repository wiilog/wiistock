<?php
namespace App\Form;
use App\Entity\Sites;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Doctrine\ORM\EntityRepository;
class SitesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('nom')
            ->add('filiale', EntityType::class, array(
                'class' => 'App\Entity\Filiales',
                'choice_label' => 'nom',
                'multiple' => false,
                'query_builder' => function (EntityRepository $er) {
					return $er->createQueryBuilder('f')
						->orderBy('f.nom', 'ASC');
				},
            ));
    }
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Sites::class,
        ]);
    }
}