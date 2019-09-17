<?php

namespace App\DataFixtures;

use App\Controller\MouvementTracaController;
use App\Entity\Article;
use App\Entity\CategorieStatut;
use App\Entity\Collecte;
use App\Entity\Demande;
use App\Entity\Livraison;
use App\Entity\MouvementStock;
use App\Entity\MouvementTraca;
use App\Entity\OrdreCollecte;
use App\Entity\Preparation;
use App\Entity\Reception;
use App\Entity\ReferenceArticle;
use App\Entity\Manutention;
use App\Entity\Statut;
use App\Repository\StatutRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class StatutFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    private $encoder;

    /**
     * @var StatutRepository
     */
    private $statutRepository;


    public function __construct(StatutRepository $statutRepository, UserPasswordEncoderInterface $encoder)
    {
        $this->encoder = $encoder;
        $this->statutRepository = $statutRepository;
    }

    public function load(ObjectManager $manager)
    {
        // categorie referenceArticle
        $statutsNames = [
            ReferenceArticle::STATUT_ACTIF,
            ReferenceArticle::STATUT_INACTIF
        ];

        foreach ($statutsNames as $statutName) {
            $statut = $this->statutRepository->findOneByCategorieAndStatut(ReferenceArticle::CATEGORIE, $statutName);

            if (empty($statut)) {
                $statut = new Statut();
                $statut
                    ->setNom($statutName)
                    ->setCategorie($this->getReference('statut-referenceArticle'));
                $manager->persist($statut);
                dump("création du statut " . $statutName);
            }
        }

        // catégorie article
        $statutsNames = [
            Article::STATUT_ACTIF,
            Article::STATUT_INACTIF,
            Article::STATUT_EN_TRANSIT
        ];

        foreach ($statutsNames as $statutName) {
            $statut = $this->statutRepository->findOneByCategorieAndStatut(Article::CATEGORIE, $statutName);

            if (empty($statut)) {
                $statut = new Statut();
                $statut
                    ->setNom($statutName)
                    ->setCategorie($this->getReference('statut-article'));
                $manager->persist($statut);
                dump("création du statut " . $statutName);
            }
        }


        // catégorie demande de collecte
        $statutsNames = [
            Collecte::STATUS_BROUILLON,
            Collecte::STATUS_A_TRAITER,
            Collecte::STATUS_COLLECTE
        ];

        foreach ($statutsNames as $statutName) {
            $statut = $this->statutRepository->findOneByCategorieAndStatut(Collecte::CATEGORIE, $statutName);

            if (empty($statut)) {
                $statut = new Statut();
                $statut
                    ->setNom($statutName)
                    ->setCategorie($this->getReference('statut-collecte'));
                $manager->persist($statut);
                dump("création du statut " . $statutName);
            }
        }


        // catégorie ordre de collecte
        $statutsNames = [
            OrdreCollecte::STATUT_A_TRAITER,
            OrdreCollecte::STATUT_TRAITE
        ];

        foreach ($statutsNames as $statutName) {
            $statut = $this->statutRepository->findOneByCategorieAndStatut(OrdreCollecte::CATEGORIE, $statutName);

            if (empty($statut)) {
                $statut = new Statut();
                $statut
                    ->setNom($statutName)
                    ->setCategorie($this->getReference('statut-ordreCollecte'));
                $manager->persist($statut);
                dump("création du statut " . $statutName);
            }
        }


        // catégorie demande de livraison
        $statutsNames = [
            Demande::STATUT_BROUILLON,
            Demande::STATUT_A_TRAITER,
            Demande::STATUT_PREPARE,
            Demande::STATUT_LIVRE
        ];

        foreach ($statutsNames as $statutName) {
            $statut = $this->statutRepository->findOneByCategorieAndStatut(Demande::CATEGORIE, $statutName);

            if (empty($statut)) {
                $statut = new Statut();
                $statut
                    ->setNom($statutName)
                    ->setCategorie($this->getReference('statut-demande'));
                $manager->persist($statut);
                dump("création du statut " . $statutName);
            }
        }


        // catégorie livraison
        $statutsNames = [
            Livraison::STATUT_A_TRAITER,
            Livraison::STATUT_LIVRE
        ];

        foreach ($statutsNames as $statutName) {
            $statut = $this->statutRepository->findOneByCategorieAndStatut(Livraison::CATEGORIE, $statutName);

            if (empty($statut)) {
                $statut = new Statut();
                $statut
                    ->setNom($statutName)
                    ->setCategorie($this->getReference('statut-livraison'));
                $manager->persist($statut);
                dump("création du statut " . $statutName);
            }
        }


        // catégorie préparation
        $statutsNames = [
            Preparation::STATUT_A_TRAITER,
            Preparation::STATUT_EN_COURS_DE_PREPARATION,
            Preparation::STATUT_PREPARE
        ];

        foreach ($statutsNames as $statutName) {
            $statut = $this->statutRepository->findOneByCategorieAndStatut(Preparation::CATEGORIE, $statutName);

            if (empty($statut)) {
                $statut = new Statut();
                $statut
                    ->setNom($statutName)
                    ->setCategorie($this->getReference('statut-preparation'));
                $manager->persist($statut);
                dump("création du statut " . $statutName);
            }
        }

        $manager->flush();


        // catégorie réception
        $statutsNames = [
            Reception::STATUT_EN_ATTENTE,
            Reception::STATUT_RECEPTION_PARTIELLE,
            Reception::STATUT_RECEPTION_TOTALE,
            Reception::STATUT_ANOMALIE
        ];

        foreach ($statutsNames as $statutName) {
            $statut = $this->statutRepository->findOneByCategorieAndStatut(Reception::CATEGORIE, $statutName);

            if (empty($statut)) {
                $statut = new Statut();
                $statut
                    ->setNom($statutName)
                    ->setCategorie($this->getReference('statut-reception'));
                $manager->persist($statut);
                dump("création du statut " . $statutName);
            }
        }

        $manager->flush();


        // catégorie manutention
        $statutsNames = [
            Manutention::STATUT_A_TRAITER,
            Manutention::STATUT_TRAITE,
        ];

        foreach ($statutsNames as $statutName) {
            $statut = $this->statutRepository->findOneByCategorieAndStatut(Manutention::CATEGORIE, $statutName);

            if (empty($statut)) {
                $statut = new Statut();
                $statut
                    ->setNom($statutName)
                    ->setCategorie($this->getReference('statut-manutention'));
                $manager->persist($statut);
                dump("création du statut " . $statutName);
            }
        }

        $manager->flush();


        // catégorie mouvement traça
        $statutsNames = [
            MouvementTraca::TYPE_PRISE,
            MouvementTraca::TYPE_DEPOSE,
        ];

        foreach ($statutsNames as $statutName) {
            $statut = $this->statutRepository->findOneByCategorieAndStatut(CategorieStatut::MVT_TRACA, $statutName);

            if (empty($statut)) {
                $statut = new Statut();
                $statut
                    ->setNom($statutName)
                    ->setCategorie($this->getReference('statut-mouvement_traca'));
                $manager->persist($statut);
                dump("création du statut " . $statutName);
            }
        }

		// catégorie mouvement sock
		$statutsNames = [
			MouvementStock::TYPE_ENTREE,
			MouvementStock::TYPE_SORTIE,
			MouvementStock::TYPE_TRANSFERT,
		];

		foreach ($statutsNames as $statutName) {
			$statut = $this->statutRepository->findOneByCategorieAndStatut(CategorieStatut::MVT_STOCK, $statutName);

			if (empty($statut)) {
				$statut = new Statut();
				$statut
					->setNom($statutName)
					->setCategorie($this->getReference('statut-mouvement_stock'));
				$manager->persist($statut);
				dump("création du statut " . $statutName);
			}
		}

        $manager->flush();
    }

    public function getDependencies()
    {
        return [CategorieStatutFixtures::class];
    }

    public static function getGroups(): array
    {
        return ['status', 'fixtures'];
    }
}
