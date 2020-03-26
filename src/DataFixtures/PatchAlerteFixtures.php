<?php

namespace App\DataFixtures;

use App\Entity\ReferenceArticle;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;


class PatchAlerteFixtures extends Fixture implements FixtureGroupInterface
{

    public function load(ObjectManager $manager)
    {
        $referenceArticleRepository = $manager->getRepository(ReferenceArticle::class);

        $refArts = $referenceArticleRepository->findAll();
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
