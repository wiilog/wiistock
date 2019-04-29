<?php

namespace App\DataFixtures;

use App\Entity\Article;
use App\Entity\ArticleFournisseur;
use App\Entity\ChampsLibre;
use App\Entity\Emplacement;
use App\Entity\Fournisseur;
use App\Entity\Type;
use App\Entity\ValeurChampsLibre;
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
use App\Repository\TypeRepository;
use App\Repository\ChampsLibreRepository;

class RefArticleSILIFixtures extends Fixture implements FixtureGroupInterface
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
     * @var FournisseurRepository
     */
    private $fournisseurRepository;

    public function __construct(FournisseurRepository $fournisseurRepository, EmplacementRepository $emplacementRepository, CategorieCLRepository $categorieCLRepository, ReferenceArticleRepository $refArticleRepository, UserPasswordEncoderInterface $encoder, TypeRepository $typeRepository, ChampsLibreRepository $champsLibreRepository, StatutRepository $statutRepository)
    {
        $this->typeRepository = $typeRepository;
        $this->champsLibreRepository = $champsLibreRepository;
        $this->encoder = $encoder;
        $this->statutRepository = $statutRepository;
        $this->refArticleRepository = $refArticleRepository;
        $this->categorieCLRepository = $categorieCLRepository;
        $this->emplacementRepository = $emplacementRepository;
        $this->fournisseurRepository = $fournisseurRepository;
    }

    public function load(ObjectManager $manager)
    {
        $path = "src/DataFixtures/Csv/sili.csv";
        $file = fopen($path, "r");

        $rows = [];
        while (($data = fgetcsv($file, 1000, ";")) !== false) {
            $rows[] = array_map('utf8_encode', $data);
        }

        $fournisseur = $this->initFournisseur($manager);

        array_shift($rows); // supprime la 1è ligne d'en-têtes
        $i = 1;
        foreach ($rows as $row) {
            if (empty($row[0])) continue;
            dump($i);
            $i++;
            $typeSili = $this->typeRepository->findOneBy(['label' => Type::LABEL_SILI]);
            $typeArticle = $this->typeRepository->findOneBy(['label' => Type::LABEL_ARTICLE]);

            // si l'article de référence n'existe pas déjà, on le crée
            $referenceArticle = $this->refArticleRepository->findOneBy(['reference' => $row[0]]);
            if (empty($referenceArticle)) {
                // champs fixes
                $referenceArticle = new ReferenceArticle();
                $referenceArticle
                    ->setType($typeSili)
                    ->setStatut($this->statutRepository->findOneByCategorieAndStatut(ReferenceArticle::CATEGORIE, ReferenceArticle::STATUT_ACTIF))
                    ->setReference($row[0])
                    ->setLibelle($row[1])
                    ->setTypeQuantite(ReferenceArticle::TYPE_QUANTITE_ARTICLE);
                $manager->persist($referenceArticle);
                $manager->flush();

                // on crée l'article fournisseur, on le lie au fournisseur et à l'article de référence
                $articleFournisseur = new ArticleFournisseur();
                $articleFournisseur
                    ->setLabel($row[1])
                    ->setReference(time() . '-' . $i)// code aléatoire unique
                    ->setFournisseur($fournisseur)
                    ->setReferenceArticle($referenceArticle);

                $manager->persist($articleFournisseur);


                // champs libres
                $listFields = [
                    ['label' => 'famille produit', 'col' => 4, 'type' => ChampsLibre::TYPE_LIST, 'elements' => ['CONSOMMABLES','PAD','POMPE','POMPE_41', 'PIECES DETACHEES', 'PDT GENERIQUE', 'DCOS TEST ELECTRIQUE', 'SILICIUM', 'SIL_EXTERNE', 'SIL_INTERNE', 'MOBILIER SB', 'MOBILIER TERTIAIRE', 'CIBLE / SLUGS']],
                    ['label' => "alerte mini", 'col' => 13, 'type' => ChampsLibre::TYPE_LIST, 'elements' => ['besoin', '']],
                    ['label' => "alerte prévision", 'col' => 14, 'type' => ChampsLibre::TYPE_NUMBER],
                ];


                foreach ($listFields as $field) {
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
                        ->setValeur($row[$field['col']]);
                    $manager->persist($vcl);
                }
                $manager->flush();
            }

            // on crée l'article
            $article = new Article();
            $article
                ->setReference($row[0] . '-' . $i)
                ->setLabel($row[1])
                ->setStatut($this->statutRepository->findOneByCategorieAndStatut(Article::CATEGORIE, Article::STATUT_ACTIF))
                ->setType($typeArticle)
                ->setConform(true)
                ->setQuantite(intval($row[3]));

            // on crée l'article fournisseur, on le lie au fournisseur et à l'article de référence
            $artFourn = new ArticleFournisseur();
            $artFourn
                ->setLabel($row[1])
                ->setReference(time() . '-' . $i)// code aléatoire unique
                ->setFournisseur($fournisseur)
                ->setReferenceArticle($referenceArticle);
            $manager->persist($artFourn);

            // on lie l'article à l'article fournisseur
            $article->setArticleFournisseur($artFourn);


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
                ['label' => 'famille produit', 'col' => 4, 'type' => ChampsLibre::TYPE_LIST, 'elements' => ['CONSOMMABLES','PAD','POMPE','POMPE_41', 'PIECES DETACHEES', 'PDT GENERIQUE', 'DCOS TEST ELECTRIQUE', 'SILICIUM', 'SIL_EXTERNE', 'SIL_INTERNE', 'MOBILIER SB', 'MOBILIER TERTIAIRE', 'CIBLE / SLUGS']],
                ['label' => "alerte mini", 'col' => 13, 'type' => ChampsLibre::TYPE_LIST, 'elements' => ['besoin', '']],
                ['label' => "alerte prévision", 'col' => 14, 'type' => ChampsLibre::TYPE_NUMBER],
            ];

            foreach ($listFields as $field) {
                $vcl = new ValeurChampsLibre();
                $cl = $this->champsLibreRepository->findOneBy(['label' => $field['label']]);
                if (empty($cl)) {
                    $cl = new ChampsLibre();
                    $cl
                        ->setLabel($field['label'])
                        ->setTypage($field['type'])
                        ->setCategorieCL($this->categorieCLRepository->findOneByLabel(CategorieCL::ARTICLE))
                        ->setType($typeArticle);

                    if ($field['type'] == ChampsLibre::TYPE_LIST) {
                        $cl->setElements($field['elements']);
                    }
                    $manager->persist($cl);
                }
                $vcl
                    ->setChampLibre($cl)
                    ->addArticle($article)
                    ->setValeur($row[$field['col']]);
                $manager->persist($vcl);
            }

            $manager->flush();
        }
        fclose($file);
    }

    public static function getGroups():array {
        return ['articlesSILI', 'articles'];
    }

    /**
     * @param ObjectManager $manager
     * @return Fournisseur
     */
    public function initFournisseur(ObjectManager $manager): Fournisseur
    {
        $fournisseurLabel = 'A DETERMINER';
        $fournisseurRef = 'A_DETERMINER';
        $fournisseur = $this->fournisseurRepository->findOneBy(['nom' => $fournisseurLabel]);

        // si le fournisseur n'existe pas, on le crée
        if (empty($fournisseur)) {
            $fournisseur = new Fournisseur();
            $fournisseur
                ->setNom($fournisseurLabel)
                ->setCodeReference($fournisseurRef);
            $manager->persist($fournisseur);
            $manager->flush();
        }
        return $fournisseur;
    }

}
