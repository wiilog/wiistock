<?php

namespace App\DataFixtures;

use App\Entity\Article;

use App\Entity\Statut;
use App\Repository\CategorieStatutRepository;
use App\Repository\FiltreRefRepository;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;

use Doctrine\Common\Persistence\ObjectManager;


class PatchRenameStatutArt extends Fixture implements FixtureGroupInterface
{
	/**
	 * @var CategorieStatutRepository
	 */
	private $categorieStatutRepository;

	/**
	 * @var FiltreRefRepository
	 */
	private $filtreRefRepository;


	public function __construct(FiltreRefRepository $filtreRefRepository, CategorieStatutRepository $categorieStatutRepository)
	{
		$this->categorieStatutRepository = $categorieStatutRepository;
		$this->filtreRefRepository = $filtreRefRepository;
	}

	public function load(ObjectManager $manager)
	{
        $statutRepository = $manager->getRepository(Statut::class);

        $statutActifArts = $statutRepository->findOneByCategorieNameAndStatutCode('article', 'actif');
        $statutInactifArts = $statutRepository->findOneByCategorieNameAndStatutCode('article', 'inactif');

        if (!empty($statutActifArts)) {
            $statutActifArts->setNom(Article::STATUT_ACTIF);
			dump('"renommage du statut article / actif -> ' . Article::STATUT_ACTIF);
		}
        if (!empty($statutInactifArts)) {
            $statutInactifArts->setNom(Article::STATUT_INACTIF);
			dump('"renommage du statut article / inactif -> ' . Article::STATUT_ACTIF);
		}

//        $filtresRef = $this->filtreRefRepository->findBy(['champFixe' => 'Statut']);
//        $i = 0;
//        foreach ($filtresRef as $filtreRef) {
//        	if ($filtreRef->getValue() == 'actif') {
//        		$filtreRef->setValue(ReferenceArticle::STATUT_ACTIF);
//        		$i++;
//			} else if ($filtreRef->getValue() == 'inactif') {
//        		$filtreRef->setValue(ReferenceArticle::STATUT_INACTIF);
//        		$i++;
//			}
//		}
//        dump("renommage de " . $i ." filtres statuts sur les réf");

        $manager->flush();
	}

	public static function getGroups():array {
		return ['patchStatuts'];
	}

}
