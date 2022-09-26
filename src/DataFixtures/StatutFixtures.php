<?php

namespace App\DataFixtures;

use App\Entity\Article;
use App\Entity\CategorieStatut;
use App\Entity\Collecte;
use App\Entity\DeliveryRequest\Demande;
use App\Entity\Export;
use App\Entity\Import;
use App\Entity\Livraison;
use App\Entity\MouvementStock;
use App\Entity\PurchaseRequest;
use App\Entity\TrackingMovement;
use App\Entity\OrdreCollecte;
use App\Entity\PreparationOrder\Preparation;
use App\Entity\Reception;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\TransferOrder;
use App\Entity\TransferRequest;
use App\Entity\Transport\TransportOrder;
use App\Entity\Transport\TransportRequest;
use App\Entity\Transport\TransportRound;
use App\Repository\StatutRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Console\Output\ConsoleOutput;

class StatutFixtures extends Fixture implements FixtureGroupInterface
{

    public function load(ObjectManager $manager)
    {
        $output = new ConsoleOutput();

        $statutRepository = $manager->getRepository(Statut::class);
        $categorieStatutRepository = $manager->getRepository(CategorieStatut::class);

        $statefulCategories = [
            CategorieStatut::REFERENCE_ARTICLE,
            CategorieStatut::DEM_COLLECTE,
            CategorieStatut::ORDRE_COLLECTE,
            CategorieStatut::DEM_LIVRAISON,
            CategorieStatut::ORDRE_LIVRAISON,
            CategorieStatut::PREPARATION,
            CategorieStatut::RECEPTION,
            CategorieStatut::TRANSFER_REQUEST,
            CategorieStatut::TRANSFER_ORDER,
            CategorieStatut::IMPORT,
            CategorieStatut::PURCHASE_REQUEST,
        ];

        $categoriesStatus = [
    		CategorieStatut::REFERENCE_ARTICLE => [
    			ReferenceArticle::STATUT_ACTIF => null,
				ReferenceArticle::STATUT_INACTIF => null,
                ReferenceArticle::DRAFT_STATUS => Statut::DRAFT,
			],
			CategorieStatut::ARTICLE => [
				Article::STATUT_ACTIF,
				Article::STATUT_INACTIF,
				Article::STATUT_EN_TRANSIT,
				Article::STATUT_EN_LITIGE
			],
			CategorieStatut::DEM_COLLECTE => [
				Collecte::STATUT_BROUILLON => Statut::DRAFT,
				Collecte::STATUT_A_TRAITER => Statut::NOT_TREATED,
				Collecte::STATUT_COLLECTE => Statut::TREATED,
				Collecte::STATUT_INCOMPLETE => Statut::PARTIAL
			],
			CategorieStatut::ORDRE_COLLECTE => [
				OrdreCollecte::STATUT_A_TRAITER => Statut::NOT_TREATED,
				OrdreCollecte::STATUT_TRAITE => Statut::TREATED
			],
			CategorieStatut::DEM_LIVRAISON => [
				Demande::STATUT_BROUILLON => Statut::DRAFT,
				Demande::STATUT_A_TRAITER => Statut::NOT_TREATED,
				Demande::STATUT_PREPARE => Statut::PARTIAL,
                Demande::STATUT_INCOMPLETE => Statut::PARTIAL,
                Demande::STATUT_LIVRE_INCOMPLETE => Statut::PARTIAL,
                Demande::STATUT_LIVRE => Statut::TREATED
			],
			CategorieStatut::ORDRE_LIVRAISON => [
				Livraison::STATUT_A_TRAITER => Statut::NOT_TREATED,
				Livraison::STATUT_LIVRE => Statut::TREATED,
                Livraison::STATUT_INCOMPLETE => Statut::TREATED
            ],
			CategorieStatut::PREPARATION => [
				Preparation::STATUT_A_TRAITER => Statut::NOT_TREATED,
				Preparation::STATUT_EN_COURS_DE_PREPARATION => Statut::NOT_TREATED,
				Preparation::STATUT_PREPARE => Statut::TREATED,
                Preparation::STATUT_INCOMPLETE => Statut::TREATED,
                Preparation::STATUT_VALIDATED => Statut::NOT_TREATED,
			],
			CategorieStatut::RECEPTION => [
				Reception::STATUT_EN_ATTENTE => Statut::NOT_TREATED,
				Reception::STATUT_RECEPTION_PARTIELLE => Statut::TREATED,
				Reception::STATUT_RECEPTION_TOTALE => Statut::TREATED,
				Reception::STATUT_ANOMALIE => Statut::DISPUTE
			],
			CategorieStatut::MVT_TRACA => [
				TrackingMovement::TYPE_PRISE,
                TrackingMovement::TYPE_DEPOSE,
                TrackingMovement::TYPE_PRISE_DEPOSE,
                TrackingMovement::TYPE_GROUP,
                TrackingMovement::TYPE_UNGROUP,
                TrackingMovement::TYPE_EMPTY_ROUND,
			],
			CategorieStatut::MVT_STOCK => [
				MouvementStock::TYPE_ENTREE,
				MouvementStock::TYPE_SORTIE,
				MouvementStock::TYPE_TRANSFER,
				MouvementStock::TYPE_INVENTAIRE_ENTREE,
				MouvementStock::TYPE_INVENTAIRE_SORTIE,
			],
			CategorieStatut::ARRIVAGE => [],
            CategorieStatut::DISPUTE_ARR => [],
            CategorieStatut::LITIGE_RECEPT => [],
            CategorieStatut::DISPATCH => [],
            CategorieStatut::HANDLING => [],
            CategorieStatut::TRANSFER_REQUEST => [
                TransferRequest::DRAFT => Statut::DRAFT,
                TransferRequest::TO_TREAT => Statut::NOT_TREATED,
                TransferRequest::TREATED => Statut::TREATED,
            ],
            CategorieStatut::TRANSFER_ORDER => [
                TransferOrder::TO_TREAT => Statut::NOT_TREATED,
                TransferOrder::TREATED => Statut::TREATED,
            ],
            CategorieStatut::PURCHASE_REQUEST => [
                PurchaseRequest::DRAFT => Statut::DRAFT,
                PurchaseRequest::NOT_TREATED => Statut::NOT_TREATED,
            ],
			CategorieStatut::IMPORT => [
				Import::STATUS_PLANNED => Statut::NOT_TREATED,
				Import::STATUS_FINISHED => Statut::TREATED,
				Import::STATUS_IN_PROGRESS => Statut::NOT_TREATED,
				Import::STATUS_CANCELLED => Statut::NOT_TREATED,
				Import::STATUS_DRAFT => Statut::DRAFT
            ],
            CategorieStatut::EXPORT => [
                Export::STATUS_ERROR,
                Export::STATUS_FINISHED,
                Export::STATUS_SCHEDULED,
                Export::STATUS_CANCELLED,
            ],
            CategorieStatut::TRANSPORT_REQUEST_DELIVERY => [
                TransportRequest::STATUS_AWAITING_VALIDATION,
                TransportRequest::STATUS_TO_PREPARE,
                TransportRequest::STATUS_TO_DELIVER,
                TransportRequest::STATUS_ONGOING,
                TransportRequest::STATUS_FINISHED,
                TransportRequest::STATUS_CANCELLED,
                TransportRequest::STATUS_NOT_DELIVERED,
                TransportRequest::STATUS_SUBCONTRACTED,
            ],
            CategorieStatut::TRANSPORT_REQUEST_COLLECT => [
                TransportRequest::STATUS_AWAITING_VALIDATION,
                TransportRequest::STATUS_AWAITING_PLANNING,
                TransportRequest::STATUS_TO_COLLECT,
                TransportRequest::STATUS_ONGOING,
                TransportRequest::STATUS_FINISHED,
                TransportRequest::STATUS_DEPOSITED,
                TransportRequest::STATUS_CANCELLED,
                TransportRequest::STATUS_NOT_COLLECTED,
            ],
            CategorieStatut::TRANSPORT_ORDER_DELIVERY => [
                TransportOrder::STATUS_TO_ASSIGN,
                TransportOrder::STATUS_ASSIGNED,
                TransportOrder::STATUS_ONGOING,
                TransportOrder::STATUS_FINISHED,
                TransportOrder::STATUS_CANCELLED,
                TransportOrder::STATUS_NOT_DELIVERED,
                TransportOrder::STATUS_SUBCONTRACTED,
                TransportOrder::STATUS_AWAITING_VALIDATION,
            ],
            CategorieStatut::TRANSPORT_ORDER_COLLECT => [
                TransportOrder::STATUS_TO_CONTACT,
                TransportOrder::STATUS_TO_ASSIGN,
                TransportOrder::STATUS_ASSIGNED,
                TransportOrder::STATUS_ONGOING,
                TransportOrder::STATUS_FINISHED,
                TransportOrder::STATUS_CANCELLED,
                TransportOrder::STATUS_NOT_COLLECTED,
                TransportOrder::STATUS_AWAITING_VALIDATION,
                TransportOrder::STATUS_DEPOSITED,
            ],
            CategorieStatut::TRANSPORT_ROUND => [
                TransportRound::STATUS_AWAITING_DELIVERER,
                TransportRound::STATUS_ONGOING,
                TransportRound::STATUS_FINISHED,
            ],
        ];

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

			if (in_array($categoryName, $statefulCategories)) {
                foreach ($statuses as $statusLabel => $state) {
                    $this->treatStatus($statutRepository, $manager, $categoryName, $statusLabel, $output, $state);
			    }
            }
			else {
                foreach ($statuses as $statusLabel) {
                    $this->treatStatus($statutRepository, $manager, $categoryName, $statusLabel, $output);
			    }
            }
		}
        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['status', 'fixtures'];
    }

    private function treatStatus(StatutRepository $statutRepository,
                                 ObjectManager $manager,
                                 string $categoryName,
                                 string $statusLabel,
                                 ConsoleOutput $output,
                                 $state = null) {

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

        $statut->setState($state);
    }
}
