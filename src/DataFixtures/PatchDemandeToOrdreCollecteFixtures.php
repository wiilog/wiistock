<?php

namespace App\DataFixtures;

use App\Entity\OrdreCollecteReference;
use App\Repository\CollecteRepository;
use App\Repository\OrdreCollecteRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Doctrine\ORM\EntityManagerInterface;


class PatchDemandeToOrdreCollecteFixtures extends Fixture implements FixtureGroupInterface
{
	/**
	 * @var CollecteRepository
	 */
	private $collecteRepository;

	/**
	 * @var OrdreCollecteRepository
	 */
	private $ordreCollecteRepository;

	/**
	 * @var EntityManagerInterface
	 */
	private $entityManager;


    public function __construct(
    	CollecteRepository $collecteRepository,
		OrdreCollecteRepository $ordreCollecteRepository,
		EntityManagerInterface $entityManager)
    {
    	$this->collecteRepository = $collecteRepository;
    	$this->ordreCollecteRepository = $ordreCollecteRepository;
    	$this->entityManager = $entityManager;
    }

    public function load(ObjectManager $manager)
    {
		$ordresCollecte = $this->ordreCollecteRepository->findAll();

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
