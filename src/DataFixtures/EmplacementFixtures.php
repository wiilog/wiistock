<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use App\Entity\Emplacement;

class EmplacementFixtures extends Fixture
{
    private $encoder;

    public function __construct(UserPasswordEncoderInterface $encoder)
    {
        $this->encoder = $encoder;
    }

    public function load(ObjectManager $manager)
    {
        $emplacements = [
            ['libelle'=> 'SAS 4101 BASEMENT E16', 'description' => 'Gestion KANBAN - Flux CSP' ],
            ['libelle'=> 'SAS 4102 BASEMENT B21', 'description' => 'Gestion KANBAN - Flux CSP' ],
            ['libelle'=> 'SAS BHT N1', 'description' => 'Gestion KANBAN - Flux CSP' ],
            ['libelle'=> 'SAS BHT N1 B5', 'description' => 'Gestion KANBAN - Flux CSP' ],
            ['libelle'=> 'SAS BHT N3', 'description' => 'Gestion KANBAN - Flux CSP' ],
            ['libelle'=> 'SAS BHT BASEMENT 14L', 'description' => 'Gestion KANBAN - Flux CSP' ],
            ['libelle'=> 'SAS BOC / BCA C148', 'description' => 'Gestion KANBAN - Flux CSP' ],
            ['libelle'=> 'SAS BAT41.07 N0 SAS 2', 'description' => 'Gestion KANBAN - Flux CSP' ],
            ['libelle'=> 'SAS PFP RDC C1425', 'description' => 'Gestion KANBAN - Flux CSP' ],
            ['libelle'=> 'Stock CSP', 'description' => 'Gestion KANBAN - Flux CSP' ],
            ['libelle'=> 'Stock Cible / slug', 'description' => 'Gestion KANBAN - Flux CSP' ],
            ['libelle'=> 'Ensacheuses', 'description' => 'Gestion KANBAN - Flux CSP' ],
            ['libelle'=> 'Zone sous-traitance', 'description' => 'Retrait et Collecte - Flux Scilicium' ],
            ['libelle'=> 'Casiers', 'description' => 'Retrait et Collecte - Flux Scilicium' ],
            ['libelle'=> 'Mise au stockage', 'description' => 'Retrait et Collecte - Flux Scilicium' ],
            ['libelle'=> 'Stock interne', 'description' => 'Retrait et Collecte - Flux Scilicium' ],
            ['libelle'=> 'stock externe', 'description' => 'Retrait et Collecte - Flux Scilicium' ],
            ['libelle'=> 'stock silicium', 'description' => 'Retrait et Collecte - Flux Scilicium' ],
            ['libelle'=> 'Recyclage', 'description' => 'Retrait et Collecte - Flux Scilicium' ],
            ['libelle'=> 'Départ sous-traitance', 'description' => 'Retrait et Collecte - Flux Scilicium' ],
            ['libelle'=> 'Rives', 'description' => 'Retrait et Collecte - Flux Scilicium' ],
            ['libelle'=> 'SAS 41', 'description' => 'Retrait et Collecte - Flux Scilicium' ],
            ['libelle'=> 'Etagère 200 BH', 'description' => 'Retrait et Collecte - Flux Scilicium' ],
            ['libelle'=> 'SAS BHT', 'description' => 'Retrait et Collecte - Flux Scilicium' ],
            ['libelle'=> 'Labo 40.06', 'description' => 'Retrait et Collecte - Flux Scilicium' ],
            ['libelle'=> 'Temoins 200', 'description' => 'Retrait et Collecte - Flux Scilicium' ],
            ['libelle'=> 'Etagère IL1000A', 'description' => 'Retrait et Collecte - Flux Scilicium' ],
            ['libelle'=> 'Etagère lots 200', 'description' => 'Retrait et Collecte - Flux Scilicium' ],
            ['libelle'=> 'Entrées 300', 'description' => 'Retrait et Collecte - Flux Scilicium' ],
            ['libelle'=> 'SAS Silicum', 'description' => 'Retrait et Collecte - Flux Scilicium' ],
            ['libelle'=> 'Gare LBB BHT', 'description' => 'Retrait et Collecte - Flux Scilicium' ],
            ['libelle'=> 'Hauvent', 'description' => 'Retrait et Collecte - Flux Scilicium' ],
            ['libelle'=> 'Rives', 'description' => 'Retrait et Collecte - Flux Scilicium' ],
            ['libelle'=> 'Stock silicium', 'description' => 'Retrait et Collecte - Flux Scilicium' ],
            ['libelle'=> '41.23 Etagère Litho (entrée & sortie)', 'description' => 'Retrait et Collecte - Flux PDT' ],
            ['libelle'=> '41.23 Etagère Gravure (entrée & sortie)', 'description' => 'Retrait et Collecte - Flux PDT' ],
            ['libelle'=> '41.23 Etagère Metro (entrée & sortie)', 'description' => 'Retrait et Collecte - Flux PDT' ],
            ['libelle'=> '41.26 Etagère collecte (entrée & sortie)', 'description' => 'Retrait et Collecte - Flux PDT' ],
            ['libelle'=> 'Stock PDT Etagère retrait', 'description' => 'Retrait et Collecte - Flux PDT' ],
            ['libelle'=> 'Stock PDT Etagère sortie', 'description' => 'Retrait et Collecte - Flux PDT' ],
            ['libelle'=> 'BHT N1 Etagère entrée & sortie', 'description' => 'Retrait et Collecte - Flux PDT' ],
            ['libelle'=> 'Stock pompe', 'description' => 'Retrait et Collecte - Flux PDT' ],
            ['libelle'=> 'Kit gravure Plot F8', 'description' => 'Retrait et Collecte - Flux PDT' ],
            ['libelle'=> 'Kit BHT', 'description' => 'Retrait et Collecte - Flux PDT' ],
            ['libelle'=> 'BHT plot 22J1', 'description' => 'Retrait et Collecte - Flux PDT' ],
            ['libelle'=> 'BHT N1 Sas kit', 'description' => 'Retrait et Collecte - Flux PDT' ],
            ['libelle'=> 'Stock grillagé', 'description' => 'Retrait et Collecte - Flux PDT' ],
            ['libelle'=> '40.17 stock PDT hors SB', 'description' => 'Retrait et Collecte - Flux PDT' ],
            ['libelle'=> 'Haut vent', 'description' => 'Retrait et Collecte - Flux PDT' ],
            ['libelle'=> 'Stock M26', 'description' => 'Retrait et Collecte - Flux PDT' ],
            ['libelle'=> 'Stock 33.03', 'description' => 'Retrait et Collecte - Flux Mobilier' ],
            ['libelle'=> 'Stock Rives', 'description' => 'Retrait et Collecte - Flux Mobilier' ],
                     
        ];

        foreach ($emplacements as $emplacementFixture) {
            $emplacement = new Emplacement();
            $emplacement->setLabel($emplacementFixture['libelle']);
            $emplacement->setDescription($emplacementFixture['description']);
            $manager->persist($emplacement);
        }

        $manager->flush();
    }

}
