<?php

namespace App\DataFixtures;

use App\Entity\Type;
use App\Entity\ValeurChampLibre;
use App\Repository\CategorieCLRepository;
use App\Repository\StatutRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

use App\Entity\ReferenceArticle;
use App\Repository\TypeRepository;
use App\Repository\ChampLibreRepository;

class RefArticleSILIIntFixtures extends Fixture implements FixtureGroupInterface
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
     * @var StatutRepository
     */
    private $statutRepository;

    /**
     * @var CategorieCLRepository
     */
    private $categorieCLRepository;

    public function __construct(CategorieCLRepository $categorieCLRepository, UserPasswordEncoderInterface $encoder, TypeRepository $typeRepository, ChampLibreRepository $champLibreRepository, StatutRepository $statutRepository)
    {
        $this->typeRepository = $typeRepository;
        $this->champLibreRepository = $champLibreRepository;
        $this->encoder = $encoder;
        $this->statutRepository = $statutRepository;
        $this->categorieCLRepository = $categorieCLRepository;
    }

    public function load(ObjectManager $manager)
    {
        $path = "src/DataFixtures/Csv/sili-int.csv";
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
            $typeSiliInt = $this->typeRepository->findOneBy(['label' => Type::LABEL_SILI_INT]);

            // contruction référence
            $referenceNum = str_pad($i, 5, '0', STR_PAD_LEFT);

            // champs fixes
            $referenceArticle = new ReferenceArticle();
            $referenceArticle
                ->setType($typeSiliInt)
                ->setReference('SILI_INT_' . $referenceNum)
                ->setLibelle($row[1])
                ->setQuantiteStock(1)
                ->setTypeQuantite('reference')
                ->setStatut($this->statutRepository->findOneByCategorieNameAndStatutName(ReferenceArticle::CATEGORIE, ReferenceArticle::STATUT_ACTIF));
            $manager->persist($referenceArticle);
            $manager->flush();


            // champs libres
            $listFields = [
                ['label' => 'adresse', 'col' => 0],
                ['label' => 'famille produit', 'col' => 2],
                ['label' => 'date', 'col' => 3],
                ['label' => 'diamètre', 'col' => 4],
                ['label' => 'n° lot autre', 'col' => 5],
                ['label' => 'n° lot Léti', 'col' => 6],
                ['label' => "demandeur", 'col' => 7],
                ['label' => "projet 3", 'col' => 8],
//                ['label' => "date de retour en salle ou d'envoi à Crolles ou autre", 'col' => 9],
                ['label' => "commentaire", 'col' => 10],
                ['label' => "mois de stock", 'col' => 11],
            ];


            foreach($listFields as $field) {
                $vcl = new ValeurChampLibre();
                $label = $field['label'] . ' (' . $typeSiliInt->getLabel() . ')';
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
        return ['siliint'];
    }

}
