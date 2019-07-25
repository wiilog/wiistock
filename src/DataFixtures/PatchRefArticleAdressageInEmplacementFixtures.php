<?php

namespace App\DataFixtures;

use App\Entity\Emplacement;

use App\Repository\EmplacementRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\ChampsLibreRepository;

use App\Repository\ValeurChampsLibreRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;
use function Symfony\Component\DependencyInjection\Loader\Configurator\ref;


class PatchRefArticleAdressageInEmplacementFixtures extends Fixture implements FixtureGroupInterface
{
    /**
     * @var ValeurChampsLibreRepository
     */
    private $valeurChampsLibreRepository;

    /**
     * @var ChampsLibreRepository
     */
    private $champsLibreRepository;

    /**
     * @var ReferenceArticleRepository
     */
    private $refArticleRepository;

    /**
     * @var EmplacementRepository
     */
    private $emplacementRepository;


    public function __construct(ValeurChampsLibreRepository $valeurChampsLibreRepository, EmplacementRepository $emplacementRepository, ChampsLibreRepository $champsLibreRepository, ReferenceArticleRepository $refArticleRepository)
    {
        $this->champsLibreRepository = $champsLibreRepository;
        $this->refArticleRepository = $refArticleRepository;
        $this->emplacementRepository = $emplacementRepository;
        $this->valeurChampsLibreRepository = $valeurChampsLibreRepository;
    }

    public function load(ObjectManager $manager)
    {
        $listAdresses = $this->valeurChampsLibreRepository->getValeurAdresse();

		foreach($listAdresses as $adresse){
			if(!$this->emplacementRepository->findOneByLabel($adresse['valeur'])){
				$emplacement = new Emplacement();
				$emplacement->setLabel($adresse['valeur']);
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

        $this->champsLibreRepository->deleteByLabel('adresse');
        $manager->flush();
    }

    public static function getGroups():array {
        return ['adressage'];
    }
}
