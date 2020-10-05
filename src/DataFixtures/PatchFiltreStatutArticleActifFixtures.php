<?php

namespace App\DataFixtures;

use App\Entity\FiltreRef;

use App\Entity\Utilisateur;
use App\Repository\FiltreRefRepository;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;


class PatchFiltreStatutArticleActifFixtures extends Fixture implements FixtureGroupInterface
{

    /**
     * @var FiltreRefRepository
     */
    private $filtreRefRepository;

    public function __construct(EntityManagerInterface $manager)
    {
        $this->filtreRefRepository = $manager->getRepository(FiltreRef::class);
    }

    public function load(ObjectManager $manager)
    {
        $utilisateurRepository = $manager->getRepository(Utilisateur::class);
        $listUser = $utilisateurRepository->findAll();
        foreach($listUser as $user){
            $filter = $this->filtreRefRepository->findOneByUserAndChampFixe($user, FiltreRef::CHAMP_FIXE_STATUT);
            if($filter == null){
                $newFilter = new FiltreRef();
                $newFilter
                    ->setUtilisateur($user)
                    ->setChampFixe(FiltreRef::CHAMP_FIXE_STATUT)
                    ->setValue('actif');

                $manager->persist($newFilter);
            }
        }
        $manager->flush();
    }

    public static function getGroups():array {
        return ['filtreStatut'];
    }
}
