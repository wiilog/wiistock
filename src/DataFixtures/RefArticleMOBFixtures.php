<?php

namespace App\DataFixtures;

use App\Entity\ArticleFournisseur;
use App\Entity\Fournisseur;
use App\Entity\Type;
use App\Repository\FournisseurRepository;
use App\Repository\StatutRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

use App\Entity\ReferenceArticle;
use App\Repository\TypeRepository;
use App\Repository\ChampsLibreRepository;

class RefArticleMOBFixtures extends Fixture implements FixtureGroupInterface
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
     * @var FournisseurRepository
     */
    private $fournisseurRepository;

    /**
     * @var Packages
     */
    private $assetsManager;


    public function __construct(UserPasswordEncoderInterface $encoder, TypeRepository $typeRepository, ChampsLibreRepository $champsLibreRepository, StatutRepository $statutRepository, FournisseurRepository $fournisseurRepository, Packages $assetsManager)
    {
        $this->typeRepository = $typeRepository;
        $this->champsLibreRepository = $champsLibreRepository;
        $this->encoder = $encoder;
        $this->statutRepository = $statutRepository;
        $this->fournisseurRepository = $fournisseurRepository;
        $this->assetsManager = $assetsManager;
    }

    public function load(ObjectManager $manager)
    {
        $path = "public/csv/mob.csv";
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
            $typeMob = $this->typeRepository->findOneBy(['label' => Type::LABEL_MOB]);

            // champs fixes
            $referenceArticle = new ReferenceArticle();
            $referenceArticle
                ->setType($typeMob)
                ->setReference($row[0])
                ->setLibelle($row[1])
                ->setQuantiteStock(intval($row[3]))
                ->setTypeQuantite('reference')
                ->setStatut($this->statutRepository->findOneByCategorieAndStatut(ReferenceArticle::CATEGORIE, ReferenceArticle::STATUT_ACTIF));
            $manager->persist($referenceArticle);
            $manager->flush();


            // champ fournisseur
            $fournisseurLabel = $row[5];
            if (!empty($fournisseurLabel)) {
                $fournisseurRef = $row[6];
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
                    ->setLabel($data[0])
                    ->setReference(time())
                    ->setFournisseur($fournisseur)
                    ->setReferenceArticle($referenceArticle); // code aléatoire

                $manager->persist($articleFournisseur);
            }


            // champs libres
            $listFields = [
                ['label' => 'adresse', 'col' => 2, 'type' => ChampsLibre::TYPE_TEXT],
                ['label' => 'famille produit', 'col' => 4, 'type' => ChampsLibre::TYPE_LIST, 'elements' => 'CONSOMMABLES;PAD;POMPE;POMPE_41;PIECES DETACHEES;PDT GENERIQUE;DCOS TEST ELECTRIQUE;SILICIUM;SIL_EXTERNE;SIL_INTERNE;MOBILIER SB;MOBILIER TERTIAIRE;CIBLE / SLUGS'],
                ['label' => "stock mini", 'col' => 7, 'type' => ChampsLibre::TYPE_NUMBER],
                ['label' => "stock alerte", 'col' => 8, 'type' => ChampsLibre::TYPE_NUMBER],
                ['label' => "prix unitaire", 'col' => 9, 'type' => ChampsLibre::TYPE_TEXT],
                ['label' => "date entrée", 'col' => 10, 'type' => ChampsLibre::TYPE_DATE],
                ['label' => "prix du stock final", 'col' => 11, 'type' => ChampsLibre::TYPE_DATE],
                ['label' => "alerte mini", 'col' => 12, 'type' => ChampsLibre::TYPE_LIST, 'elements' => 'besoin'],
                ['label' => "alerte prévision", 'col' => 13, 'type' => ChampsLibre::TYPE_NUMBER],
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
                        ->setType($typeMob);

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