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
        $path = "public/csv/mob.csv";
        $file = fopen($path, "r");

        $firstRow = true;
        while (($data = fgetcsv($file, 1000, ";")) !== false) {
            if ($firstRow) {
                $firstRow = false;
            } else {
                $data = array_map('utf8_encode', $data);
                dump(print_r($data));

                $typeSlugcible = $this->typeRepository->findOneBy(['label' => Type::LABEL_SLUGCIBLE]);
                $statutActif = $this->statutRepository->findOneByCategorieAndStatut(ReferenceArticle::CATEGORIE, ReferenceArticle::STATUT_ACTIF);

                // champs fixes
                $referenceArticle = new ReferenceArticle();
                $referenceArticle
                    ->setType($typeSlugcible)
                    ->setReference($data[0])
                    ->setLibelle($data[1])
                    ->setQuantiteStock(intval($data[2]))
                    ->setTypeQuantite('reference')
                    ->setStatut($statutActif);
                $manager->persist($referenceArticle);


                // champ fournisseur
                $fournisseurLabel = $data[10];
                if (!empty($fournisseurLabel)) {
                    $fournisseurRef = $data[11];
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
                        ->setReference(time()) // code aléatoire
                        ->setFournisseur($fournisseur)
                        ->setReferenceArticle($referenceArticle);

                    $manager->persist($articleFournisseur);
                }


                // champs libres
                $listData = [
                    ['label' => 'bénéficiaire ou n° commande', 'col' => 7, 'type' => ChampsLibre::TYPE_TEXT],
                    ['label' => 'machine', 'col' => 9, 'type' => ChampsLibre::TYPE_TEXT],
                    ['label' => 'stock mini', 'col' => 13, 'type' => ChampsLibre::TYPE_NUMBER],
                    ['label' => 'date entrée', 'col' => 15, 'type' => ChampsLibre::TYPE_TEXT],
                ];

                foreach($listData as $data) {
                    $vcl = new ValeurChampsLibre();
                    $cl = $this->champsLibreRepository->findOneBy(['label' => $data['label']]);
                    if (empty($cl)) {
                        $cl = new ChampsLibre();
                        $cl
                            ->setLabel($data['label'])
                            ->setTypage($data['type'])
                            ->setType($typeSlugcible);
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
        }
        fclose($file);
    }

    public static function getGroups():array {
        return ['articles'];
    }

}
