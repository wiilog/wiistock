<?php

namespace App\DataFixtures;

use App\Entity\Acheminements;
use App\Entity\Arrivage;
use App\Entity\Article;
use App\Entity\CategorieStatut;
use App\Entity\Collecte;
use App\Entity\Demande;
use App\Entity\Import;
use App\Entity\Livraison;
use App\Entity\MouvementStock;
use App\Entity\MouvementTraca;
use App\Entity\OrdreCollecte;
use App\Entity\Preparation;
use App\Entity\Reception;
use App\Entity\ReferenceArticle;
use App\Entity\Manutention;
use App\Entity\Statut;
use App\Repository\CategorieStatutRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\DBAL\Connection;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class StatutFixtures extends Fixture implements FixtureGroupInterface
{
    private $encoder;

	/**
	 * @var CategorieStatutRepository
	 */
    private $categorieStatutRepository;


    public function __construct(CategorieStatutRepository $categorieStatutRepository, UserPasswordEncoderInterface $encoder)
    {
        $this->encoder = $encoder;
        $this->categorieStatutRepository = $categorieStatutRepository;
    }

    public function load(ObjectManager $manager)
    {
        $statutRepository = $manager->getRepository(Statut::class);

        $categoriesStatus = [
    		CategorieStatut::REFERENCE_ARTICLE => [
    			ReferenceArticle::STATUT_ACTIF,
				ReferenceArticle::STATUT_INACTIF
			],
			CategorieStatut::ARTICLE => [
				Article::STATUT_ACTIF,
				Article::STATUT_INACTIF,
				Article::STATUT_EN_TRANSIT
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
			CategorieStatut::MANUTENTION => [
				Manutention::STATUT_A_TRAITER,
				Manutention::STATUT_TRAITE,
			],
			CategorieStatut::MVT_TRACA => [
				MouvementTraca::TYPE_PRISE,
                MouvementTraca::TYPE_DEPOSE,
                MouvementTraca::TYPE_PRISE_DEPOSE,
			],
			CategorieStatut::MVT_STOCK => [
				MouvementStock::TYPE_ENTREE,
				MouvementStock::TYPE_SORTIE,
				MouvementStock::TYPE_TRANSFERT,
				MouvementStock::TYPE_INVENTAIRE_ENTREE,
				MouvementStock::TYPE_INVENTAIRE_SORTIE,
			],
			CategorieStatut::ARRIVAGE => [
				Arrivage::STATUS_CONFORME,
                Arrivage::STATUS_LITIGE,
                Arrivage::STATUS_RESERVE,
			],
            CategorieStatut::LITIGE_ARR => [],
            CategorieStatut::LITIGE_RECEPT => [],
            CategorieStatut::ACHEMINEMENT => [
                Acheminements::STATUT_A_TRAITER,
                Acheminements::STATUT_TRAITE,
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
            dump("suppression du statut " . $statutASupprimer->getNom() . ' (catégorie ' . CategorieStatut::ARRIVAGE . ')');
        }

    	foreach ($categoriesStatus as $categoryName => $statuses) {

    		// création des catégories de statuts
			$categorie = $this->categorieStatutRepository->findOneBy(['nom' => $categoryName]);

			if (empty($categorie)) {
				$categorie = new CategorieStatut();
				$categorie->setNom($categoryName);
				$manager->persist($categorie);
				dump("création de la catégorie " . $categoryName);
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
					dump("création du statut " . $statusLabel);
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
