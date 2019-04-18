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

class RefArticleSLUGCIBLEFixtures extends Fixture implements FixtureGroupInterface
{
    /**
     * @var UserPasswordEncoderInterface
     */
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


    public function __construct(UserPasswordEncoderInterface $encoder, TypeRepository $typeRepository, ChampsLibreRepository $champsLibreRepository, StatutRepository $statutRepository, FournisseurRepository $fournisseurRepository)
    {
        $this->typeRepository = $typeRepository;
        $this->champsLibreRepository = $champsLibreRepository;
        $this->encoder = $encoder;
        $this->statutRepository = $statutRepository;
        $this->fournisseurRepository = $fournisseurRepository;
    }

    public function load(ObjectManager $manager)
    {
        $path = "public/csv/slugcible.csv";
        $file = fopen($path, "r");

        $rows = [];
        while (($data = fgetcsv($file, 1000, ";")) !== false) {
            $rows[] = array_map('utf8_encode', $data);
        }

        array_shift($rows); // supprime la 1è ligne d'en-têtes

        $i = 1;
        foreach ($rows as $row) {
            if (empty($row[0])) continue;
            $i++;

            $typeSlugcible = $this->typeRepository->findOneBy(['label' => Type::LABEL_SLUGCIBLE]);
            $statutActif = $this->statutRepository->findOneByCategorieAndStatut(ReferenceArticle::CATEGORIE, ReferenceArticle::STATUT_ACTIF);

            // champs fixes
            $referenceArticle = new ReferenceArticle();
            $referenceArticle
                ->setType($typeSlugcible)
                ->setReference($row[0])
                ->setLibelle($row[1])
                ->setQuantiteStock(intval($row[2]))
                ->setTypeQuantite('reference')
                ->setStatut($statutActif);
            $manager->persist($referenceArticle);


            // champ fournisseur
            $fournisseurLabel = $row[10];
            if (!empty($fournisseurLabel)) {
                $fournisseurRef = $row[11];
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
                    ->setLabel($row[0])
                    ->setReference(time()) // code aléatoire
                    ->setFournisseur($fournisseur)
                    ->setReferenceArticle($referenceArticle);

                $manager->persist($articleFournisseur);
            }


            // champs libres
            $listFields = [
                ['label' => 'bénéficiaire ou n° commande', 'col' => 7, 'type' => ChampsLibre::TYPE_TEXT],
                ['label' => 'machine', 'col' => 9, 'type' => ChampsLibre::TYPE_TEXT],
                ['label' => 'stock mini', 'col' => 13, 'type' => ChampsLibre::TYPE_NUMBER],
                ['label' => 'date entrée', 'col' => 15, 'type' => ChampsLibre::TYPE_TEXT],
            ];

            foreach($listFields as $field) {
                $vcl = new ValeurChampsLibre();
                $cl = $this->champsLibreRepository->findOneBy(['label' => $field['label']]);
                if (empty($cl)) {
                    $cl = new ChampsLibre();
                    $cl
                        ->setLabel($field['label'])
                        ->setTypage($field['type'])
                        ->setType($typeSlugcible);
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
        return ['articles'];
    }

}
