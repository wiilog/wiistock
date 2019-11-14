<?php


namespace App\DataFixtures;


use App\Repository\CategorieCLRepository;
use App\Repository\ChampLibreRepository;
use App\Repository\EmplacementRepository;
use App\Repository\FournisseurRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\StatutRepository;
use App\Repository\TypeRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;


class SeuilsAlerteFixtures extends Fixture implements FixtureGroupInterface
{
    private $encoder;


    /**
     * @var TypeRepository
     */
    private $typeRepository;

    /**
     * @var ChampLibreRepository
     */
    private $champLibreRepository;

    /**
     * @var FournisseurRepository
     */
    private $fournisseurRepository;

    /**
     * @var StatutRepository
     */
    private $statutRepository;

    /**
     * @var ReferenceArticleRepository
     */
    private $refArticleRepository;

    /**
     * @var CategorieCLRepository
     */
    private $categorieCLRepository;

    /**
     * @var EmplacementRepository
     */
    private $emplacementRepository;

    public function __construct(EmplacementRepository $emplacementRepository, UserPasswordEncoderInterface $encoder, TypeRepository $typeRepository, ChampLibreRepository $champsLibreRepository, FournisseurRepository $fournisseurRepository, StatutRepository $statutRepository, ReferenceArticleRepository $refArticleRepository, CategorieCLRepository $categorieCLRepository)
    {
        $this->typeRepository = $typeRepository;
        $this->champLibreRepository = $champsLibreRepository;
        $this->encoder = $encoder;
        $this->fournisseurRepository = $fournisseurRepository;
        $this->statutRepository = $statutRepository;
        $this->refArticleRepository = $refArticleRepository;
        $this->categorieCLRepository = $categorieCLRepository;
        $this->emplacementRepository = $emplacementRepository;
    }

    public function load(ObjectManager $manager)
    {
        $allRefArticles = $this->refArticleRepository->findAll();

        $cpt = 0;

        foreach($allRefArticles as $refArticle){
            $valueAlerteSeuil = $this->refArticleRepository->getStockMiniClByRef($refArticle);
            $valueAlerteSecurity = $this->refArticleRepository->getStockAlerteClByRef($refArticle);

            if ($valueAlerteSecurity != null) {
                $refArticle->setLimitSecurity($valueAlerteSecurity[0]['valeur']);
            }
            if ($valueAlerteSeuil != null) {
                $refArticle->setLimitWarning($valueAlerteSeuil[0]['valeur']);
            }
            $cpt++;

            if ($cpt % 1000 === 0) {
                $manager->flush();
            }
        }
    }

    public static function getGroups():array {
        return ['seuilsAlerte'];
    }
}
