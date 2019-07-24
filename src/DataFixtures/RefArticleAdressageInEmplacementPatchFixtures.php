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


class RefArticleAdressageInEmplacementPatchFixtures extends Fixture implements FixtureGroupInterface
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
        $allRefArticles = $this->refArticleRepository->findAllRefArticles();

        $allRefArticles = array_slice($allRefArticles, 0, 3000);
        foreach($allRefArticles as $key => $refArticle){
            dump($key);
            if(!$refArticle->getEmplacement()){
                $emplacement = $this->valeurChampsLibreRepository->getEmplacementByRefArticle($refArticle);
                $refArticle->setEmplacement($emplacement);
            }
        }
        $manager->flush();
    }

    public static function getGroups():array {
        return ['adressage'];
    }
}
