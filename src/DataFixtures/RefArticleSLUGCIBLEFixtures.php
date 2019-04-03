<?php

namespace App\DataFixtures;

use App\Entity\ChampsLibre;
use App\Entity\Type;
use App\Repository\StatutRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

use App\Entity\ReferenceArticle;
use App\Entity\ValeurChampsLibre;
use App\Repository\TypeRepository;
use App\Repository\ChampsLibreRepository;

class RefArticleSLUGCIBLEFixtures extends Fixture
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
        $file = fopen("C:\wamp64\www\WiiStock\public\csv\slugcible.csv", "r");
        while (($data = fgetcsv($file, 1000, ";")) !== false) {
            $data = array_map('utf8_encode', $data);
            dump(print_r($data));

            $typeSlugcible = $this->typeRepository->findOneBy(['label' => Type::LABEL_SLUGCIBLE]);

            // champs fixes
            $referenceArticle = new ReferenceArticle();
            $referenceArticle
                ->setType($typeSlugcible)
                ->setReference($data[0]) //TODO où est la référence ??
                ->setLibelle($data[1])
                ->setQuantiteStock(intval($data[2]))
                ->setTypeQuantite('reference')
                ->setStatut($this->statutRepository->findOneByCategorieAndStatut(ReferenceArticle::CATEGORIE, ReferenceArticle::STATUT_ACTIF))
            ;
            $manager->persist($referenceArticle);
            $manager->flush();

            // champs libres
            $vcl = new ValeurChampsLibre();
            $cl = $this->champsLibreRepository->findOneBy(['label' => 'bénéficiaire ou n° commande']); //TODO label CL pas unique !!
            if (empty($cl)) {
                $cl = new ChampsLibre();
                $cl
                    ->setLabel('bénéficiaire ou n° commande')
                    ->setTypage(ChampsLibre::TYPE_TEXT)
                    ->setType($typeSlugcible)
                    ;
                $manager->persist($cl);
            }

            $vcl
                ->setChampLibre($cl)
                ->addArticleReference($referenceArticle)
                ->setValeur($data[7]);
            $manager->persist($vcl);
        }
        fclose($file);
    }
}
