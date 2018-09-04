<?php

namespace App\Form;

use App\Entity\Parcs;
use App\Entity\Filiales;
use App\Entity\Sites;
use App\Entity\Marques;
use App\Entity\CategoriesVehicules;
use App\Entity\SousCategoriesVehicules;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use App\Form\ChariotsType;
use App\Form\VehiculesType;

class ParcsType extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder
			->add('modele', TextType::class, array('label' => 'Modèle'))
			->add('filiale', EntityType::class, array(
				'class' => Filiales::class,
				'choice_label' => 'nom',
				'label' => 'Filiale'
			))
			->add('site', EntityType::class, array(
				'class' => Sites::class,
				'choice_label' => 'nom',
				'label' => 'Site'
			))
			->add('marque', EntityType::class, array(
				'class' => Marques::class,
				'choice_label' => 'nom',
				'label' => 'Marque'
			))
			->add('categorieVehicule', EntityType::class, array(
				'class' => CategoriesVehicules::class,
				'choice_label' => 'nom',
				'label' => 'Catégorie'
			))
			->add('sousCategorieVehicule', EntityType::class, array(
				'class' => SousCategoriesVehicules::class,
				'choice_label' => 'nom',
				'label' => 'Sous catégorie'
			))
			->add('statut', ChoiceType::class, array(
				'label' => 'Statut',
				'choices' => array(
					'Demande création' => 'Demande création',
					'Actif' => 'Actif',
					'Demande sortie/transfert' => 'Demande sortie/transfert',
					'Sorti' => 'Sorti',
				),
			))
			->add('n_parc', TextType::class, array('label' => 'Numéro de parc'))
			->add('mise_en_circulation', DateType::class, array(
				'label' => 'Date de mise en circulation',
				'widget' => 'single_text',
				'format' => 'dd/MM/yyyy',
				'attr' => [
					'class' => 'datepicker',
				],
			))
			->add('fournisseur', TextType::class, array('label' => 'Fournisseur'))
			->add('poids', IntegerType::class, array('label' => 'Poids (Tonne)'))
			->add('mode_acquisition', ChoiceType::class, array(
				'label' => 'Mode d\'acquisition',
				'choices' => array(
					'Achat neuf' => 'Achat neuf',
					'Achat d’occasion' => 'Achat d’occasion',
					'Location longue durée' => 'Location longue durée',
					'Mise à disposition' => 'Mise à disposition',
				),
			))
			->add('commentaire', TextareaType::class, array('label' => 'Commentaire'))
			->add('sortie', DateType::class, array(
				'label' => 'Date de sortie',
				'widget' => 'single_text',
				'format' => 'dd/MM/yyyy',
				'attr' => [
					'class' => 'datepicker',
				],
			))
			->add('incorporation', DateType::class, array(
				'label' => 'Date d\'incorporation',
				'widget' => 'single_text',
				'format' => 'dd/MM/yyyy',
				'attr' => [
					'class' => 'datepicker',
				],
			))
			->add('mise_en_service', DateType::class, array(
				'label' => 'Date de mise en service',
				'widget' => 'single_text',
				'format' => 'dd/MM/yyyy',
				'attr' => [
					'class' => 'datepicker',
				],
			))
			->add('sortie', DateType::class, array(
				'label' => 'Date de sortie',
				'widget' => 'single_text',
				'format' => 'dd/MM/yyyy',
				'attr' => [
					'class' => 'datepicker',
				],
			))
			->add('motif', ChoiceType::class, array(
				'label' => 'Motif',
				'choices' => array(
					'Mise au rebus' => 'Mise au rebus',
					'Transfert vers site d’une filiale différente' => 'Transfert vers site d’une filiale différente',
					'Transfert vers site d’une même filiale' => 'Transfert vers site d’une même filiale',
				),
			))
			->add('commentaire_sortie', TextareaType::class, array('label' => 'Commentaire sortie'))
			// ->add('chariots', CollectionType::class, array(
			// 	'entry_type' => ChariotsType::class,
			// 	'entry_options' => array('label' => false),
			// ))
			// ->add('vehicules', CollectionType::class, array(
			// 	'entry_type' => VehiculesType::class,
			// 	'entry_options' => array('label' => false),
			// ))
			->add('chariots', ChariotsType::class)
			->add('vehicules', VehiculesType::class)
			->add('validation', SubmitType::class, array('label' => 'Ajouter'));
	}

	public function configureOptions(OptionsResolver $resolver)
	{
		$resolver->setDefaults([
			'data_class' => Parcs::class,
		]);
	}
}
