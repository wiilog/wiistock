<?php

namespace App\DataFixtures;

use App\Entity\Filter;

use App\Repository\UtilisateurRepository;
use App\Repository\FilterRepository;

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
     * @var FilterRepository
     */
    private $filterRepository;

    public function __construct(UtilisateurRepository $utilisateurRepository, FilterRepository $filterRepository)
    {
        $this->utilisateurRepository = $utilisateurRepository;
        $this->filterRepository = $filterRepository;
    }

    public function load(ObjectManager $manager)
    {
        $listUser = $this->utilisateurRepository->findAll();
        foreach($listUser as $user){
            $filter = $this->filterRepository->findByUserAndChampFixe($user, Filter::CHAMP_FIXE_STATUT);
            if($filter == null){
                $newFilter = new Filter ();
                $newFilter
                    ->setUtilisateur($user)
                    ->setChampFixe(Filter::CHAMP_FIXE_STATUT)
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
