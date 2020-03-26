<?php

namespace App\DataFixtures;

use App\Entity\ArticleFournisseur;
use App\Entity\Fournisseur;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\ValeurChampLibre;
use App\Repository\ArticleFournisseurRepository;
use App\Repository\CategorieCLRepository;
use App\Repository\FournisseurRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

use App\Entity\ReferenceArticle;
use App\Repository\ChampLibreRepository;

class PatchRefArticleMOBFixtures extends Fixture implements FixtureGroupInterface
{
    private $encoder;

    /**
     * @var ChampLibreRepository
     */
    private $champLibreRepository;

    /**
     * @var FournisseurRepository
     */
    private $fournisseurRepository;

    /**
     * @var CategorieCLRepository
     */
    private $categorieCLRepository;

    /**
     * @var Packages
     */
    private $assetsManager;

    /**
     * @var ArticleFournisseurRepository
     */
    private $articleFournisseurRepository;


    public function __construct(ArticleFournisseurRepository $articleFournisseurRepository, CategorieCLRepository $categorieCLRepository, UserPasswordEncoderInterface $encoder, ChampLibreRepository $champsLibreRepository, FournisseurRepository $fournisseurRepository, Packages $assetsManager)
    {
        $this->champLibreRepository = $champsLibreRepository;
        $this->encoder = $encoder;
        $this->fournisseurRepository = $fournisseurRepository;
        $this->assetsManager = $assetsManager;
        $this->categorieCLRepository = $categorieCLRepository;
        $this->articleFournisseurRepository = $articleFournisseurRepository;
    }

    public function load(ObjectManager $manager)
    {
        $statutRepository = $manager->getRepository(Statut::class);
        $path = "src/DataFixtures/Csv/mob.csv";
        $file = fopen($path, "r");

        $rows = [];
        while (($data = fgetcsv($file, 1000, ";")) !== false) {
            $rows[] = array_map('utf8_encode', $data);
        }

        array_shift($rows); // supprime la 1è ligne d'en-têtes
        $typeRepository = $manager->getRepository(Type::class);
        $i = 1;
        foreach ($rows as $row) {
            if (empty($row[0])) continue;
            dump($i);
            $i++;
            $typeMob = $typeRepository->findOneBy(['label' => Type::LABEL_MOB]);
            // contruction référence
            $referenceNum = str_pad($i, 5, '0', STR_PAD_LEFT);

            // champs fixes
            $referenceArticle = new ReferenceArticle();
            $referenceArticle
                ->setType($typeMob)
                ->setReference($row[0] . '-' . $referenceNum)
                ->setLibelle($row[1])
                ->setQuantiteStock(intval($row[3]))
                ->setTypeQuantite('reference')
                ->setStatut($statutRepository->findOneByCategorieNameAndStatutCode(ReferenceArticle::CATEGORIE, ReferenceArticle::STATUT_ACTIF));
            $manager->persist($referenceArticle);
            $manager->flush();


            // champ fournisseur
            $fournisseurLabel = $row[5];
            if (!empty($fournisseurLabel)) {
                $fournisseurRef = $row[6];
                if (in_array($fournisseurRef, ['nc', 'nd', 'NC', 'ND', '*', '.', ''])) {
                    $fournisseurRef = $fournisseurLabel;
                }
                $fournisseur = $this->fournisseurRepository->findOneBy(['codeReference' => $fournisseurRef]);

                // si le fournisseur n'existe pas, on le crée
                if (empty($fournisseur)) {
                    $fournisseur = new Fournisseur();
                    $fournisseur
                        ->setNom($fournisseurLabel)
                        ->setCodeReference($fournisseurRef);
                    $manager->persist($fournisseur);
                }


                // article fournisseur
                $articleFournisseur = new ArticleFournisseur();
                $articleFournisseur
                    ->setLabel($row[0])
                    ->setReference(time() . '-' . $i)// code aléatoire unique
                    ->setFournisseur($fournisseur)
                    ->setReferenceArticle($referenceArticle);

                $manager->persist($articleFournisseur);
            }


            // champs libres
            $listFields = [
                ['label' => 'adresse', 'col' => 2],
                ['label' => 'famille produit', 'col' => 4],
                ['label' => "stock mini", 'col' => 7],
                ['label' => "stock alerte", 'col' => 8],
                ['label' => "prix unitaire", 'col' => 9],
                ['label' => "date entrée", 'col' => 10],
                ['label' => "prix du stock final", 'col' => 11],
                ['label' => "alerte mini", 'col' => 12],
                ['label' => "alerte prévision", 'col' => 13],
            ];

            foreach($listFields as $field) {
                $vcl = new ValeurChampLibre();
                $label = $field['label'] . ' (' . $typeMob->getLabel() . ')';
                $cl = $this->champLibreRepository->findOneBy(['label' => $label]);
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
        return ['articlesMOB', 'articles'];
    }

}
