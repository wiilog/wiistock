<?php


namespace App\DataFixtures;


use App\Entity\Emplacement;
use App\Service\SpecificService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\NonUniqueResultException;

class SafranEdEmplacementFixtures extends Fixture implements FixtureGroupInterface
{
    private $specificService;

    public function __construct(SpecificService $specificService) {
        $this->specificService = $specificService;
    }

    /**
     * @param ObjectManager $manager
     * @throws NonUniqueResultException
     */
    public function load(ObjectManager $manager)
    {
        if ($this->specificService->isCurrentClientNameFunction(SpecificService::CLIENT_SAFRAN_ED)) {
            $emplacementRepository = $manager->getRepository(Emplacement::class);
            $locationMvtDepose = $emplacementRepository->findOneByLabel(SpecificService::ARRIVAGE_SPECIFIQUE_SED_MVT_DEPOSE);
            if (!$locationMvtDepose) {
                dump('Création de l\'emplacement spécifique ' . SpecificService::ARRIVAGE_SPECIFIQUE_SED_MVT_DEPOSE);
                $locationMvtDepose = new Emplacement();
                $locationMvtDepose
                    ->setIsActive(true)
                    ->setLabel(SpecificService::ARRIVAGE_SPECIFIQUE_SED_MVT_DEPOSE);
                $manager->persist($locationMvtDepose);
                $manager->flush();
            }
        }
    }

    public static function getGroups(): array {
        return ['fixtures'];
    }
}
