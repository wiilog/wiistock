<?php

namespace App\DataFixtures;

use App\Entity\FiltreRef;

use App\Repository\UtilisateurRepository;
use App\Repository\FiltreRefRepository;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;


class PatchFiltreStatutArticleActifFixtures extends Fixture implements FixtureGroupInterface
{

    /**
     * @var UtilisateurRepository
     */
    private $utilisateurRepository;

    /**
     * @var FiltreRefRepository
     */
    private $filtreRefRepository;

    public function __construct(UtilisateurRepository $utilisateurRepository, FiltreRefRepository $filtreRefRepository)
    {
        $this->utilisateurRepository = $utilisateurRepository;
        $this->filtreRefRepository = $filtreRefRepository;
    }

    public function load(ObjectManager $manager)
    {
        $listUser = $this->utilisateurRepository->findAll();
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
