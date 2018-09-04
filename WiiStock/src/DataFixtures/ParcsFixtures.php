<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;
use App\Entity\Sites;
use App\Entity\Filiales;
use App\Entity\SousCategoriesVehicules;
use App\Entity\CategoriesVehicules;
use App\Entity\Marques;

class ParcsFixtures extends Fixture
{
    public function load(ObjectManager $manager)
    {
    	
        // SITES
        $sites_obj = array();
        $sites = array(
        	'Bassens',

        	'CEA Cesta',
        	'Papeterie Emin leydier',
        	'CEA Saclay',
        	'GT santé',

        	'Angers',
        	'Cadeaux naissance',
        	'GGB Annecy',
        	'Nogent le Phaye',
        	'NTN Cran',
        	'NTN Saint Vulba',
        	'PSA Polaris',
        	'PSA Sochaux',
        	
        	'CEA Grenoble',
        	'Einea',
        	'GRT Gaz',
        	'Hutchinson',
        	'Imeca',
        	'Isover Chemillé',
        	'Lidl Rives',
        	'Papeterie Saint Miche',
        	'PSA Versailles',
        	'Safran Fougères',

        	'ArianeGroup Le Haillan',
        	'Arkema Feuchy',
        	'Arkema Jarrie',
        	'Arkema Mont',
        	'RTA Dunkerque',
        	'Safran Ceramics',
        	'Trimet',

        	'Smurfit',

        	'Continental Foix',
        	'Continental Toulouse',
        	'Poult',
        	'Prysmian',

        	'Ratier Figeac',

        	'PSA Vesoul',

        	'Figeac Aero',
        );

        foreach ($sites as $site) {
    		$si = new Sites();
    		$si->setNom($site);
    		array_push($sites_obj, $si);

    		$manager->persist($si);
    	}


    	// FILIALES
    	$filiales_obj = array();
    	$filiales = array(
    		'GT Logistics' => [0],
    		'GT Logistics 01' => [1,2,3,4],
    		'GT Logistics 02' => [5,6,7,8,9,10,11,12],
    		'GT Logistics 03' => [13,14,15,16,17,18,19,20,21,22],
    		'GT Logistics 04' => [23,24,25,26,27,28,29],
    		'GT Logistics 05' => [30],
    		'GT Logistics 06' => [31,32,33,34],
    		'GT Logistics 07' => [35],
    		'GT Logistics 08' => [36],
    		'Flexlog' => [37],
    	);

    	foreach ($filiales as $filiale => $value) {
    		$fi = new Filiales();
    		$fi->setNom($filiale);
    		foreach ($value as $v) {
    			$fi->addSite($sites_obj[$v]);
    		}
    		array_push($filiales_obj, $fi);

    		$manager->persist($fi);
    	}

    	// SOUS CATEGORIES VEHICULES
    	$sous_categs_obj = array();
    	$sous_categs = array(
    		'Chariot nacelle' => [4],
    		'Chariot rétractable' => [4],
    		'Gerbeur à conducteur accompagnant' => [4],
    		'Gerbeur à conducteur auto porté' => [4],
    		'Transpalette à conducteur accompagnant' => [4],
    		'Transpalette à conducteur auto porté' => [4],
    		'Préparateur de commande' => [4],
    		
    		'Chariot élévateur <2,5 T' => [4],
    		'Chariot élévateur 2,5<à<5 T' => [4],
    		'Chariot élévateur 5<à<9 T' => [4],
    		'Chariot élévateur 9<à<16 T' => [4],
    		'Chariot élévateur >16 T' => [4],

    		'Chargeuse' => [4],
    		'Chariot télescopique' => [4],
    		'Mini chargeuse' => [4],
    		'Mini pelle' => [4],
    		'Pelle' => [4],
    		'Nacelle' => [4],

    		'Balayeuse' => [4],
    		'Laveuse' => [4],

    		'Locotracteur' => [4],
    		'Terberg' => [4],
    		'Tracteur industriel' => [4],
    		'Tracteur routier' => [1],

    		'Citerne' => [2],
    		'Remorque industrielle' => [6],
    		'Remorque routière' => [2],
    		'Semi' => [2],

    		'Véhicule de fonction' => [9],
    		'Véhicule de service' => [8],
    		'Minibus' => [8],

    		'Porteur bache' => [3],
    		'Porteur benne' => [3],
    		'Porteur caisse volaille' => [3],
    		'Porteur frigo' => [3],
    		'Porteur plateau' => [3],
    		'Porteur fourgon' => [3],
    		'Semi plateau' => [3],
    		'Véhicule utilitaire' => [3],
    	);

    	foreach ($sous_categs as $sous_categ => $value) {
    		$sca = new SousCategoriesVehicules();
    		$sca->setNom($sous_categ);
    		$sca->setCode($value[0]);
    		array_push($sous_categs_obj, $sca);

    		$manager->persist($sca);
    	}


    	// CATEGORIES VEHICULES
    	$categs_veh_obj = array();
    	$categs = array(
    		'Engin de magasinage' => [0,1,2,3,4,5,6],
    		'Chariot frontal' => [7,8,9,10,11],
    		'Engin BTP' => [12,13,14,15,16,17],
    		'Engin Divers' => [18,19],
    		'Engin de traction' => [20,21,22,23],
    		'Remorque' => [24,25,26,27],
    		'Voiture' => [28,29,30],
    		'Camion et camionnette' => [31,32,33,34,35,36,37,38],
    	);

    	foreach ($categs as $categ => $value) {
    		$ca = new CategoriesVehicules();
    		$ca->setNom($categ);
    		foreach ($value as $v) {
    			$ca->addSousCategoriesVehicule($sous_categs_obj[$v]);
    		}
    		array_push($categs_veh_obj, $ca);

    		$manager->persist($ca);
    	}


    	// MARQUES
    	$marques_obj = array();
    	$marques = array(
    		'Fenwick',
    		'BT',
    		'Toyota',
    		'Manitou',
    		'Yale',
    		'Hyster',
    		'Terberg',
    		'Tennant',
    		'Jungheinrich',
    		'Caterpillar',
			'OMG',
			'Charlatte',
			'Crow',
			'Still',
			'Nilkfick',
			'Nissan',
			'John deere',
			'Miloco',
			'Daudin',
			'Jcb',
			'Bobcat',
			'kalmar',
			'mitsubitshi',
			'hanix',
			'zephir',
			'eurotract',
			'pales',
			'ligier',
			'masson',
			'peugeot',
			'citroen',
			'renault',
			'mercedes',
			'bmw',
			'audi',
			'trouillet',
			'lahitte',
			'micodan',
			'spitzer',
			'hermanns',
			'baryval',
			'man',
			'samro',
			'frejat',
			'iveco',
			'castera',
			'gruau',
			'elv',
    	);

    	foreach ($marques as $marque) {
    		$ma = new Marques();
    		$ma->setNom($marque);
    		array_push($marques_obj, $ma);

    		$manager->persist($ma);
    	}


        $manager->flush();
    }
}
