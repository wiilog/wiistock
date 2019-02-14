<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use App\Entity\Sites;
use App\Entity\Filiales;
use App\Entity\SousCategoriesVehicules;
use App\Entity\CategoriesVehicules;
use App\Entity\Marques;
use App\Entity\Utilisateurs;

class ParcsFixtures extends Fixture
{
    private $encoder;

    public function __construct(UserPasswordEncoderInterface $encoder)
    {
        $this->encoder = $encoder;
    }

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
			'Euthanasie Volailles',

        	'Angers',
        	'Cadeaux naissance',
        	'GGB Annecy',
        	'Nogent le Phaye',
        	'NTN Cran Gevrier',
        	'NTN Saint Vulbas',
        	'PSA Polaris',
        	'PSA Sochaux',
        	
        	'CEA Grenoble',
        	'Einea',
        	'GRT Gaz',
        	'Hutchinson',
        	'Imeca',
        	'Isover Chemillé',
        	'Lidl Rives',
        	'PSM Thiollet',
        	'PSA Versailles',
			'Safran SED Fougères',
			'AIA',

        	'ArianeGroup Le Haillan',
        	'CECA Feuchy',
        	'Arkema Jarrie',
        	'Arkema Mont',
        	'RTA Dunkerque',
        	'Safran Ceramics',
			'Trimet',
			'Blue Star',

        	'Smurfit',

        	'Continental Foix',
        	'Continental Toulouse',
        	'Poult',
        	'Prysmian',

        	'Ratier-Figeac',

			'PSA Vesoul Condi',
			'PSA Vesoul Pneu',

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
    		'GT Logistics.01' => [1,2,3,4,5],
    		'GT Logistics.02' => [6,7,8,9,10,11,12,13],
    		'GT Logistics.03' => [14,15,16,17,18,19,20,21,22,23,24],
    		'GT Logistics.04' => [25,26,27,28,29,30,31,32],
    		'GT Logistics.05' => [33],
    		'GT Logistics.06' => [34,35,36,37],
    		'GT Logistics.07' => [38],
    		'GT Logistics.08' => [39,40],
    		'Flexlog' => [41],
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
    		'Gerbeur à conducteur autoporté' => [4],
    		'Transpalette à conducteur accompagnant' => [4],
    		'Transpalette à conducteur autoporté' => [4],
    		'Préparateur de commande' => [4],
			'Chariot autonome' => [4],
			'Auto palet mover' => [4],
    		
    		'Chariot élévateur <2.5T' => [4],
    		'Chariot élévateur 2.5T<à<5T' => [4],
    		'Chariot élévateur 5T<à<=9T' => [4],
    		'Chariot élévateur 9T<à<16T' => [4],
    		'Chariot élévateur >16T' => [4],

    		'Chargeuse' => [4],
    		'Chariot télescopique' => [4],
    		'Mini-chargeuse' => [4],
    		'Mini-pelle' => [4],
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
    		'Semi sans plateau' => [2],
            'Caisse mobile' => [5],

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
    		'Engin de magasinage' => [0,1,2,3,4,5,6,7,8],
    		'Chariot frontal' => [9,10,11,12,13],
    		'Engin BTP' => [14,15,16,17,18,19],
    		'Engin divers' => [20,21],
    		'Engin de traction' => [22,23,24,25],
    		'Remorque' => [26,27,28,29,30],
    		'Voiture' => [31,32,33],
    		'Camion et camionnette' => [34,35,36,37,38,39,40,41],
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
			'Kalmar',
			'Mitsubitshi',
			'Hanix',
			'Zephir',
			'Eurotract',
			'Pales',
			'Ligier',
			'Masson',
			'Peugeot',
			'Citroen',
			'Renault',
			'Mercedes',
			'Bmw',
			'Audi',
			'Trouillet',
			'Lahitte',
			'Micodan',
			'Spitzer',
			'Hermanns',
			'Baryval',
			'Man',
			'Samro',
			'Frejat',
			'Iveco',
			'Castera',
			'Gruau',
			'Elv',
			'Volvo',
			'Dulevo',
			'PSA',
			'Sman',
			'Jungheinrich',
			'Nilkfisk',
			'Crown',
			'IHI',
			'Dulevo',
			'Kassboher',
            'A définir',
    	);

    	foreach ($marques as $marque) {
    		$ma = new Marques();
    		$ma->setNom($marque);
    		array_push($marques_obj, $ma);

    		$manager->persist($ma);
    	}


        //UTILISATEURS
        $admin_parc = new Utilisateurs();
        $admin_parc->setUsername("D.Caille");
        $admin_parc->setEmail("d.caille@gt-logistics.fr");
        $admin_parc->setRoles(array('ROLE_PARC_ADMIN'));
        $password = $this->encoder->encodePassword($admin_parc, "PdDw,ABc0pCv3");
        $admin_parc->setPassword($password);
        $manager->persist($admin_parc);

        $admin_parc = new Utilisateurs();
        $admin_parc->setUsername("Leo");
        $admin_parc->setEmail("leo.couffinhal@wiilog.fr");
        $admin_parc->setRoles(array('ROLE_SUPER_ADMIN'));
        $password = $this->encoder->encodePassword($admin_parc, "Admin123!");
        $admin_parc->setPassword($password);
        $manager->persist($admin_parc);

        $manager->flush();
    }
}
