<?php

namespace App\DataFixtures\Patchs;

use App\Entity\ChampLibre;
use App\Repository\ChampLibreRepository;
use App\Repository\TypeRepository;
use App\Repository\ValeurChampLibreRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;


class PatchOneTwoOne extends Fixture implements FixtureGroupInterface
{
	private $encoder;

	/**
	 * @var ChampLibreRepository
	 */
	private $champLibreRepository;

	/**
	 * @var ValeurChampLibreRepository
	 */
	private $valeurChampLibreRepository;

	/**
	 * @var TypeRepository
	 */
	private $typeRepository;


	public function __construct(TypeRepository $typeRepository, ValeurChampLibreRepository $valeurChampLibreRepository, ChampLibreRepository $champLibreRepository, UserPasswordEncoderInterface $encoder)
	{
		$this->encoder = $encoder;
		$this->champLibreRepository = $champLibreRepository;
		$this->valeurChampLibreRepository = $valeurChampLibreRepository;
		$this->typeRepository = $typeRepository;
	}

	public function load(ObjectManager $manager)
	{
		// patch spécifique pour modifier champs libres Machine (PDT) et Zone (type text-> type list)
		$labels = ['Machine (PDT)', 'Zone (PDT)'];

		foreach ($labels as $label) {

			$champLibre = $this->champLibreRepository->findOneBy(['label' => $label]);
			if (!$champLibre) {
				dump('champ libre ' . $label . ' non trouvé en base');
				return;
			}

			$champLibre->setTypage(ChampLibre::TYPE_LIST);

			$valeursChampsLibresMachine = $this->valeurChampLibreRepository->findBy(['champLibre' => $champLibre->getId()]);
			$elements = [];
			foreach ($valeursChampsLibresMachine as $valeurChampLibre) {
				$valeur = $valeurChampLibre->getValeur();
				if (!empty($valeur) && !in_array($valeur, $elements)) {
					$elements[] = $valeur;
				}
			}
			sort($elements);
			$champLibre->setElements($elements);
		}

		$manager->flush();
	}

	public static function getGroups():array {
		return ['1.2.1'];
	}

}
