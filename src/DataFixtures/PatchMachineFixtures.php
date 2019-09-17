<?php

namespace App\DataFixtures;

use App\Repository\ChampLibreRepository;
use App\Repository\ValeurChampLibreRepository;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;


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
        $path ="src/DataFixtures/Csv/machine.csv";
        $file = fopen($path, "r");

        $rows = [];
        while (($data = fgetcsv($file, 1000, ",")) !== false) {
            $rows[] = $data;
        }

        array_shift($rows); // supprime la 1è ligne d'en-têtes

        $listElements = $this->champLibreRepository->getIdAndElementsWithMachine();
//        foreach($listElements as $elements){
//            $i = 0;
//            while($i < count($rows)) {
//                $bool = false;
//                $j = 0;
//                while ($j < count($elements['elements'])) {
//                    if ($rows[$i][0] == $elements['elements'][$j] && $rows[$i][1] != '?') {
//                        $elements['elements'][$j] = $rows[$i][1];
//                        $bool = true;
//                        break;
//                    }
//                    $j++;
//                }
//                if(!$bool && $rows[$i][1] != '?'){
//                    array_push($elements['elements'] , $rows[$i][1]);
//                }
//                $i++;
//            }
//        }

        foreach($listElements as $elements){
            $colEyelit = $colMachines= [];
            foreach($rows as $row){
                    $colMachines [] = $row[0];
                if($row[1] != '?'){
                    $colEyelit [] = $row[1];
                } else {
                    $colEyelit [] = $row[0];
                }
            }
            $newElements = str_replace($colMachines, $colEyelit, $elements['elements']);
            $champsLibre = $this->champLibreRepository->find($elements['id']);
            $champsLibre->setElements(array_unique($newElements));

            //remplace toutes les valeurs champs libre
            $listValeurChampsLibres = $this->valeurChampLibreRepository->findByCL($elements['id']);
            foreach($listValeurChampsLibres as $valeurChampLibre){
                $newValeur = $colEyelit[array_search($valeurChampLibre->getValeur(), $colMachines)];
                $valeurChampLibre->setValeur($newValeur);
            }
        }
        $manager->flush();
        fclose($file);
    }

    public static function getGroups():array {
        return ['machine'];
    }
}
