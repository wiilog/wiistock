<?php

namespace App\DataFixtures;

use App\Entity\ArticleFournisseur;
use App\Entity\Fournisseur;
use App\Entity\ReferenceArticle;
use App\Repository\ReferenceArticleRepository;
use App\Service\SpecificService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class PatchFournADef2Fixtures extends Fixture implements FixtureGroupInterface
{
    private $encoder;
    private $specificService;
    private $refArticleRepository;

    public function __construct(UserPasswordEncoderInterface $encoder,
                                SpecificService $specificService,
								ReferenceArticleRepository $referenceArticleRepository
	)
    {
        $this->encoder = $encoder;
        $this->specificService = $specificService;
        $this->refArticleRepository = $referenceArticleRepository;
    }

    public function load(ObjectManager $manager)
    {
		// on crée les articles fournisseurs et on les lie aux références
		$fournisseurADef = $manager->getRepository('App:Fournisseur')->findOneByCodeReference('A DEFINIR'); /** @var Fournisseur $fournisseurADef */

		$query = $manager->createQuery(
		/** @lang DQL */
			"SELECT ra 
			FROM App\Entity\ReferenceArticle ra
			LEFT JOIN ra.articlesFournisseur af
			WHERE af.id is null");
		/** @var ReferenceArticle[] $listRef */
		$listRef = $query->execute();

		foreach ($listRef as $ref) {
			$artFourn = new ArticleFournisseur();
			$artFourn
				->setLabel(trim($ref->getLibelle()) . ' / ' . trim($fournisseurADef->getNom()))
				->setReferenceArticle($ref)
				->setFournisseur($fournisseurADef)
				->setReference(trim($ref->getReference()) . ' / ' . trim($fournisseurADef->getCodeReference()));
			$manager->persist($artFourn);
		}
		$manager->flush();
	}

    public static function getGroups(): array
    {
        return ['articles-fournisseur-ref'];
    }
}
