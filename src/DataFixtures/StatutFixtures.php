<?php

namespace App\DataFixtures;

use App\Entity\Arrivage;
use App\Entity\Article;
use App\Entity\CategorieStatut;
use App\Entity\Collecte;
use App\Entity\Demande;
use App\Entity\Litige;
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
use App\Repository\StatutRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class StatutFixtures extends Fixture implements FixtureGroupInterface
{
    private $encoder;

    /**
     * @var StatutRepository
     */
    private $statutRepository;

	/**
	 * @var CategorieStatutRepository
	 */
    private $categorieStatutRepository;


    public function __construct(CategorieStatutRepository $categorieStatutRepository, StatutRepository $statutRepository, UserPasswordEncoderInterface $encoder)
    {
        $this->encoder = $encoder;
        $this->statutRepository = $statutRepository;
        $this->categorieStatutRepository = $categorieStatutRepository;
    }

    public function load(ObjectManager $manager)
    {
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
				Collecte::STATUS_BROUILLON,
				Collecte::STATUS_A_TRAITER,
				Collecte::STATUS_COLLECTE
			],
			CategorieStatut::ORDRE_COLLECTE => [
				OrdreCollecte::STATUT_A_TRAITER,
				OrdreCollecte::STATUT_TRAITE
			],
			CategorieStatut::DEM_LIVRAISON => [
				Demande::STATUT_BROUILLON,
				Demande::STATUT_A_TRAITER,
				Demande::STATUT_PREPARE,
				Demande::STATUT_LIVRE
			],
			CategorieStatut::ORDRE_LIVRAISON => [
				Livraison::STATUT_A_TRAITER,
				Livraison::STATUT_LIVRE
			],
			CategorieStatut::PREPARATION => [
				Preparation::STATUT_A_TRAITER,
				Preparation::STATUT_EN_COURS_DE_PREPARATION,
				Preparation::STATUT_PREPARE
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
			],
			CategorieStatut::LITIGE_ARR => [
				[Litige::CONFORME, 1],
				[Litige::ATTENTE_ACHETEUR, 0],
				[Litige::TRAITE_ACHETEUR, 0],
				[Litige::SOLDE, 1],
			]
		];

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
			    if (is_array($statusLabel)) {
                    $this->persistStatus($categoryName, $statusLabel[0], $manager, $statusLabel[1]);
                }
			    else {
                    $this->persistStatus($categoryName, $statusLabel, $manager);
                }

			}
		}
        $manager->flush();
    }

    private function persistStatus($categoryName, $statusLabel, $manager, $treated = null): void {
        $statut = $this->statutRepository->findOneByCategorieNameAndStatutName($categoryName, $statusLabel);

        if (empty($statut)) {
            $statut = new Statut();
            $statut
                ->setNom($statusLabel)
                ->setCategorie($this->getReference('statut-' . $categoryName));

            $manager->persist($statut);
            dump("création du statut " . $statusLabel);
        }

        if (isset($treated)) {
            $statut->setTreated($treated);
        }
    }

    public static function getGroups(): array
    {
        return ['status', 'fixtures'];
    }
}
