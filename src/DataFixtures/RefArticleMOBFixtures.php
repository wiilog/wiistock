<?php

namespace App\DataFixtures;

use App\Entity\ArticleFournisseur;
use App\Entity\Fournisseur;
use App\Entity\Type;
use App\Repository\FournisseurRepository;
use App\Repository\StatutRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

use App\Entity\ReferenceArticle;
use App\Repository\TypeRepository;
use App\Repository\ChampsLibreRepository;

class RefArticleMOBFixtures extends Fixture implements FixtureGroupInterface
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
     * @var FournisseurRepository
     */
    private $fournisseurRepository;


    public function __construct(UserPasswordEncoderInterface $encoder, TypeRepository $typeRepository, ChampsLibreRepository $champsLibreRepository, StatutRepository $statutRepository, FournisseurRepository $fournisseurRepository)
    {
        $this->typeRepository = $typeRepository;
        $this->champsLibreRepository = $champsLibreRepository;
        $this->encoder = $encoder;
        $this->statutRepository = $statutRepository;
        $this->fournisseurRepository = $fournisseurRepository;
    }

    public function load(ObjectManager $manager)
    {
        $file = fopen("C:\wamp64\www\WiiStock\public\csv\mob.csv", "r");
        $firstRow = true;
        while (($data = fgetcsv($file, 1000, ";")) !== false) {
            if ($firstRow) {
                $firstRow = false;
            } else {
                $data = array_map('utf8_encode', $data);
                dump(print_r($data));

                $fournisseurLabel = $data[4];
                $fournisseur = $this->fournisseurRepository->findOneBy(['nom' => $fournisseurLabel]);

                // si le fournisseur n'existe pas, on le crée
                if (empty($fournisseur)) {
                    $fournisseur = new Fournisseur();
                    $fournisseur
                        ->setNom($fournisseurLabel)
                        ->setCodeReference($fournisseurLabel);
                    $manager->persist($fournisseur);
                    $manager->flush();
                }

                $referenceArticle = new ReferenceArticle();
                $referenceArticle
                    ->setType($this->typeRepository->findOneBy(['label' => Type::LABEL_MOB]))
                    ->setReference($data[0])
                    ->setLibelle($data[0])
                    ->setQuantiteStock(intval($data[2]))
                    ->setTypeQuantite('reference')
                    ->setStatut($this->statutRepository->findOneByCategorieAndStatut(ReferenceArticle::CATEGORIE, ReferenceArticle::STATUT_ACTIF));

                $manager->persist($referenceArticle);
                $manager->flush();

                // on crée l'article fournisseur, on le lie au fournisseur et à l'article de référence
                $articleFournisseur = new ArticleFournisseur();
                $articleFournisseur
                    ->setLabel($data[0])
                    ->setReference(time())
                    ->setFournisseur($fournisseur)
                    ->setReferenceArticle($referenceArticle); // code aléatoire

                $manager->persist($articleFournisseur);
                $manager->flush();

            }
        }
        fclose($file);
    }

    public static function getGroups():array {
        return ['articles'];
    }

}