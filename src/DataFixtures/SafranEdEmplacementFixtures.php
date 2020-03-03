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
            $ecsArg = $emplacementRepository->findOneByLabel(SpecificService::ECS_ARG_LOCATION);
            if (!isset($ecsArg)) {
                $ecsArg = new Emplacement();
                $ecsArg
                    ->setIsActive(true)
                    ->setLabel(SpecificService::ECS_ARG_LOCATION);
                $manager->persist($ecsArg);
                $manager->flush();
            }
        }
    }

    public static function getGroups(): array {
        return ['fixtures'];
    }
}
