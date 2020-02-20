<?php

namespace App\DataFixtures\Patchs;

use App\Entity\Emplacement;

use App\Repository\EmplacementRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\ChampLibreRepository;

use App\Repository\ValeurChampLibreRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;


class PatchRefArticleAdressageInEmplacementFixtures extends Fixture implements FixtureGroupInterface
{
    /**
     * @var ValeurChampLibreRepository
     */
    private $valeurChampLibreRepository;

    /**
     * @var ChampLibreRepository
     */
    private $champLibreRepository;

    /**
     * @var ReferenceArticleRepository
     */
    private $refArticleRepository;

    /**
     * @var EmplacementRepository
     */
    private $emplacementRepository;


    public function __construct(ValeurChampLibreRepository $valeurChampLibreRepository, EmplacementRepository $emplacementRepository, ChampLibreRepository $champLibreRepository, ReferenceArticleRepository $refArticleRepository)
    {
        $this->champLibreRepository = $champLibreRepository;
        $this->refArticleRepository = $refArticleRepository;
        $this->emplacementRepository = $emplacementRepository;
        $this->valeurChampLibreRepository = $valeurChampLibreRepository;
    }

    public function load(ObjectManager $manager)
    {
        $listAdresses = $this->valeurChampLibreRepository->getValeurAdresse();

		foreach($listAdresses as $adresse){
			if(!$this->emplacementRepository->findOneByLabel($adresse['valeur'])){
				$emplacement = new Emplacement();
				$emplacement
					->setLabel($adresse['valeur'])
					->setIsActive(true);
				$manager->persist($emplacement);
			}
		}

        $manager->flush();

        $allRefArticles = $this->refArticleRepository->findAll();

        foreach($allRefArticles as $refArticle){
            if(!$refArticle->getEmplacement()){
                $emplacement = $this->emplacementRepository->findOneByRefArticleWithChampLibreAdresse($refArticle);
                $refArticle->setEmplacement($emplacement);
            }
        }

        $this->champLibreRepository->deleteByLabel("'adresse'");
        $manager->flush();
    }

    public static function getGroups():array {
        return ['adressage'];
    }
}
