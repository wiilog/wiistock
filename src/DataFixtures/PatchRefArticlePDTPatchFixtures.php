<?php

namespace App\DataFixtures;

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

use App\Entity\ValeurChampLibre;
use App\Repository\ChampLibreRepository;

class PatchRefArticlePDTPatchFixtures extends Fixture implements FixtureGroupInterface
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


    public function __construct(ArticleFournisseurRepository $articleFournisseurRepository, EmplacementRepository $emplacementRepository, UserPasswordEncoderInterface $encoder, ChampLibreRepository $champLibreRepository, FournisseurRepository $fournisseurRepository, StatutRepository $statutRepository, ReferenceArticleRepository $refArticleRepository, CategorieCLRepository $categorieCLRepository)
    {
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
        $rows = array_slice($rows, 0, 200);

        $i = 1;
        foreach($rows as $row) {
            if (empty($row[0])) continue;
            dump($i);
            $i++;

            // on récupère l'article de référence
            $referenceArticle = $this->refArticleRepository->findOneBy(['reference' => $row[0]]);

            if (empty($referenceArticle)) {
                dump('pas trouvé l\'article de réf ' . $row[0]);
            } else {
                // on alimente les champs libres
                $listFields = [
                    ['label' => "adresse", 'col' => 2],
                    ['label' => "prix unitaire", 'col' => 14],
                    ['label' => "date entrée", 'col' => 15],
                ];

                foreach ($listFields as $field) {
                    $vcl = new ValeurChampLibre();
                    $label = $field['label'] . ' (PDT)';
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
        }

        fclose($file);
    }


    public static function getGroups():array {
        return ['patchPDT'];
    }

}
