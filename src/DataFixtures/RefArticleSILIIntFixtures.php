<?php

namespace App\DataFixtures;

use App\Entity\CategorieCL;
use App\Entity\ChampsLibre;
use App\Entity\Type;
use App\Entity\ValeurChampsLibre;
use App\Repository\CategorieCLRepository;
use App\Repository\StatutRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

use App\Entity\ReferenceArticle;
use App\Repository\TypeRepository;
use App\Repository\ChampsLibreRepository;

class RefArticleSILIIntFixtures extends Fixture implements FixtureGroupInterface
{
    private $encoder;


    /**
     * @var TypeRepository
     */
    private $typeRepository;
    /**
     * @var ChampsLibreRepository
     */
    private $champsLibreRepository;

    /**
     * @var StatutRepository
     */
    private $statutRepository;

    /**
     * @var CategorieCLRepository
     */
    private $categorieCLRepository;

    public function __construct(CategorieCLRepository $categorieCLRepository, UserPasswordEncoderInterface $encoder, TypeRepository $typeRepository, ChampsLibreRepository $champsLibreRepository, StatutRepository $statutRepository)
    {
        $this->typeRepository = $typeRepository;
        $this->champsLibreRepository = $champsLibreRepository;
        $this->encoder = $encoder;
        $this->statutRepository = $statutRepository;
        $this->categorieCLRepository = $categorieCLRepository;
    }

    public function load(ObjectManager $manager)
    {
        $path = "src/DataFixtures/Csv/sili-int.csv";
        $file = fopen($path, "r");

        $rows = [];
        while (($data = fgetcsv($file, 1000, ";")) !== false) {
            $rows[] = array_map('utf8_encode', $data);
        }

        array_shift($rows); // supprime la 1è ligne d'en-têtes

        $i = 1;
        foreach ($rows as $row) {
//            if (empty($row[0])) continue;
            dump($i);
            $i++;
            $typeSiliInt = $this->typeRepository->findOneBy(['label' => Type::LABEL_SILI_INT]);

            // contruction référence
            $referenceNum = str_pad($i, 5, '0', STR_PAD_LEFT);

            // champs fixes
            $referenceArticle = new ReferenceArticle();
            $referenceArticle
                ->setType($typeSiliInt)
                ->setReference('SILI_INT_' . $referenceNum)
                ->setLibelle('SILI_INT_' . $referenceNum)
                ->setTypeQuantite('reference')
                ->setStatut($this->statutRepository->findOneByCategorieAndStatut(ReferenceArticle::CATEGORIE, ReferenceArticle::STATUT_ACTIF));
            $manager->persist($referenceArticle);
            $manager->flush();


            // champs libres
            $listFields = [
                ['label' => 'adresse', 'col' => 0, 'type' => ChampsLibre::TYPE_TEXT],
                ['label' => 'famille produit', 'col' => 1, 'type' => ChampsLibre::TYPE_LIST, 'elements' => ['CONSOMMABLES','PAD','POMPE','POMPE_41', 'PIECES DETACHEES', 'PDT GENERIQUE', 'DCOS TEST ELECTRIQUE', 'SILICIUM', 'SIL_EXTERNE', 'SIL_INTERNE', 'MOBILIER SB', 'MOBILIER TERTIAIRE', 'CIBLE / SLUGS']],
                ['label' => 'date', 'col' => 2, 'type' => ChampsLibre::TYPE_DATE],
                ['label' => 'diamètre', 'col' => 3, 'type' => ChampsLibre::TYPE_NUMBER],
                ['label' => 'n° lot autre', 'col' => 4, 'type' => ChampsLibre::TYPE_TEXT],
                ['label' => 'n° lot Léti', 'col' => 5, 'type' => ChampsLibre::TYPE_TEXT],
                ['label' => "demandeur", 'col' => 6, 'type' => ChampsLibre::TYPE_TEXT],
                ['label' => "projet 3", 'col' => 7, 'type' => ChampsLibre::TYPE_TEXT],
                ['label' => "date de retour en salle ou d'envoi à Crolles ou autre", 'col' => 8, 'type' => ChampsLibre::TYPE_DATE],
                ['label' => "commentaire", 'col' => 9, 'type' => ChampsLibre::TYPE_TEXT],
                ['label' => "mois de stock", 'col' => 10, 'type' => ChampsLibre::TYPE_LIST, 'elements' => ['0','1','2','3','4','5','6','7','8','9','10','11','12']],
            ];

            foreach($listFields as $field) {
                $vcl = new ValeurChampsLibre();
                $label = $field['label'] . ' (' . $typeSiliInt->getLabel() . ')';
                $cl = $this->champsLibreRepository->findOneBy(['label' => $label]);
                if (empty($cl)) {
                    dump('il manque le champ libre de label ' . $label);
                } else {
                    $vcl
                        ->setChampLibre($cl)
                        ->addArticleReference($referenceArticle)
                        ->setValeur($row[$field['col']]);
                    $manager->persist($vcl);
                }
            }

            $manager->flush();
        }
        fclose($file);
    }

    public static function getGroups():array {
        return ['articlesSILIint', 'articles'];
    }

}
