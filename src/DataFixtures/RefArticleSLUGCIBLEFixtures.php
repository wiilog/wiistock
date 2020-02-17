<?php

namespace App\DataFixtures;

use App\Entity\ArticleFournisseur;
use App\Entity\Fournisseur;
use App\Entity\Type;
use App\Repository\ArticleFournisseurRepository;
use App\Repository\CategorieCLRepository;
use App\Repository\FournisseurRepository;
use App\Repository\StatutRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

use App\Entity\ReferenceArticle;
use App\Entity\ValeurChampLibre;
use App\Repository\TypeRepository;
use App\Repository\ChampLibreRepository;

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
     * @var ChampLibreRepository
     */
    private $champLibreRepository;

    /**
     * @var StatutRepository
     */
    private $statutRepository;

    /**
     * @var FournisseurRepository
     */
    private $fournisseurRepository;

    /**
     * @var CategorieCLRepository
     */
    private $categorieCLRepository;

    /**
     * @var ArticleFournisseurRepository
     */
    private $articleFournisseurRepository;


    public function __construct(ArticleFournisseurRepository $articleFournisseurRepository, UserPasswordEncoderInterface $encoder, TypeRepository $typeRepository, ChampLibreRepository $champLibreRepository, StatutRepository $statutRepository, FournisseurRepository $fournisseurRepository, CategorieCLRepository $categorieCLRepository)
    {
        $this->typeRepository = $typeRepository;
        $this->champLibreRepository = $champLibreRepository;
        $this->encoder = $encoder;
        $this->statutRepository = $statutRepository;
        $this->fournisseurRepository = $fournisseurRepository;
        $this->categorieCLRepository = $categorieCLRepository;
        $this->articleFournisseurRepository = $articleFournisseurRepository;
    }

    public function load(ObjectManager $manager)
    {
        $path = "src/DataFixtures/Csv/slugcible.csv";
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

            $typeSlugcible = $this->typeRepository->findOneBy(['label' => Type::LABEL_SLUGCIBLE]);
            $statutActif = $this->statutRepository->findOneByCategorieNameAndStatutCode(ReferenceArticle::CATEGORIE, ReferenceArticle::STATUT_ACTIF);

            // champs fixes
            $referenceArticle = new ReferenceArticle();
            $referenceArticle
                ->setType($typeSlugcible)
                ->setReference($row[0])
                ->setLibelle($row[1])
                ->setQuantiteStock(intval($row[3]))
                ->setTypeQuantite('reference')
                ->setStatut($statutActif);
            $manager->persist($referenceArticle);


            // champ fournisseur
            $fournisseurLabel = $row[9];
            if (!empty($fournisseurLabel)) {
                $fournisseurRef = $row[10];
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
                ['label' => 'famille produit', 'col' => 4],
                ['label' => 'zone', 'col' => 5],
                ['label' => 'équipementier', 'col' => 6],
                ['label' => 'réf équipementier', 'col' => 7],
                ['label' => 'machine', 'col' => 8],
                ['label' => 'stock mini', 'col' => 11],
                ['label' => 'stock alerte', 'col' => 12],
                ['label' => 'prix unitaire', 'col' => 14],
                ['label' => 'date entrée', 'col' => 15],
                ['label' => 'prix du stock final', 'col' => 16],
                ['label' => 'alerte mini', 'col' => 17],
                ['label' => 'alerte prévision', 'col' => 18],
            ];

            foreach($listFields as $field) {
                $vcl = new ValeurChampLibre();
                $label = $field['label'] . ' (' . $typeSlugcible->getLabel() . ')';
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
        return ['articlesSLUGCIBLE', 'articles'];
    }

}
