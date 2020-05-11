<?php

namespace App\DataFixtures;

use App\Entity\Acheminements;
use App\Entity\Arrivage;
use App\Entity\Article;
use App\Entity\CategorieStatut;
use App\Entity\Collecte;
use App\Entity\Demande;
use App\Entity\Import;
use App\Entity\Livraison;
use App\Entity\MouvementStock;
use App\Entity\MouvementTraca;
use App\Entity\OrdreCollecte;
use App\Entity\Preparation;
use App\Entity\Reception;
use App\Entity\ReferenceArticle;
use App\Entity\Manutention;
use App\Entity\Statut;
use App\Entity\Wiilock;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class WiilockFixtures extends Fixture implements FixtureGroupInterface
{

    public function load(ObjectManager $manager)
    {
        $wiilockRepository = $manager->getRepository(Wiilock::class);

        $wiilocks = [
            Wiilock::DASHBOARD_FED_KEY => false
        ];

        foreach ($wiilocks as $key => $value) {
            $wiilock = $wiilockRepository->findOneBy([
                'lockKey' => $key
            ]);
            if (empty($wiilock)) {
                dump('Création du lock : ' . $key);
                $wiilock = new Wiilock();
                $wiilock
                    ->setValue($value)
                    ->setLockKey($key);
                $manager->persist($wiilock);
            }
        }
        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['wiilock', 'fixtures'];
    }
}
