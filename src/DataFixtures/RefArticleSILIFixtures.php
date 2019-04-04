<?php

namespace App\DataFixtures;

use App\Entity\Type;
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

    public function __construct(UserPasswordEncoderInterface $encoder, TypeRepository $typeRepository, ChampsLibreRepository $champsLibreRepository, StatutRepository $statutRepository)
    {
        $this->typeRepository = $typeRepository;
        $this->champsLibreRepository = $champsLibreRepository;
        $this->encoder = $encoder;
        $this->statutRepository = $statutRepository;
    }

    public function load(ObjectManager $manager)
    {
        $file = fopen("C:\wamp64\www\WiiStock\public\csv\sili.csv", "r");
        $firstRow = true;
        while (($data = fgetcsv($file, 1000, ";")) !== false) {
            if ($firstRow) {
                $firstRow = false;
            } else {
                $data = array_map('utf8_encode', $data);
                dump(print_r($data));

                $referenceArticle = new ReferenceArticle();
                $referenceArticle
                    ->setType($this->typeRepository->findOneBy(['label' => Type::LABEL_SILI]))
                    ->setReference($data[0])
                    ->setLibelle($data[1])
                    ->setQuantiteStock(intval($data[3]))
                    ->setTypeQuantite('reference')
                    ->setStatut($this->statutRepository->findOneByCategorieAndStatut(ReferenceArticle::CATEGORIE, ReferenceArticle::STATUT_ACTIF));
                $manager->persist($referenceArticle);
                $manager->flush();
            }
        }
        fclose($file);
    }

    public static function getGroups():array {
        return ['articles'];
    }

}
