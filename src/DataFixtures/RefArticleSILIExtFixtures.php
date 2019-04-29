<?php

namespace App\DataFixtures;

use App\Entity\ChampsLibre;
use App\Entity\Type;
use App\Repository\StatutRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

use App\Entity\ReferenceArticle;
use App\Repository\TypeRepository;
use App\Repository\ChampsLibreRepository;

class RefArticleSILIExtFixtures extends Fixture implements FixtureGroupInterface
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

    public function __construct(UserPasswordEncoderInterface $encoder, TypeRepository $typeRepository, ChampsLibreRepository $champsLibreRepository, StatutRepository $statutRepository)
    {
        $this->typeRepository = $typeRepository;
        $this->champsLibreRepository = $champsLibreRepository;
        $this->encoder = $encoder;
        $this->statutRepository = $statutRepository;
    }

    public function load(ObjectManager $manager)
    {
        $path = "public/csv/sili-ext.csv";
        $file = fopen($path, "r");

        $rows = [];
        while (($data = fgetcsv($file, 1000, ";")) !== false) {
            $rows[] = array_map('utf8_encode', $data);
        }

        array_shift($rows); // supprime la 1è ligne d'en-têtes

        $i = 1;
        foreach ($rows as $row) {
            if (empty($row[0])) continue;
            dump($i);
            $i++;
            $typeSili = $this->typeRepository->findOneBy(['label' => Type::LABEL_SILI]);

            // contruction référence
            $referenceNum = str_pad($i, 5, '0', STR_PAD_LEFT);

            // champs fixes
            $referenceArticle = new ReferenceArticle();
            $referenceArticle
                ->setType($typeSili)
                ->setReference('SILI_EXT_' . $referenceNum)
                ->setLibelle('SILI_EXT_' . $referenceNum)
                ->setTypeQuantite('reference')
                ->setStatut($this->statutRepository->findOneByCategorieAndStatut(ReferenceArticle::CATEGORIE, ReferenceArticle::STATUT_ACTIF));
            $manager->persist($referenceArticle);
            $manager->flush();


            // champs libres
            $listFields = [
                ['label' => 'adresse', 'col' => 0, 'type' => ChampsLibre::TYPE_TEXT],
                ['label' => 'famille produit', 'col' => 1, 'type' => ChampsLibre::TYPE_LIST, 'elements' => 'CONSOMMABLES;PAD;POMPE;POMPE_41;PIECES DETACHEES;PDT GENERIQUE;DCOS TEST ELECTRIQUE;SILICIUM;SIL_EXTERNE;SIL_INTERNE;MOBILIER SB;MOBILIER TERTIAIRE;CIBLE / SLUGS'],
                ['label' => 'date', 'col' => 2, 'type' => ChampsLibre::TYPE_DATE],
                ['label' => "projet", 'col' => 3, 'type' => ChampsLibre::TYPE_TEXT],
                ['label' => "demandeur", 'col' => 4, 'type' => ChampsLibre::TYPE_TEXT],
                ['label' => "date fin de projet", 'col' => 5, 'type' => ChampsLibre::TYPE_DATE],
                ['label' => "lot", 'col' => 6, 'type' => ChampsLibre::TYPE_TEXT],
                ['label' => "sortie", 'col' => 7, 'type' => ChampsLibre::TYPE_NUMBER],
                ['label' => "commentaire", 'col' => 8, 'type' => ChampsLibre::TYPE_TEXT],
                ['label' => "jours de péremption", 'col' => 9, 'type' => ChampsLibre::TYPE_NUMBER],
            ];

            foreach($listFields as $field) {
                $vcl = new ValeurChampsLibre();
                $cl = $this->champsLibreRepository->findOneBy(['label' => $field['label']]);
                if (empty($cl)) {
                    $cl = new ChampsLibre();
                    $cl
                        ->setLabel($field['label'])
                        ->setTypage($field['type'])
                        ->setCategorieCL($this->categorieCLRepository->findOneByLabel(CategorieCL::REFERENCE_ARTICLE))
                        ->setType($typeSili);

                    if ($field['type'] == ChampsLibre::TYPE_LIST) {
                        $cl->setElements($field['elements']);
                    }
                    $manager->persist($cl);
                }
                $vcl
                    ->setChampLibre($cl)
                    ->addArticleReference($referenceArticle)
                    ->setValeur($data[$field['col']]);
                $manager->persist($vcl);
            }

            $manager->flush();
        }
        fclose($file);
    }

    public static function getGroups():array {
        return ['articles'];
    }

}
