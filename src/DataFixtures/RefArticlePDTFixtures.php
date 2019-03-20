<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

use App\Entity\ReferenceArticle;
use App\Entity\ValeurChampsLibre;
use App\Repository\TypeRepository;
use App\Repository\ChampsLibreRepository;
use App\Entity\ChampsLibre;

class RefArticlePDTFixtures extends Fixture
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

    public function __construct(UserPasswordEncoderInterface $encoder, TypeRepository $typeRepository, ChampsLibreRepository $champsLibreRepository)
    {
        $this->typeRepository = $typeRepository;
        $this->champsLibreRepository = $champsLibreRepository;
        $this->encoder = $encoder;
    }

    public function load(ObjectManager $manager)
    {
        $file = fopen("C:\wamp64\www\WiiStock\public\csv\pdt.csv", "r");
        while (($data = fgetcsv($file, 1000, ";")) !== false) {
            dump(print_r(fgetcsv($file, 1000, ";")));
            $k = (fgetcsv($file, 1000, ";"));

            $referenceArticle = new ReferenceArticle();
            $referenceArticle
                ->setType($this->typeRepository->find(3))
                ->setReference($data[0])
                ->setQuantiteStock(intval($data[3]))
                ->setLibelle($data[1]);
            $manager->persist($referenceArticle);
            $manager->flush();

            $adresse = new ValeurChampsLibre();
            $CLAdresse = $this->champsLibreRepository->findOneBy(['label' => 'adresse']);
            $adresse
                ->setChampLibre($CLAdresse)
                ->addArticleReference($referenceArticle)
                ->setValeur($data[2]);
            $manager->persist($adresse);

            dump('hello');

            $dateEntre = new ValeurChampsLibre();
            $CLDateEntre = $this->champsLibreRepository->findOneBy(['label' => "Date d'entrée"]);
            $dateEntre
                ->setChampLibre($CLDateEntre)
                ->addArticleReference($referenceArticle)
                ->setValeur($data[14]);
            $manager->persist($dateEntre);

            $equipementier = new ValeurChampsLibre();
            $DLequipementier = $this->champsLibreRepository->findOneBy(['label' => "Equipementier"]);
            $equipementier
                ->setChampLibre($DLequipementier)
                ->addArticleReference($referenceArticle)
                ->setValeur($data[6]);
            $manager->persist($equipementier);

            $familleProduit = new ValeurChampsLibre();
            $DLfamilleProduit = $this->champsLibreRepository->findOneBy(['label' => "famille produit"]);
            $familleProduit
                ->setChampLibre($DLfamilleProduit)
                ->addArticleReference($referenceArticle)
                ->setValeur($data[4]);
            $manager->persist($familleProduit);

            $fournisseur = new ValeurChampsLibre();
            $DLfournisseur = $this->champsLibreRepository->findOneBy(['label' => "fournisseur"]);
            $fournisseur
                ->setChampLibre($DLfournisseur)
                ->addArticleReference($referenceArticle)
                ->setValeur($data[9]);
            $manager->persist($fournisseur);

            $machine = new ValeurChampsLibre();
            $DLMachine = $this->champsLibreRepository->findOneBy(['label' => "Machine"]);
            $machine
                ->setChampLibre($DLMachine)
                ->addArticleReference($referenceArticle)
                ->setValeur($data[8]);
            $manager->persist($machine);

            $lot = new ValeurChampsLibre();
            $DLlot = $this->champsLibreRepository->findOneBy(['label' => "No lot"]);
            $lot
                ->setChampLibre($DLlot)
                ->addArticleReference($referenceArticle)
                ->setValeur($data[13]);
            $manager->persist($lot);

            $refEquip = new ValeurChampsLibre();
            $DLrefEquip = $this->champsLibreRepository->findOneBy(['label' => "R+H1:I9505ef équipementier"]);
            $refEquip
                ->setChampLibre($DLrefEquip)
                ->addArticleReference($referenceArticle)
                ->setValeur($data[7]);
            $manager->persist($refEquip);

            $refFournisseur = new ValeurChampsLibre();
            $DLrefFournisseur = $this->champsLibreRepository->findOneBy(['label' => "Ref Fournisseur"]);
            $refFournisseur
                ->setChampLibre($DLrefFournisseur)
                ->addArticleReference($referenceArticle)
                ->setValeur($data[10]);
            $manager->persist($refFournisseur);

            $zone = new ValeurChampsLibre();
            $DLzone = $this->champsLibreRepository->findOneBy(['label' => "zone"]);
            $zone
                ->setChampLibre($DLzone)
                ->addArticleReference($referenceArticle)
                ->setValeur($data[5]);
            $manager->persist($zone);

            $manager->flush();

            unset($CLAdresse, $adresse, $refEquip, $DLrefEquip, $refFournisseur, $referenceArticle, $CLDateEntre, $DLMachine,
            $DLequipementier, $equipementier, $DLfamilleProduit, $DLlot, $lot, $DLrefFournisseur, $DLzone, $zone, $familleProduit, $machine);
        }
        fclose($file);
    }
}
