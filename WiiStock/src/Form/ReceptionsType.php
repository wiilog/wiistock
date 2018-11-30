<?php

namespace App\Form;

use App\Entity\Receptions;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;

class ReceptionsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('statut')
            ->add('reference_SAP', TextType::class, array(
				'label' => 'Référence SAP',
			))
            ->add('nom_CEA', TextType::class, array(
				'label' => 'Nom',
			))
            ->add('prenom_CEA', TextType::class, array(
				'label' => 'Prénom',
			))
            ->add('mail_CEA', EmailType::class, array(
				'label' => 'Email',
			))
            ->add('date_au_plus_tot', DateType::class, array(
				'label' => 'Au plus tôt',
				'widget' => 'single_text',
				'format' => 'dd/MM/yyyy',
				'attr' => [
					'class' => 'datepicker',
				],
			))
            ->add('date_prevue', DateType::class, array(
				'label' => 'Prévue',
				'widget' => 'single_text',
				'format' => 'dd/MM/yyyy',
				'attr' => [
					'class' => 'datepicker',
				],
			))
            ->add('date_au_plus_tard', DateType::class, array(
				'label' => 'Au plus tard',
				'widget' => 'single_text',
				'format' => 'dd/MM/yyyy',
				'attr' => [
					'class' => 'datepicker',
				],
			))
			->add('commentaire', TextareaType::class, array(
				'label' => 'Commentaire',
			))
            ->add('fournisseur', EntityType::class, array(
                'class' => 'App\Entity\Fournisseurs',
                'choice_label' => 'nom',
                'multiple' => false,
            ))
            ->add('code_ref_transporteur', TextType::class, array(
				'label' => 'Code référence',
			))
            ->add('nom_transporteur', TextType::class, array(
				'label' => 'Nom',
			));
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Receptions::class,
        ]);
    }
}
