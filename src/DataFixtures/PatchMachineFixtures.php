<?php

namespace App\DataFixtures;

use App\Entity\ChampLibre;

use App\Entity\ValeurChampLibre;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;


class PatchMachineFixtures extends Fixture implements FixtureGroupInterface
{

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

        $champLibreRepository = $manager->getRepository(ChampLibre::class);
        $valeurChampLibreRepository = $manager->getRepository(ValeurChampLibre::class);

		$listElements = $champLibreRepository->getIdAndElementsWithMachine();

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
				$champsLibre = $champLibreRepository->find($elements['id']);
				$champsLibre->setElements(array_unique($elements['elements']));

				// remplace toutes les valeurs champs libre
				$listValeurChampsLibres = $valeurChampLibreRepository->findByCL($elements['id']);
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

        $champLibreRepository = $manager->getRepository(ChampLibre::class);

		$champMachine = $champLibreRepository->findOneByLabel('machine (PDT)%');
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
