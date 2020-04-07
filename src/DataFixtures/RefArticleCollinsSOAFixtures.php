<?php

namespace App\DataFixtures;

use App\Entity\Statut;
use App\Entity\Type;
use App\Service\RefArticleDataService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\ReferenceArticle;


class RefArticleCollinsSOAFixtures extends Fixture implements FixtureGroupInterface
{

    /**
     * @var RefArticleDataService
     */
    private $articleDataService;
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager,
                                RefArticleDataService $articleDataService)
    {
        $this->entityManager = $entityManager;
        $this->articleDataService = $articleDataService;
    }

    public function load(ObjectManager $manager)
    {
        $path = "/root/refs-soa.csv";
        $file = fopen($path, "r");

        $typeRepository = $this->entityManager->getRepository(Type::class);
        $statutRepository = $this->entityManager->getRepository(Statut::class);
        $referenceArticleRepository = $this->entityManager->getRepository(ReferenceArticle::class);

        $typeStandard = $typeRepository->findOneBy(['label' => Type::LABEL_STANDARD]);
        $activeStatus = $statutRepository->findOneByCategorieNameAndStatutCode(ReferenceArticle::CATEGORIE, ReferenceArticle::STATUT_ACTIF);

        $alreadyAddedReference = array_map(function(ReferenceArticle $referenceArticle) {
            return $referenceArticle->getReference();
        }, $referenceArticleRepository->findAll());

        // supprime la 1è ligne d'en-têtes
        fgetcsv($file, 0, ";");
        $i = 0;
        $addedReferences = [];
        $barcodeCounter = 1;

        $rows = [];

        while (($row = fgetcsv($file, 0, ";")) !== false) {
            $row = array_map(function($cell) {
                return mb_convert_encoding($cell, 'CP850');
            }, $row);
            if (empty($row[0])) {
                continue;
            }
            $rows[] = $row;
        }

        fclose($file);

        $count = count($rows);
        foreach($rows as $row) {
            $reference = strtr($row[0],'àáâãäçèéêëìíîïñòóôõöùúûüýÿÀÁÂÃÄÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝ','aaaaaceeeeiiiinooooouuuuyyAAAAACEEEEIIIINOOOOOUUUUY');
            $libelle = $row[1];
            if (!in_array($reference, $alreadyAddedReference) &&
                !in_array($reference, $addedReferences)) {
                $stringCounter = sprintf("%08d", $barcodeCounter);
                $barcodeCounter++;

                $barcode = $this->articleDataService->generateBarCode($stringCounter);
                $addedReferences[] = $reference;
                $referenceArticle = new ReferenceArticle();
                $referenceArticle
                    ->setType($typeStandard)
                    ->setLibelle($libelle)
                    ->setReference($reference)
                    ->setBarCode($barcode)
                    ->setQuantiteStock(0)
                    ->setTypeQuantite(ReferenceArticle::TYPE_QUANTITE_ARTICLE)
                    ->setStatut($activeStatus);
                $this->entityManager->persist($referenceArticle);

                $i++;
            }

            if ($i === 1000) {
                $i = 0;
                $this->entityManager->flush();

                dump('===> Flush de 1000 références / ' . $count);
                $count -= 1000;
            }
        }


        if ($i > 0) {
            dump("===> Flush de {$i} référence(s) / " . $count);

            $this->entityManager->flush();
        }
    }

    public static function getGroups(): array
    {
        return ['refs-soa'];
    }

}
