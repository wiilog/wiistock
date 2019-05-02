<?php

namespace App\DataFixtures;

use App\Entity\CategorieCL;
use App\Entity\ChampsLibre;
use App\Entity\Type;
use App\Repository\CategorieCLRepository;
use App\Repository\EmplacementRepository;
use App\Repository\FournisseurRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\StatutRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

use App\Repository\TypeRepository;
use App\Repository\ChampsLibreRepository;

class ChampsLibresFixtures extends Fixture implements FixtureGroupInterface
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


    public function __construct(EmplacementRepository $emplacementRepository, UserPasswordEncoderInterface $encoder, TypeRepository $typeRepository, ChampsLibreRepository $champsLibreRepository, FournisseurRepository $fournisseurRepository, StatutRepository $statutRepository, ReferenceArticleRepository $refArticleRepository, CategorieCLRepository $categorieCLRepository)
    {
        $this->typeRepository = $typeRepository;
        $this->champsLibreRepository = $champsLibreRepository;
        $this->encoder = $encoder;
        $this->fournisseurRepository = $fournisseurRepository;
        $this->statutRepository = $statutRepository;
        $this->refArticleRepository = $refArticleRepository;
        $this->categorieCLRepository = $categorieCLRepository;
        $this->emplacementRepository = $emplacementRepository;
    }

    public function load(ObjectManager $manager)
    {
        $listFieldsRefArticlePDT = [
            ['label' => 'famille produit', 'type' => ChampsLibre::TYPE_LIST, 'elements' => ['POMPE', 'POMPE_41', 'PIECES DETACHEES', 'PDT GENERIQUE', 'DCOS TEST ELECTRIQUE']],
            ['label' => 'zone', 'type' => ChampsLibre::TYPE_TEXT],
            ['label' => 'équipementier', 'type' => ChampsLibre::TYPE_TEXT],
            ['label' => "réf équipementier", 'type' => ChampsLibre::TYPE_TEXT],
            ['label' => "machine", 'type' => ChampsLibre::TYPE_TEXT],
            ['label' => "stock mini", 'type' => ChampsLibre::TYPE_NUMBER],
            ['label' => "stock alerte", 'type' => ChampsLibre::TYPE_NUMBER],
            ['label' => "prix du stock final", 'type' => ChampsLibre::TYPE_TEXT],
            ['label' => "alerte mini", 'type' => ChampsLibre::TYPE_LIST, 'elements' => ['besoin', '']],
            ['label' => "alerte prévision", 'type' => ChampsLibre::TYPE_NUMBER],
        ];
        $listFieldsArticlePDT = [
            ['label' => "prix unitaire", 'type' => ChampsLibre::TYPE_TEXT],
            ['label' => "date entrée", 'type' => ChampsLibre::TYPE_DATE],
        ];
        $listFieldsRefArticleCSP = [
            ['label' => 'famille produit', 'type' => ChampsLibre::TYPE_LIST, 'elements' => ['CONSOMMABLES', 'PAD']],
            ['label' => "stock mini", 'type' => ChampsLibre::TYPE_NUMBER],
            ['label' => "stock alerte", 'type' => ChampsLibre::TYPE_NUMBER],
            ['label' => "prix du stock final", 'type' => ChampsLibre::TYPE_DATE],
            ['label' => "alerte mini", 'type' => ChampsLibre::TYPE_LIST, 'elements' => ['besoin', '']],
            ['label' => "alerte prévision", 'type' => ChampsLibre::TYPE_NUMBER],
        ];
        $listFieldsArticleCSP = [
            ['label' => "date entrée", 'type' => ChampsLibre::TYPE_DATE],
            ['label' => "prix unitaire", 'type' => ChampsLibre::TYPE_TEXT],
            ['label' => "péremptions", 'type' => ChampsLibre::TYPE_DATE],
        ];
        $listFieldsSILI = [
            ['label' => 'adresse', 'type' => ChampsLibre::TYPE_TEXT],
            ['label' => 'famille produit', 'type' => ChampsLibre::TYPE_LIST, 'elements' => ['SILICIUM', 'SIL_EXTERNE', 'SIL_INTERNE']],
            ['label' => "alerte mini", 'type' => ChampsLibre::TYPE_LIST, 'elements' => ['besoin', '']],
            ['label' => "alerte prévision", 'type' => ChampsLibre::TYPE_NUMBER],
            ['label' => 'date', 'type' => ChampsLibre::TYPE_DATE],
            ['label' => "projet", 'type' => ChampsLibre::TYPE_TEXT],
            ['label' => "demandeur", 'type' => ChampsLibre::TYPE_TEXT],
            ['label' => "date fin de projet", 'type' => ChampsLibre::TYPE_DATE],
            ['label' => "lot", 'type' => ChampsLibre::TYPE_TEXT],
            ['label' => "sortie", 'type' => ChampsLibre::TYPE_NUMBER],
            ['label' => "commentaire", 'type' => ChampsLibre::TYPE_TEXT],
            ['label' => "jours de péremption", 'type' => ChampsLibre::TYPE_NUMBER],
            ['label' => 'diamètre', 'type' => ChampsLibre::TYPE_NUMBER],
            ['label' => 'n° lot autre', 'type' => ChampsLibre::TYPE_TEXT],
            ['label' => 'n° lot Léti', 'type' => ChampsLibre::TYPE_TEXT],
            ['label' => "projet 3", 'type' => ChampsLibre::TYPE_TEXT],
            ['label' => "date de retour en salle ou d'envoi à Crolles ou autre", 'type' => ChampsLibre::TYPE_DATE],
            ['label' => "mois de stock", 'type' => ChampsLibre::TYPE_LIST, 'elements' => ['0','1','2','3','4','5','6','7','8','9','10','11','12']],
        ];
        $listFieldsSILIInt = [];
        $listFieldsSILIExt = [];
        $listFieldsMOB = [
            ['label' => 'adresse', 'col' => 2, 'type' => ChampsLibre::TYPE_TEXT],
            ['label' => 'famille produit', 'type' => ChampsLibre::TYPE_LIST, 'elements' => ['MOBILIER SB', 'MOBILIER TERTIAIRE']],
            ['label' => "stock mini", 'type' => ChampsLibre::TYPE_NUMBER],
            ['label' => "stock alerte", 'type' => ChampsLibre::TYPE_NUMBER],
            ['label' => "prix unitaire", 'col' => 9, 'type' => ChampsLibre::TYPE_TEXT],
            ['label' => "date entrée", 'type' => ChampsLibre::TYPE_DATE],
            ['label' => "prix du stock final", 'col' => 11, 'type' => ChampsLibre::TYPE_DATE],
            ['label' => "alerte mini", 'type' => ChampsLibre::TYPE_LIST, 'elements' => ['besoin', '']],
            ['label' => "alerte prévision", 'type' => ChampsLibre::TYPE_NUMBER],
        ];
        $listFieldsSLUGCIBLE = [
            ['label' => 'famille produit', 'type' => ChampsLibre::TYPE_LIST, 'elements' => ['CIBLE / SLUGS']],
            ['label' => 'zone', 'col' => 5, 'type' => ChampsLibre::TYPE_TEXT],
            ['label' => 'équipementier', 'col' => 6, 'type' => ChampsLibre::TYPE_TEXT],
            ['label' => 'réf équipementier', 'col' => 7, 'type' => ChampsLibre::TYPE_TEXT],
            ['label' => 'machine', 'col' => 8, 'type' => ChampsLibre::TYPE_TEXT],
            ['label' => "stock mini", 'type' => ChampsLibre::TYPE_NUMBER],
            ['label' => "stock alerte", 'type' => ChampsLibre::TYPE_NUMBER],
            ['label' => 'prix unitaire', 'type' => ChampsLibre::TYPE_TEXT],
            ['label' => "date entrée", 'type' => ChampsLibre::TYPE_DATE],
            ['label' => 'prix du stock final', 'type' => ChampsLibre::TYPE_TEXT],
            ['label' => "alerte mini", 'type' => ChampsLibre::TYPE_LIST, 'elements' => ['besoin', '']],
            ['label' => "alerte prévision", 'type' => ChampsLibre::TYPE_NUMBER],
        ];

        // PDT
        foreach ($listFieldsRefArticlePDT as $field) {
            $this->createCL($manager, $field, Type::LABEL_PDT, CategorieCL::REFERENCE_ARTICLE);
        }
        foreach ($listFieldsArticlePDT as $field) {
            $this->createCL($manager, $field, Type::LABEL_PDT, CategorieCL::ARTICLE);
        }
        // CSP
        foreach ($listFieldsRefArticleCSP as $field) {
            $this->createCL($manager, $field, Type::LABEL_CSP, CategorieCL::REFERENCE_ARTICLE);
        }
        foreach ($listFieldsArticleCSP as $field) {
            $this->createCL($manager, $field, Type::LABEL_CSP, CategorieCL::ARTICLE);
        }
        // SILI
        foreach ($listFieldsSILI as $field) {
            $this->createCL($manager, $field, Type::LABEL_SILI, CategorieCL::REFERENCE_ARTICLE);
        }
        // MOB
        foreach ($listFieldsMOB as $field) {
            $this->createCL($manager, $field, Type::LABEL_MOB, CategorieCL::REFERENCE_ARTICLE);
        }
        // SLUGCIBLE
        foreach ($listFieldsSLUGCIBLE as $field) {
            $this->createCL($manager, $field, Type::LABEL_SLUGCIBLE, CategorieCL::REFERENCE_ARTICLE);
        }

        $manager->flush();
    }

    public static function getGroups():array {
        return ['champslibres'];
    }

    /**
     * @param ObjectManager $manager
     * @param $field
     * @param string $typeLabel
     * @param string $categorieCLLabel
     * @return ChampsLibre|null
     */
    public function createCL(ObjectManager $manager, $field, $typeLabel, $categorieCLLabel)
    {
        $type = $this->typeRepository->findOneBy(['label' => $typeLabel]);
        $label = $field['label'] . ' (' . $type->getLabel() . ') ';

        $cl = $this->champsLibreRepository->findOneBy(['label' => $label]);

        if (empty($cl)) {
            $cl = new ChampsLibre();
            $cl
                ->setLabel($field['label'] . ' (' . $type->getLabel() . ') ')
                ->setTypage($field['type'])
                ->setCategorieCL($this->categorieCLRepository->findOneByLabel($categorieCLLabel))
                ->setType($type);

            if ($field['type'] == ChampsLibre::TYPE_LIST) {
                $cl->setElements($field['elements']);
            }
            $manager->persist($cl);
        }
        return $cl;
    }

}
