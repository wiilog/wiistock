<?php

namespace App\DataFixtures;

use App\Repository\ArticleFournisseurRepository;
use App\Repository\CategorieCLRepository;
use App\Repository\EmplacementRepository;
use App\Repository\FournisseurRepository;
use App\Repository\ReferenceArticleRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

use App\Repository\ChampLibreRepository;
use App\Entity\Type;

class PatchRefArticlePDTPatchQuantiteFixtures extends Fixture implements FixtureGroupInterface
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


    public function __construct(ArticleFournisseurRepository $articleFournisseurRepository, EmplacementRepository $emplacementRepository, UserPasswordEncoderInterface $encoder, ChampLibreRepository $champLibreRepository, FournisseurRepository $fournisseurRepository, ReferenceArticleRepository $refArticleRepository, CategorieCLRepository $categorieCLRepository)
    {
        $this->champLibreRepository = $champLibreRepository;
        $this->encoder = $encoder;
        $this->fournisseurRepository = $fournisseurRepository;
        $this->refArticleRepository = $refArticleRepository;
        $this->categorieCLRepository = $categorieCLRepository;
        $this->emplacementRepository = $emplacementRepository;
        $this->articleFournisseurRepository = $articleFournisseurRepository;
    }

    public function load(ObjectManager $manager)
    {
        $typeRepository = $manager->getRepository(Type::class);
        $this->refArticleRepository->setQuantiteZeroForType($typeRepository->findOneByLabel(Type::LABEL_PDT));

        $path = "src/DataFixtures/Csv/pdt.csv";
        $file = fopen($path, "r");

        $rows = [];
        while (($data = fgetcsv($file, 1000, ";")) !== false) {
            $rows[] = array_map('utf8_encode', $data);
        }

        array_shift($rows);

        //$rows = array_slice($rows, 0, 100);

        $i = 1;
        foreach ($rows as $row) {
            if (empty($row[0])) continue;
            dump($i);
            $i++;

            // on récupère l'article de référence
            $referenceArticle = $this->refArticleRepository->findOneBy(['reference' => $row[0]]);

            if (empty($referenceArticle)) {
                dump('pas trouvé l\'article de réf ' . $row[0]);
            } else {
                $referenceArticle->setQuantiteStock(intval($row[3]));
            }
        }
        $manager->flush();

        fclose($file);
    }


    public static function getGroups(): array
    {
        return ['patchPDTQuantite'];
    }
}
