<?php

namespace App\DataFixtures;

use App\Entity\ArticleFournisseur;
use App\Entity\ChampsLibre;
use App\Entity\Fournisseur;
use App\Entity\Type;
use App\Repository\FournisseurRepository;
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


    public function __construct(UserPasswordEncoderInterface $encoder, TypeRepository $typeRepository, ChampsLibreRepository $champsLibreRepository, FournisseurRepository $fournisseurRepository)
    {
        $this->typeRepository = $typeRepository;
        $this->champsLibreRepository = $champsLibreRepository;
        $this->encoder = $encoder;
        $this->fournisseurRepository = $fournisseurRepository;
    }

    public function load(ObjectManager $manager)
    {
        if ($_SERVER['APP_ENV'] == 'dev') {
            $path = "C:\wamp64\www\WiiStock\public\csv\pdt.csv";
        } else {
            $path = "https://cl1-test.follow-gt.fr/csv/pdt.csv";
        };

        $file = fopen($path, "r");

        $firstRow = true;

        while (($data = fgetcsv($file, 1000, ";")) !== false) {
            if ($firstRow) {
                $firstRow = false;
            } else {
                $data = array_map('utf8_encode', $data);
                dump(print_r($data));

                $typePdt = $this->typeRepository->findOneBy(['label' => Type::LABEL_PDT]);

                // champs fixes
                $referenceArticle = new ReferenceArticle();
                $referenceArticle
                    ->setType($typePdt)
                    ->setReference($data[0])
                    ->setLibelle($data[1])
                    ->setQuantiteStock(intval($data[3]));
                $manager->persist($referenceArticle);
                $manager->flush();


                // champ fournisseur
                $fournisseurLabel = $data[9];
                if (!empty($fournisseurLabel)) {
                    $fournisseurRef = $data[10];
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
                        ->setLabel($data[1])
                        ->setReference(time()) // code aléatoire
                        ->setFournisseur($fournisseur)
                        ->setReferenceArticle($referenceArticle);

                    $manager->persist($articleFournisseur);
                }


                // champs libres
                $listData = [
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

                foreach($listData as $data) {
                    $vcl = new ValeurChampsLibre();
                    $cl = $this->champsLibreRepository->findOneBy(['label' => $data['label']]);
                    if (empty($cl)) {
                        $cl = new ChampsLibre();
                        $cl
                            ->setLabel($data['label'])
                            ->setTypage($data['type'])
                            ->setType($typePdt);
                        $manager->persist($cl);
                    }
                    $vcl
                        ->setChampLibre($cl)
                        ->addArticleReference($referenceArticle)
                        ->setValeur($data['col']);
                    $manager->persist($vcl);
                }

                $manager->flush();
            }
            unset($data);
        }
        fclose($file);
    }

    public static function getGroups():array {
        return ['articlePDT'];
    }

}
