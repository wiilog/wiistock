<?php

namespace App\DataFixtures;

use App\Repository\ReferenceArticleRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;


class PatchAlerteFixtures extends Fixture implements FixtureGroupInterface
{

    /**
     * @var ReferenceArticleRepository
     */
    private $referenceArticleRepository;

    public function __construct(ReferenceArticleRepository $referenceArticleRepository)
    {
        $this->referenceArticleRepository = $referenceArticleRepository;
    }

    public function load(ObjectManager $manager)
    {
        $refArts = $this->referenceArticleRepository->findAll();
        foreach ($refArts as $refArt)
        {
            $alerts = [
                $refArt->getLimitWarning(),
                $refArt->getLimitSecurity()
            ];

            $refArt
                ->setLimitWarning(max($alerts))
                ->setLimitSecurity(min($alerts));

        }
        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['patch-alerte'];
    }
}
