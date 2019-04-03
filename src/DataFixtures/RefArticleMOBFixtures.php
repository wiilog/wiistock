<?php

namespace App\DataFixtures;

use App\Entity\Type;
use App\Repository\StatutRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

use App\Entity\ReferenceArticle;
use App\Repository\TypeRepository;
use App\Repository\ChampsLibreRepository;

class RefArticleMOBFixtures extends Fixture
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
        $file = fopen("C:\wamp64\www\WiiStock\public\csv\mob.csv", "r");
        while (($data = fgetcsv($file, 1000, ";")) !== false) {
            $data = array_map('utf8_encode', $data);
            dump(print_r($data));

            $referenceArticle = new ReferenceArticle();
            $referenceArticle
                ->setType($this->typeRepository->findOneBy(['label' => Type::LABEL_MOB]))
                ->setReference() //TODO où est la référence ??
                ->setLibelle($data[0])
                ->setQuantiteStock(intval($data[2]))
                ->setTypeQuantite('reference')
                ->setStatut($this->statutRepository->findOneByCategorieAndStatut(ReferenceArticle::CATEGORIE, ReferenceArticle::STATUT_ACTIF))
            ;
            //TODO ajouter fournisseur ? (récup liste distinct et la traiter après ?)
            $manager->persist($referenceArticle);
            $manager->flush();
        }
        fclose($file);
    }
}
