<?php

namespace App\DataFixtures\Patchs;

use App\Entity\ArticleFournisseur;
use App\Entity\Fournisseur;
use App\Repository\ReferenceArticleRepository;
use App\Service\SpecificService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class PatchFournADefFixtures extends Fixture implements FixtureGroupInterface
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
    	if (SpecificService::isCurrentClientNameFunction(SpecificService::CLIENT_COLLINS)) {

    		// on vide la base articles fournisseurs
			$queries = [
                $manager->createQuery("DELETE FROM App\Entity\MouvementStock mouvement"),
                $manager->createQuery("DELETE FROM App\Entity\Article article"),
                $manager->createQuery("DELETE FROM App\Entity\LigneArticle la"),
                $manager->createQuery("DELETE FROM App\Entity\LigneArticlePreparation lap"),
                $manager->createQuery("DELETE FROM App\Entity\Livraison livraison"),
                $manager->createQuery("DELETE FROM App\Entity\Preparation preparation"),
                $manager->createQuery("DELETE FROM App\Entity\Demande demande"),
			    $manager->createQuery("DELETE FROM App\Entity\ReceptionReferenceArticle rra"),
                $manager->createQuery("DELETE FROM App\Entity\Litige l"),
			    $manager->createQuery("DELETE FROM App\Entity\Reception r"),
                $manager->createQuery("DELETE FROM App\Entity\ArticleFournisseur af"),
                $manager->createQuery("DELETE FROM App\Entity\Colis c"),
                $manager->createQuery("DELETE FROM App\Entity\Arrivage arrivage"),
            ];

			foreach ($queries as $query) {
                $query->execute();
            }

			// on crée les articles fournisseurs et on les lie aux références
    		$fournisseurADef = new Fournisseur();

    		$fournisseurADef
				->setNom('A définir')
				->setCodeReference('A DEFINIR');
    		$manager->persist($fournisseurADef);
    		$manager->flush();

    		$listRef = $this->refArticleRepository->findAll();
    		foreach ($listRef as $ref) {
    			$artFourn = new ArticleFournisseur();
    			$artFourn
                    ->setLabel($ref->getLibelle() . ' / ' . $fournisseurADef->getNom())
					->setReferenceArticle($ref)
					->setFournisseur($fournisseurADef)
					->setReference($ref->getReference() . ' / ' . $fournisseurADef->getCodeReference());
    			$manager->persist($artFourn);
			}
    		$manager->flush();
		}
    }

    public static function getGroups(): array
    {
        return ['fournisseur-a-definir'];
    }
}
