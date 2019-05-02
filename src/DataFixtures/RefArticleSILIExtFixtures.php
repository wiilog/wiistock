<?php

namespace App\DataFixtures;

use App\Entity\ArticleFournisseur;
use App\Entity\CategorieCL;
use App\Entity\ChampsLibre;
use App\Entity\Fournisseur;
use App\Entity\Type;
use App\Entity\ValeurChampsLibre;
use App\Repository\CategorieCLRepository;
use App\Repository\FournisseurRepository;
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
        $path = "src/DataFixtures/Csv/sili-ext.csv";
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
            $typeSiliExt = $this->typeRepository->findOneBy(['label' => Type::LABEL_SILI_EXT]);

            // contruction référence
            $referenceNum = str_pad($i, 5, '0', STR_PAD_LEFT);

            // champs fixes
            $referenceArticle = new ReferenceArticle();
            $referenceArticle
                ->setType($typeSiliExt)
                ->setReference('SILI_EXT_' . $referenceNum)
                ->setLibelle('SILI_EXT_' . $referenceNum)
                ->setTypeQuantite('reference')
                ->setStatut($this->statutRepository->findOneByCategorieAndStatut(ReferenceArticle::CATEGORIE, ReferenceArticle::STATUT_ACTIF));
            $manager->persist($referenceArticle);
            $manager->flush();


            // champs libres
            $listFields = [
                ['label' => 'adresse', 'col' => 0],
                ['label' => 'famille produit', 'col' => 1],
                ['label' => 'date', 'col' => 2],
                ['label' => "projet", 'col' => 3],
                ['label' => "demandeur", 'col' => 4],
                ['label' => "date fin de projet", 'col' => 5],
                ['label' => "lot", 'col' => 6],
                ['label' => "sortie", 'col' => 7],
                ['label' => "commentaire", 'col' => 8],
                ['label' => "jours de péremption", 'col' => 9],
            ];

            foreach($listFields as $field) {
                $vcl = new ValeurChampsLibre();
                $label = $field['label'] . ' (' . $typeSiliExt->getLabel() . ')';
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
        return ['articlesSILIext', 'articles'];
    }

}
