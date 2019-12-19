<?php

namespace App\DataFixtures;

use App\Entity\ArticleFournisseur;
use App\Entity\CategorieCL;
use App\Entity\ChampLibre;
use App\Entity\Fournisseur;
use App\Entity\Type;
use App\Entity\ValeurChampLibre;
use App\Repository\CategorieCLRepository;
use App\Repository\FournisseurRepository;
use App\Repository\StatutRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

use App\Entity\ReferenceArticle;
use App\Repository\TypeRepository;
use App\Repository\ChampLibreRepository;

class RefArticleCollinsFixtures extends Fixture implements FixtureGroupInterface
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
        $path = "src/DataFixtures/Csv/refs-collins.csv";
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
            $typeStandart = $this->typeRepository->findOneBy(['label' => Type::LABEL_STANDARD]);
            $referenceArticle = new ReferenceArticle();
            $referenceArticle
                ->setType($typeStandart)
                ->setLibelle($row[1])
                ->setReference($row[0])
                ->setQuantiteStock(0)
                ->setTypeQuantite('article')
                ->setStatut($this->statutRepository->findOneByCategorieNameAndStatutName(ReferenceArticle::CATEGORIE, ReferenceArticle::STATUT_ACTIF));
            $manager->persist($referenceArticle);
            $manager->flush();
        }
        fclose($file);
    }

    public static function getGroups():array {
        return ['collins-ref'];
    }

}
