<?php

namespace App\DataFixtures;

use App\Entity\Article;
use App\Entity\ArticleFournisseur;
use App\Entity\Emplacement;
use App\Entity\Fournisseur;
use App\Entity\Type;
use App\Repository\ArticleFournisseurRepository;
use App\Repository\CategorieCLRepository;
use App\Repository\EmplacementRepository;
use App\Repository\FournisseurRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\StatutRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

use App\Entity\ReferenceArticle;
use App\Entity\ValeurChampLibre;
use App\Repository\TypeRepository;
use App\Repository\ChampLibreRepository;

class RefArticlePDTFixtures extends Fixture implements FixtureGroupInterface
{
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
     * @var FournisseurRepository
     */
    private $fournisseurRepository;

    /**
     * @var StatutRepository
     */
    private $statutRepository;

    /**
     * @var ReferenceArticleRepository
     */
    private $refArticleRepository;

    /**
     * @var CategorieCLRepository
     */
    private $categorieCLRepository;

    /**
     * @var EmplacementRepository
     */
    private $emplacementRepository;

    /**
     * @var ArticleFournisseurRepository
     */
    private  $articleFournisseurRepository;


    public function __construct(ArticleFournisseurRepository $articleFournisseurRepository, EmplacementRepository $emplacementRepository, UserPasswordEncoderInterface $encoder, TypeRepository $typeRepository, ChampLibreRepository $champLibreRepository, FournisseurRepository $fournisseurRepository, StatutRepository $statutRepository, ReferenceArticleRepository $refArticleRepository, CategorieCLRepository $categorieCLRepository)
    {
        $this->typeRepository = $typeRepository;
        $this->champLibreRepository = $champLibreRepository;
        $this->encoder = $encoder;
        $this->fournisseurRepository = $fournisseurRepository;
        $this->statutRepository = $statutRepository;
        $this->refArticleRepository = $refArticleRepository;
        $this->categorieCLRepository = $categorieCLRepository;
        $this->emplacementRepository = $emplacementRepository;
        $this->articleFournisseurRepository = $articleFournisseurRepository;
    }

    public function load(ObjectManager $manager)
    {
        $path = "src/DataFixtures/Csv/pdt.csv";
        $file = fopen($path, "r");

        $rows = [];
        while (($data = fgetcsv($file, 1000, ";")) !== false) {
            $rows[] = array_map('utf8_encode', $data);
        }

        array_shift($rows); // supprime la 1è ligne d'en-têtes

        // à modifier pour faire imports successifs
        $rows = array_slice($rows, 0, 1000);

        $i = 1;
        foreach($rows as $row) {
            if (empty($row[0])) continue;
            dump($i);
            $i++;
            $typePdt = $this->typeRepository->findOneBy(['label' => Type::LABEL_PDT]);

            // si l'article de référence n'existe pas déjà, on le crée
            $referenceArticle = $this->refArticleRepository->findOneBy(['reference' => $row[0]]);
            if (empty($referenceArticle)) {
                // champs fixes
                $referenceArticle = new ReferenceArticle();
                $referenceArticle
                    ->setType($typePdt)
                    ->setStatut($this->statutRepository->findOneByCategorieNameAndStatutCode(ReferenceArticle::CATEGORIE, ReferenceArticle::STATUT_ACTIF))
                    ->setReference($row[0])
                    ->setLibelle($row[1])
                    ->setTypeQuantite(ReferenceArticle::TYPE_QUANTITE_ARTICLE);
                $manager->persist($referenceArticle);
                $manager->flush();
            }

            // champ fournisseur
            $fournisseurLabel = $row[9];
            if (empty($fournisseurLabel)) {
                $fournisseurLabel = 'A DETERMINER';
                $fournisseurRef = 'A_DETERMINER';
            } else {
                $fournisseurRef = $row[10];
            }

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

            // on crée l'article fournisseur et on le lie au fournisseur et à l'article de référence
            $articleFournisseur = new ArticleFournisseur();
            $articleFournisseur
                ->setLabel($row[1])
                ->setReference(time() . '-' . $i)// code aléatoire unique
                ->setFournisseur($fournisseur)
                ->setReferenceArticle($referenceArticle);

            $manager->persist($articleFournisseur);


            // champs libres
            $listFields = [
                ['label' => 'famille produit', 'col' => 4],
                ['label' => 'zone', 'col' => 5],
                ['label' => 'équipementier', 'col' => 6],
                ['label' => "réf équipementier", 'col' => 7],
                ['label' => "machine", 'col' => 8],
                ['label' => "stock mini", 'col' => 11],
                ['label' => "stock alerte", 'col' => 12],
                ['label' => "prix du stock final", 'col' => 16],
                ['label' => "alerte mini", 'col' => 17],
                ['label' => "alerte prévision", 'col' => 18],
            ];

            foreach ($listFields as $field) {
                $vcl = new ValeurChampLibre();
                $label = $field['label'] . ' (' . $typePdt->getLabel() . ')';
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


            // on crée l'article
            $article = new Article();
            $article
                ->setReference($row[0] . '-' . $i)
                ->setLabel($row[1])
                ->setStatut($this->statutRepository->findOneByCategorieNameAndStatutCode(Article::CATEGORIE, Article::STATUT_ACTIF))
                ->setType($typePdt)
                ->setConform(true)
                ->setQuantite(intval($row[3]));

            // champ fournisseur
            $fournisseurLabel = $row[9];
            if (empty($fournisseurLabel)) {
                $fournisseurLabel = 'A DETERMINER';
                $fournisseurRef = 'A_DETERMINER';
            } else {
                $fournisseurRef = $row[10];
            }

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

            // on lie l'article à l'article fournisseur
            $article->setArticleFournisseur($articleFournisseur);

            // champ emplacement
            $emplacementLabel = $row[2];
            if (!empty($emplacementLabel)) {
                $emplacement = $this->emplacementRepository->findOneBy(['label' => $emplacementLabel]);

                // si l'emplacement n'existe pas, on le crée
                if (empty($emplacement)) {
                    $emplacement = new Emplacement();
                    $emplacement->setLabel($emplacementLabel);
                    $manager->persist($emplacement);
                }

                $article->setEmplacement($emplacement);
            }
            $manager->persist($article);

            // champs libres
            $listFields = [
                ['label' => "prix unitaire", 'col' => 14],
                ['label' => "date entrée", 'col' => 15],
            ];

            foreach ($listFields as $field) {
                $vcl = new ValeurChampLibre();
                $label = $field['label'] . ' (' . $typePdt->getLabel() . ')';
                $cl = $this->champLibreRepository->findOneBy(['label' => $label]);
                if (empty($cl)) {
                    dump('il manque le champ libre de label ' . $label);
                } else {
                    $vcl
                        ->setChampLibre($cl)
                        ->addArticle($article)
                        ->setValeur($row[$field['col']]);
                    $manager->persist($vcl);
                }
            }

            $manager->flush();
        }

        fclose($file);
    }


    public static function getGroups():array {
        return ['articlesPDT'];
    }

}
