<?php

namespace App\DataFixtures;

use App\Entity\CategorieCL;

use App\Entity\CategoryType;
use App\Entity\ReferenceArticle;
use App\Repository\CategorieCLRepository;

use App\Repository\CategorieStatutRepository;
use App\Repository\CategoryTypeRepository;
use App\Repository\StatutRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;

use Doctrine\Common\Persistence\ObjectManager;


class RenameStatutArtEtRef extends Fixture implements FixtureGroupInterface
{
	/**
	 * @var StatutRepository
	 */
	private $statutRepository;

	/**
	 * @var CategorieStatutRepository
	 */
	private $categorieStatutRepository;


	public function __construct(CategorieStatutRepository $categorieStatutRepository, StatutRepository $statutRepository)
	{
		$this->statutRepository = $statutRepository;
		$this->categorieStatutRepository = $categorieStatutRepository;
	}

	public function load(ObjectManager $manager)
	{

	    $categorieStatutRef = $this->categorieStatutRepository->findOneBy([
	        'nom' => 'referenceArticle'
        ]);
        $categorieStatutArt = $this->categorieStatutRepository->findOneBy([
            'nom' => 'article'
        ]);
        $statutActifRefs = $this->statutRepository->findOneByCategorieAndStatut($categorieStatutRef, 'actif');
        $statutInactifRefs = $this->statutRepository->findOneByCategorieAndStatut($categorieStatutRef, 'inactif');
        $statutActifArts = $this->statutRepository->findOneByCategorieAndStatut($categorieStatutArt, 'actif');
        $statutInactifArts = $this->statutRepository->findOneByCategorieAndStatut($categorieStatutArt, 'inactif');

        if (!empty($statutActifRefs)) {
            $statutActifRefs->setNom(ReferenceArticle::STATUT_ACTIF);
        }
        if (!empty($statutInactifRefs)) {
            $statutInactifRefs->setNom(ReferenceArticle::STATUT_INACTIF);
        }
        if (!empty($statutActifArts)) {
            $statutActifArts->setNom(ReferenceArticle::STATUT_ACTIF);
        }
        if (!empty($statutInactifArts)) {
            $statutInactifArts->setNom(ReferenceArticle::STATUT_INACTIF);
        }
	}

	public static function getGroups():array {
		return ['patchStatuts'];
	}

}
