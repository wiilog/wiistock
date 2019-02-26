<?php
namespace App\Form;

use App\Entity\Reception;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReceptionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('numeroReception')
            ->add('fournisseur')
            ->add('utilisateur')
            ->add('statut')
            ->add('date')
            ->add('dateAttendu')
            ->add('commentaire')
            ;
    }
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Reception::class,
        ]);
    }
}