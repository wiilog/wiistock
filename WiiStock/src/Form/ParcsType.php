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
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use App\Form\ChariotsType;
use App\Form\VehiculesType;
use Doctrine\ORM\EntityRepository;

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
				'label' => 'Site',
				// 'query_builder' => function (EntityRepository $er) {
				// 	return $er->createQueryBuilder('p')
				// 		->add('select', 'p')
				// 		->leftJoin('p.filiale', 'f')
				// 		->where('f.id = :id')
				// 		->setParameter('id', '1');
				// },
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
				'label' => 'Sous catégorie',
				// 'query_builder' => function (EntityRepository $er) {
				// 	return $er->createQueryBuilder('p')
				// 		->add('select', 'p')
				// 		->leftJoin('p.categorie', 'c')
				// 		->where('c.id = :id')
				// 		->setParameter('id', '1');
				// },
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
			->add('n_parc', TextType::class, array(
				'label' => 'Numéro de parc',
				'attr' => array(
					'readonly' => true,
				),
			))
			->add('mise_en_circulation', DateType::class, array(
				'label' => 'Date de première mise en circulation',
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
					'' => '',
					'Mise au rebus' => 'Mise au rebus',
					'Transfert vers site d’une filiale différente' => 'Transfert vers site d’une filiale différente',
					'Transfert vers site d’une même filiale' => 'Transfert vers site d’une même filiale',
				),
			))
			->add('commentaire_sortie', TextareaType::class, array('label' => 'Commentaire sortie'))
			->add('n_serie', TextType::class, array('label' => 'Numéro de série'))
			->add('immatriculation')
			->add('genre', ChoiceType::class, array(
				'label' => 'Genre (J1)',
				'choices' => array(
					'' => '',
					'Genre 1' => 'Genre 1',
                    'Genre 2' => 'Genre 2',
                    'Genre 3' => 'Genre 3',
				),
			))
			->add('ptac', IntegerType::class, array('label' => 'Ptac (F2)'))
            ->add('ptr', IntegerType::class, array('label' => 'Ptr (F3)'))
			->add('puissance_fiscale')
			
			->add('estSorti', CheckboxType::class, array(
				'label'    => 'Sortie Définitive',
				'required' => false,
			))
			->add('img', FileType::class, array(
				'label' => 'Carte grise',
				'data_class' => null,
			))
            ->add('validation', SubmitType::class, array('label' => 'Enregistrer'))
			;
	}

	public function configureOptions(OptionsResolver $resolver)
	{
		$resolver->setDefaults([
			'data_class' => Parcs::class,
		]);
	}
}
