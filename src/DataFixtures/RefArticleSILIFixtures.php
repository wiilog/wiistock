<?php

namespace App\DataFixtures;

use App\Entity\Article;
use App\Entity\ChampsLibre;
use App\Entity\Emplacement;
use App\Entity\Type;
use App\Entity\ValeurChampsLibre;
use App\Repository\CategorieCLRepository;
use App\Repository\EmplacementRepository;
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

    public function __construct(EmplacementRepository $emplacementRepository, CategorieCLRepository $categorieCLRepository, ReferenceArticleRepository $refArticleRepository, UserPasswordEncoderInterface $encoder, TypeRepository $typeRepository, ChampsLibreRepository $champsLibreRepository, StatutRepository $statutRepository)
    {
        $this->typeRepository = $typeRepository;
        $this->champsLibreRepository = $champsLibreRepository;
        $this->encoder = $encoder;
        $this->statutRepository = $statutRepository;
        $this->refArticleRepository = $refArticleRepository;
        $this->categorieCLRepository = $categorieCLRepository;
        $this->emplacementRepository = $emplacementRepository;
    }

    public function load(ObjectManager $manager)
    {
        $path = "public/csv/sili.csv";
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
        return ['articlesSILI'];
    }

}
