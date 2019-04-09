<?php

namespace App\DataFixtures;

use App\Entity\ArticleFournisseur;
use App\Entity\ChampsLibre;
use App\Entity\Fournisseur;
use App\Entity\Type;
use App\Repository\FournisseurRepository;
use App\Repository\StatutRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

use App\Entity\ReferenceArticle;
use App\Entity\ValeurChampsLibre;
use App\Repository\TypeRepository;
use App\Repository\ChampsLibreRepository;

class RefArticlePDTFixtures extends Fixture implements FixtureGroupInterface
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
     * @var FournisseurRepository
     */
    private $fournisseurRepository;

    /**
     * @var StatutRepository
     */
    private $statutRepository;


    public function __construct(UserPasswordEncoderInterface $encoder, TypeRepository $typeRepository, ChampsLibreRepository $champsLibreRepository, FournisseurRepository $fournisseurRepository, StatutRepository $statutRepository)
    {
        $this->typeRepository = $typeRepository;
        $this->champsLibreRepository = $champsLibreRepository;
        $this->encoder = $encoder;
        $this->fournisseurRepository = $fournisseurRepository;
        $this->statutRepository = $statutRepository;
    }

    public function load(ObjectManager $manager)
    {
        $path = "public/csv/pdt-mini.csv";
        $file = fopen($path, "r");

        $rows = [];
        while (($data = fgetcsv($file, 1000, ";")) !== false) {
            $rows[] = array_map('utf8_encode', $data);
        }

        array_shift($rows); // supprime la 1è ligne d'en-têtes

        $i = 1;
        foreach($rows as $row) {
            if (empty($row[0])) continue;
            dump($i);
            $i++;
            $typePdt = $this->typeRepository->findOneBy(['label' => Type::LABEL_PDT]);

            // champs fixes
            $referenceArticle = new ReferenceArticle();
            $referenceArticle
                ->setType($typePdt)
                ->setStatut($this->statutRepository->findOneByCategorieAndStatut(ReferenceArticle::CATEGORIE, ReferenceArticle::STATUT_ACTIF))
                ->setReference($row[0])
                ->setLibelle($row[1])
                ->setTypeQuantite('reference')
                ->setQuantiteStock(intval($row[3]));
            $manager->persist($referenceArticle);
            $manager->flush();


            // champ fournisseur
            $fournisseurLabel = $row[9];
            if (!empty($fournisseurLabel)) {
                $fournisseurRef = $row[10];
                if (in_array($fournisseurRef, ['nc', 'nd', 'NC', 'ND', '*', '.', ''])) {
                    $fournisseurRef = $fournisseurLabel;
                }
                $fournisseur = $this->fournisseurRepository->findOneBy(['nom' => $fournisseurLabel]);

                // si le fournisseur n'existe pas, on le crée
                if (empty($fournisseur)) {
                    $fournisseur = new Fournisseur();
                    $fournisseur
                        ->setNom($fournisseurLabel)
                        ->setCodeReference($fournisseurRef);
                    $manager->persist($fournisseur);
                }

                // on crée l'article fournisseur, on le lie au fournisseur et à l'article de référence
                $articleFournisseur = new ArticleFournisseur();
                $articleFournisseur
                    ->setLabel($row[1])
                    ->setReference(time())// code aléatoire
                    ->setFournisseur($fournisseur)
                    ->setReferenceArticle($referenceArticle);

                $manager->persist($articleFournisseur);
            }


            // champs libres
                $listFields = [
                ['label' => 'adresse', 'col' => 2, 'type' => ChampsLibre::TYPE_TEXT],
                ['label' => 'famille produit', 'col' => 4, 'type' => ChampsLibre::TYPE_TEXT],
                ['label' => 'zone', 'col' => 5, 'type' => ChampsLibre::TYPE_TEXT],
                ['label' => 'équipementier', 'col' => 6, 'type' => ChampsLibre::TYPE_TEXT],
                ['label' => "R+H1:I9505ef équipementier", 'col' => 7, 'type' => ChampsLibre::TYPE_TEXT],
                ['label' => "machine", 'col' => 8, 'type' => ChampsLibre::TYPE_TEXT],
                ['label' => "stock mini", 'col' => 11, 'type' => ChampsLibre::TYPE_NUMBER],
                ['label' => "stock alerte", 'col' => 12, 'type' => ChampsLibre::TYPE_NUMBER],
                ['label' => "n° lot", 'col' => 13, 'type' => ChampsLibre::TYPE_TEXT],
                ['label' => "date entrée", 'col' => 14, 'type' => ChampsLibre::TYPE_TEXT],
            ];

                foreach($listFields as $field) {
                $vcl = new ValeurChampsLibre();
                    $cl = $this->champsLibreRepository->findOneBy(['label' => $field['label']]);
                if (empty($cl)) {
                    $cl = new ChampsLibre();
                    $cl
                        ->setLabel($field['label'])
                        ->setTypage($field['type'])
                        ->setType($typePdt);
                    $manager->persist($cl);
                }
                $vcl
                    ->setChampLibre($cl)
                    ->addArticleReference($referenceArticle)
                    ->setValeur($row[$field['col']]);
                $manager->persist($vcl);
            }

            $manager->flush();
        }

        fclose($file);
    }

    public static function getGroups():array {
        return ['articlePDT'];
    }

}
