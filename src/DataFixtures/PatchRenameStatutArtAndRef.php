<?php

namespace App\DataFixtures;

use App\Entity\Article;
use App\Entity\ReferenceArticle;

use App\Repository\CategorieStatutRepository;
use App\Repository\FiltreRefRepository;
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

	/**
	 * @var FiltreRefRepository
	 */
	private $filtreRefRepository;


	public function __construct(FiltreRefRepository $filtreRefRepository, CategorieStatutRepository $categorieStatutRepository, StatutRepository $statutRepository)
	{
		$this->statutRepository = $statutRepository;
		$this->categorieStatutRepository = $categorieStatutRepository;
		$this->filtreRefRepository = $filtreRefRepository;
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
			dump('"renommage du statut article / actif -> ' . Article::STATUT_ACTIF);
		}
        if (!empty($statutInactifArts)) {
            $statutInactifArts->setNom(ReferenceArticle::STATUT_INACTIF);
			dump('"renommage du statut article / inactif -> ' . Article::STATUT_ACTIF);
		}

        $filtresRef = $this->filtreRefRepository->findBy(['champFixe' => 'Statut']);
        $i = 0;
        foreach ($filtresRef as $filtreRef) {
        	if ($filtreRef->getValue() == 'actif') {
        		$filtreRef->setValue(ReferenceArticle::STATUT_ACTIF);
        		$i++;
			} else if ($filtreRef->getValue() == 'inactif') {
        		$filtreRef->setValue(ReferenceArticle::STATUT_INACTIF);
        		$i++;
			}
		}
        dump("renommage de " . $i ." filtres statuts sur les réf");

        $manager->flush();
	}

	public static function getGroups():array {
		return ['patchStatuts'];
	}

}
