<?php

namespace App\DataFixtures;

use App\Entity\Article;
use App\Entity\CategorieStatut;
use App\Entity\Collecte;
use App\Entity\Demande;
use App\Entity\Import;
use App\Entity\Livraison;
use App\Entity\MouvementStock;
use App\Entity\TrackingMovement;
use App\Entity\OrdreCollecte;
use App\Entity\Preparation;
use App\Entity\Reception;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\TransferOrder;
use App\Entity\TransferRequest;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class StatutFixtures extends Fixture implements FixtureGroupInterface
{
    private $encoder;
    private $entityManager;


    public function __construct(EntityManagerInterface $entityManager,
                                UserPasswordEncoderInterface $encoder)
    {
        $this->encoder = $encoder;
        $this->entityManager = $entityManager;
    }

    public function load(ObjectManager $manager)
    {
        $output = new ConsoleOutput();

        $statutRepository = $manager->getRepository(Statut::class);
        $categorieStatutRepository = $manager->getRepository(CategorieStatut::class);

        $categoriesStatus = [
    		CategorieStatut::REFERENCE_ARTICLE => [
    			ReferenceArticle::STATUT_ACTIF,
				ReferenceArticle::STATUT_INACTIF
			],
			CategorieStatut::ARTICLE => [
				Article::STATUT_ACTIF,
				Article::STATUT_INACTIF,
				Article::STATUT_EN_TRANSIT,
				Article::STATUT_EN_LITIGE
			],
			CategorieStatut::DEM_COLLECTE => [
				Collecte::STATUT_BROUILLON,
				Collecte::STATUT_A_TRAITER,
				Collecte::STATUT_COLLECTE,
				Collecte::STATUT_INCOMPLETE
			],
			CategorieStatut::ORDRE_COLLECTE => [
				OrdreCollecte::STATUT_A_TRAITER,
				OrdreCollecte::STATUT_TRAITE
			],
			CategorieStatut::DEM_LIVRAISON => [
				Demande::STATUT_BROUILLON,
				Demande::STATUT_A_TRAITER,
				Demande::STATUT_PREPARE,
				Demande::STATUT_LIVRE,
				Demande::STATUT_INCOMPLETE,
                Demande::STATUT_LIVRE_INCOMPLETE
			],
			CategorieStatut::ORDRE_LIVRAISON => [
				Livraison::STATUT_A_TRAITER,
				Livraison::STATUT_LIVRE,
                Livraison::STATUT_INCOMPLETE
            ],
			CategorieStatut::PREPARATION => [
				Preparation::STATUT_A_TRAITER,
				Preparation::STATUT_EN_COURS_DE_PREPARATION,
				Preparation::STATUT_PREPARE,
				Preparation::STATUT_INCOMPLETE
			],
			CategorieStatut::RECEPTION => [
				Reception::STATUT_EN_ATTENTE,
				Reception::STATUT_RECEPTION_PARTIELLE,
				Reception::STATUT_RECEPTION_TOTALE,
				Reception::STATUT_ANOMALIE
			],
			CategorieStatut::MVT_TRACA => [
				TrackingMovement::TYPE_PRISE,
                TrackingMovement::TYPE_DEPOSE,
                TrackingMovement::TYPE_PRISE_DEPOSE,
			],
			CategorieStatut::MVT_STOCK => [
				MouvementStock::TYPE_ENTREE,
				MouvementStock::TYPE_SORTIE,
				MouvementStock::TYPE_TRANSFERT,
				MouvementStock::TYPE_INVENTAIRE_ENTREE,
				MouvementStock::TYPE_INVENTAIRE_SORTIE,
			],
			CategorieStatut::ARRIVAGE => [],
            CategorieStatut::LITIGE_ARR => [],
            CategorieStatut::LITIGE_RECEPT => [],
            CategorieStatut::DISPATCH => [],
            CategorieStatut::TRANSFER_REQUEST => [
                TransferRequest::DRAFT,
                TransferRequest::TO_TREAT,
                TransferRequest::TREATED,
            ],
            CategorieStatut::TRANSFER_ORDER => [
                TransferOrder::DRAFT,
                TransferOrder::TO_TREAT,
                TransferOrder::TREATED,
            ],
			CategorieStatut::IMPORT => [
				Import::STATUS_PLANNED,
				Import::STATUS_FINISHED,
				Import::STATUS_IN_PROGRESS,
				Import::STATUS_CANCELLED,
				Import::STATUS_DRAFT,
			]
        ];

        // on supprime les anciens statut d'arrivage qui ne sont pas dans le tableau
        /** @var Statut[] $statutsASupprimer */
        $statutsASupprimer = $statutRepository->createQueryBuilder('statut')
            ->distinct()
            ->innerJoin('statut.categorie', 'categorie')
            ->andWhere('categorie.nom = :categorieArrivage')
            ->andWhere('statut.nom NOT IN (:statutsArrivage)')
            ->setParameter('statutsArrivage', $categoriesStatus[CategorieStatut::ARRIVAGE], Connection::PARAM_STR_ARRAY)
            ->setParameter('categorieArrivage', CategorieStatut::ARRIVAGE)
            ->getQuery()
            ->getResult();

        foreach ($statutsASupprimer as $statutASupprimer) {
            $manager->remove($statutASupprimer);
            $output->writeln("Suppression du statut \"" . $statutASupprimer->getNom() . "\" de la catégorie \"" . CategorieStatut::ARRIVAGE . "\"");
        }

    	foreach ($categoriesStatus as $categoryName => $statuses) {

    		// création des catégories de statuts
			$categorie = $categorieStatutRepository->findOneBy(['nom' => $categoryName]);

			if (empty($categorie)) {
				$categorie = new CategorieStatut();
				$categorie->setNom($categoryName);
				$manager->persist($categorie);
				$output->writeln("Création de la catégorie \"" . $categoryName . "\"");
			}
			$this->addReference('statut-' . $categoryName, $categorie);

			// création des statuts
			foreach ($statuses as $statusLabel) {
				$statut = $statutRepository->findOneByCategorieNameAndStatutCode($categoryName, $statusLabel);

				if (empty($statut)) {
					$statut = new Statut();
					$statut
						->setNom($statusLabel)
						->setCode($statusLabel)
						->setCategorie($this->getReference('statut-' . $categoryName));
					$manager->persist($statut);
					$output->writeln("Création du statut \"" . $statusLabel . "\" dans la catégorie \"" . $statut->getCategorie()->getNom() . "\"");
				}
			}
		}
        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['status', 'fixtures'];
    }
}
