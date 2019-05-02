<?php

namespace App\DataFixtures;

use App\Entity\ArticleFournisseur;
use App\Entity\CategorieCL;
use App\Entity\ChampsLibre;
use App\Entity\Fournisseur;
use App\Entity\Type;
use App\Repository\CategorieCLRepository;
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

    /**
     * @var CategorieCLRepository
     */
    private $categorieCLRepository;


    public function __construct(UserPasswordEncoderInterface $encoder, TypeRepository $typeRepository, ChampsLibreRepository $champsLibreRepository, StatutRepository $statutRepository, FournisseurRepository $fournisseurRepository, CategorieCLRepository $categorieCLRepository)
    {
        $this->typeRepository = $typeRepository;
        $this->champsLibreRepository = $champsLibreRepository;
        $this->encoder = $encoder;
        $this->statutRepository = $statutRepository;
        $this->fournisseurRepository = $fournisseurRepository;
        $this->categorieCLRepository = $categorieCLRepository;
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
                $fournisseur = $this->fournisseurRepository->findOneBy(['codeReference' => $fournisseurRef]);

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
                    ->setReference(time() . '-' . $i)// code aléatoire unique
                    ->setFournisseur($fournisseur)
                    ->setReferenceArticle($referenceArticle);

                $manager->persist($articleFournisseur);
            }


            // champs libres
            $listFields = [
                ['label' => 'bénéficiaire ou n° commande', 'col' => 7],
                ['label' => 'machine', 'col' => 9],
                ['label' => 'stock mini', 'col' => 13],
                ['label' => 'date entrée', 'col' => 15],
            ];

            foreach($listFields as $field) {
                $vcl = new ValeurChampsLibre();
                $label = $field['label'] . '(' . $typeSlugcible->getLabel() . ')';
                $cl = $this->champsLibreRepository->findOneBy(['label' => $label]);
                if (empty($cl)) {
                    dump('il manque le champ libre de label' . $field['label']);
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
