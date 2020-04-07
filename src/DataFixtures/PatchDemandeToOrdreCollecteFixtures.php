<?php

namespace App\DataFixtures;

use App\Entity\OrdreCollecte;
use App\Entity\OrdreCollecteReference;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;


class PatchDemandeToOrdreCollecteFixtures extends Fixture implements FixtureGroupInterface
{

    public function load(ObjectManager $manager)
    {
        $ordreCollecteRepository = $manager->getRepository(OrdreCollecte::class);
		$ordresCollecte = $ordreCollecteRepository->findAll();

        foreach ($ordresCollecte as $ordreCollecte) {
        	$demandeCollecte = $ordreCollecte->getDemandeCollecte();

        	$articles = $demandeCollecte->getArticles();
        	foreach ($articles as $article) {
        		$ordreCollecte->addArticle($article);
			}

        	$collecteRefs = $demandeCollecte->getCollecteReferences();
        	foreach ($collecteRefs as $collecteRef) {
        		$ordreCollecteRef = new OrdreCollecteReference();
        		$ordreCollecteRef
					->setReferenceArticle($collecteRef->getReferenceArticle())
					->setOrdreCollecte($ordreCollecte)
					->setQuantite($collecteRef->getQuantite());
        		$manager->persist($ordreCollecteRef);
			}
        	$manager->flush();
		}
    }

    public static function getGroups():array {
        return ['collectes'];
    }
}
