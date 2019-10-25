<?php

namespace App\DataFixtures;

use App\Entity\ReferenceArticle;

use App\Repository\CategorieStatutRepository;
use App\Repository\StatutRepository;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;

use Doctrine\Common\Persistence\ObjectManager;


class PatchRenameStatutArtAndRef extends Fixture implements FixtureGroupInterface
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
        $statutActifRefs = $this->statutRepository->findOneByCategorieNameAndStatutName('referenceArticle', 'actif');
        $statutInactifRefs = $this->statutRepository->findOneByCategorieNameAndStatutName('referenceArticle', 'inactif');
        $statutActifArts = $this->statutRepository->findOneByCategorieNameAndStatutName('article', 'actif');
        $statutInactifArts = $this->statutRepository->findOneByCategorieNameAndStatutName('article', 'inactif');

        if (!empty($statutActifRefs)) {
            $statutActifRefs->setNom(ReferenceArticle::STATUT_ACTIF);
            dump('"renommage du statut ref / actif -> ' . ReferenceArticle::STATUT_ACTIF);
        }
        if (!empty($statutInactifRefs)) {
            $statutInactifRefs->setNom(ReferenceArticle::STATUT_INACTIF);
			dump('"renommage du statut ref / inactif -> ' . ReferenceArticle::STATUT_ACTIF);
		}
        if (!empty($statutActifArts)) {
            $statutActifArts->setNom(ReferenceArticle::STATUT_ACTIF);
			dump('"renommage du statut article / actif -> ' . ReferenceArticle::STATUT_ACTIF);
		}
        if (!empty($statutInactifArts)) {
            $statutInactifArts->setNom(ReferenceArticle::STATUT_INACTIF);
			dump('"renommage du statut article / inactif -> ' . ReferenceArticle::STATUT_ACTIF);
		}

        $manager->flush();
	}

	public static function getGroups():array {
		return ['patchStatuts'];
	}

}
