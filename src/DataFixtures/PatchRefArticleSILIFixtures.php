<?php

namespace App\DataFixtures;

use App\Entity\ArticleFournisseur;
use App\Entity\Fournisseur;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\ValeurChampLibre;
use App\Repository\ArticleFournisseurRepository;
use App\Repository\CategorieCLRepository;
use App\Repository\EmplacementRepository;
use App\Repository\FournisseurRepository;
use App\Repository\ReferenceArticleRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

use App\Entity\ReferenceArticle;
use App\Repository\ChampLibreRepository;

class PatchRefArticleSILIFixtures extends Fixture implements FixtureGroupInterface
{
    private $encoder;

    /**
     * @var ChampLibreRepository
     */
    private $champLibreRepository;

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

    /**
     * @var ArticleFournisseurRepository
     */
    private $articleFournisseurRepository;

    public function __construct(ArticleFournisseurRepository $articleFournisseurRepository, FournisseurRepository $fournisseurRepository, EmplacementRepository $emplacementRepository, CategorieCLRepository $categorieCLRepository, ReferenceArticleRepository $refArticleRepository, UserPasswordEncoderInterface $encoder, ChampLibreRepository $champLibreRepository)
    {
        $this->champLibreRepository = $champLibreRepository;
        $this->encoder = $encoder;
        $this->refArticleRepository = $refArticleRepository;
        $this->categorieCLRepository = $categorieCLRepository;
        $this->emplacementRepository = $emplacementRepository;
        $this->fournisseurRepository = $fournisseurRepository;
        $this->articleFournisseurRepository = $articleFournisseurRepository;
    }

    public function load(ObjectManager $manager)
    {
        $statutRepository = $manager->getRepository(Statut::class);

        $path = "src/DataFixtures/Csv/sili.csv";
        $file = fopen($path, "r");

        $rows = [];
        while (($data = fgetcsv($file, 1000, ";")) !== false) {
            $rows[] = array_map('utf8_encode', $data);
        }

        $fournisseur = $this->initFournisseur($manager);
        $typeRepository = $manager->getRepository(Type::class);

        array_shift($rows); // supprime la 1è ligne d'en-têtes
        $i = 1;
        foreach ($rows as $row) {
            if (empty($row[0])) continue;
            dump($i);
            $i++;
            $typeSili = $typeRepository->findOneBy(['label' => Type::LABEL_SILI]);

            // si l'article de référence existe déjà on le dédoublonne
            $referenceArticle = $this->refArticleRepository->findOneBy(['reference' => $row[0]]);
            if (!empty($referenceArticle)) {
                $row[0] = $row[0] . '-' . $i;
            }

            // champs fixes
            $referenceArticle = new ReferenceArticle();
            $referenceArticle
                ->setType($typeSili)
                ->setStatut($statutRepository->findOneByCategorieNameAndStatutCode(ReferenceArticle::CATEGORIE, ReferenceArticle::STATUT_ACTIF))
                ->setReference($row[0])
                ->setQuantiteStock($row[3])
                ->setLibelle($row[1])
                ->setTypeQuantite(ReferenceArticle::TYPE_QUANTITE_REFERENCE);
            $manager->persist($referenceArticle);
            $manager->flush();


            // article fournisseur
            $articleFournisseur = new ArticleFournisseur();
            $articleFournisseur
                ->setLabel($row[1])
                ->setReference(time() . '-' . $i)// code aléatoire unique
                ->setFournisseur($fournisseur)
                ->setReferenceArticle($referenceArticle);

            $manager->persist($articleFournisseur);


            // champs libres
            $listFields = [
                ['label' => 'adresse', 'col' => 2],
                ['label' => 'famille produit', 'col' => 4],
                ['label' => "alerte mini", 'col' => 13],
                ['label' => "alerte prévision", 'col' => 14],
            ];

            foreach ($listFields as $field) {
                $vcl = new ValeurChampLibre();
                $label = $field['label'] . ' (' . $typeSili->getLabel() . ')';
                $cl = $this->champsLibreRepository->findOneBy(['label' => $label]);
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
        $fournisseur = $this->fournisseurRepository->findOneBy(['codeReference' => $fournisseurRef]);

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
