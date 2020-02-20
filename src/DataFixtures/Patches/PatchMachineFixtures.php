<?php

namespace App\DataFixtures;

use App\Repository\ChampLibreRepository;
use App\Repository\ValeurChampLibreRepository;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;


class PatchMachineFixtures extends Fixture implements FixtureGroupInterface
{

    /**
     * @var ChampLibreRepository
     */
    private $champLibreRepository;

    /**
     * @var ValeurChampLibreRepository
     */
    private $valeurChampLibreRepository;


    public function __construct(ChampLibreRepository $champLibreRepository, ValeurChampLibreRepository $valeurChampsLibreRepository)
    {
        $this->champLibreRepository = $champLibreRepository;
        $this->valeurChampLibreRepository = $valeurChampsLibreRepository;
    }

    public function load(ObjectManager $manager)
    {
        $this->updateMachines($manager);
        $this->addMachines($manager);
    }

    private function updateMachines(ObjectManager $manager)
	{
		$path ="src/DataFixtures/Csv/machine.csv";
		$file = fopen($path, "r");

		$rows = [];
		while (($data = fgetcsv($file, 1000, ";")) !== false) {
			$rows[] = $data;
		}

		array_shift($rows); // supprime la 1è ligne d'en-têtes

		$listElements = $this->champLibreRepository->getIdAndElementsWithMachine();

		$newValues = $formerValues = [];
		$formerToNew = [];
		foreach($rows as $row){
			$newValue = ($row[1] != '#N/A') ? $row[1] : $row[0];
			$newValueFormatted = str_replace(';', '/', $newValue);
			$formerToNew[$row[0]] = $newValueFormatted;
			$formerValues [] = $row[0];
			$newValues [] = $newValue;
		}

		foreach($listElements as $elements){
			if ($elements['elements']) {
				// modifie la liste des éléments du champ libre
				foreach ($elements['elements'] as &$item) {
					if (isset($formerToNew[$item])) {
						$item = $formerToNew[$item];
					}
				}
				$champsLibre = $this->champLibreRepository->find($elements['id']);
				$champsLibre->setElements(array_unique($elements['elements']));

				// remplace toutes les valeurs champs libre
				$listValeurChampsLibres = $this->valeurChampLibreRepository->findByCL($elements['id']);
				foreach($listValeurChampsLibres as $valeurChampLibre){
					if (isset($formerToNew[$valeurChampLibre->getValeur()])) {
						$valeurChampLibre->setValeur($formerToNew[$valeurChampLibre->getValeur()]);
					}
				}
			}
		}
		$manager->flush();
		fclose($file);
	}

	private function addMachines(ObjectManager $manager)
	{
		$path ="src/DataFixtures/Csv/machine_add.csv";
		$file = fopen($path, "r");

		$rows = [];
		while (($data = fgetcsv($file, 1000, ";")) !== false) {
			$rows[] = $data;
		}

		array_shift($rows); // supprime la 1è ligne d'en-têtes

		$champMachine = $this->champLibreRepository->findOneByLabel('machine (PDT)%');
		$elements = $champMachine->getElements();
		dump($elements);

		foreach ($rows as $row) {
			if (!in_array($row[0], $elements)) {
				$elements[] = $row[0];
			}
		}
		sort($elements);
		$champMachine->setElements($elements);

		$manager->flush();
		fclose($file);
	}

    public static function getGroups():array {
        return ['machine'];
    }
}
