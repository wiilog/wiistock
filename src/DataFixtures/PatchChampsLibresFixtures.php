<?php

namespace App\DataFixtures;

use App\Entity\CategorieCL;
use App\Entity\ChampLibre;
use App\Entity\Type;
use App\Repository\CategorieCLRepository;
use App\Repository\EmplacementRepository;
use App\Repository\ReferenceArticleRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

use App\Repository\ChampLibreRepository;

class PatchChampsLibresFixtures extends Fixture implements FixtureGroupInterface
{
    private $encoder;

    /**
     * @var ChampLibreRepository
     */
    private $champLibreRepository;

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


    public function __construct(EmplacementRepository $emplacementRepository, UserPasswordEncoderInterface $encoder, ChampLibreRepository $champsLibreRepository, ReferenceArticleRepository $refArticleRepository, CategorieCLRepository $categorieCLRepository)
    {
        $this->champLibreRepository = $champsLibreRepository;
        $this->encoder = $encoder;
        $this->refArticleRepository = $refArticleRepository;
        $this->categorieCLRepository = $categorieCLRepository;
        $this->emplacementRepository = $emplacementRepository;
    }

    public function load(ObjectManager $manager)
    {
        $listFieldsRefArticlePDT = [
            ['label' => 'famille produit', 'type' => ChampLibre::TYPE_LIST, 'elements' => ['POMPE', 'POMPE_41', 'PIECES DETACHEES', 'PDT GENERIQUE', 'DCOS TEST ELECTRIQUE']],
            ['label' => 'zone', 'type' => ChampLibre::TYPE_TEXT],
            ['label' => 'équipementier', 'type' => ChampLibre::TYPE_TEXT],
            ['label' => "réf équipementier", 'type' => ChampLibre::TYPE_TEXT],
            ['label' => "machine", 'type' => ChampLibre::TYPE_TEXT],
            ['label' => "stock mini", 'type' => ChampLibre::TYPE_NUMBER],
            ['label' => "stock alerte", 'type' => ChampLibre::TYPE_NUMBER],
            ['label' => "prix du stock final", 'type' => ChampLibre::TYPE_TEXT],
            ['label' => "alerte mini", 'type' => ChampLibre::TYPE_LIST, 'elements' => ['besoin', '']],
            ['label' => "alerte prévision", 'type' => ChampLibre::TYPE_NUMBER],
        ];
        $listFieldsArticlePDT = [
            ['label' => "prix unitaire", 'type' => ChampLibre::TYPE_TEXT],
            ['label' => "date entrée", 'type' => ChampLibre::TYPE_DATE],
        ];
        $listFieldsRefArticleCSP = [
            ['label' => 'famille produit', 'type' => ChampLibre::TYPE_LIST, 'elements' => ['CONSOMMABLES', 'PAD']],
            ['label' => "stock mini", 'type' => ChampLibre::TYPE_NUMBER],
            ['label' => "stock alerte", 'type' => ChampLibre::TYPE_NUMBER],
            ['label' => "prix du stock final", 'type' => ChampLibre::TYPE_DATE],
            ['label' => "alerte mini", 'type' => ChampLibre::TYPE_LIST, 'elements' => ['besoin', '']],
            ['label' => "alerte prévision", 'type' => ChampLibre::TYPE_NUMBER],
        ];
        $listFieldsArticleCSP = [
            ['label' => "date entrée", 'type' => ChampLibre::TYPE_DATE],
            ['label' => "prix unitaire", 'type' => ChampLibre::TYPE_TEXT],
            ['label' => "péremptions", 'type' => ChampLibre::TYPE_DATE],
        ];
        $listFieldsSILI = [
            ['label' => 'adresse', 'type' => ChampLibre::TYPE_TEXT],
            ['label' => 'famille produit', 'type' => ChampLibre::TYPE_LIST, 'elements' => ['SILICIUM']],
            ['label' => "alerte mini", 'type' => ChampLibre::TYPE_LIST, 'elements' => ['besoin', '']],
            ['label' => "alerte prévision", 'type' => ChampLibre::TYPE_NUMBER],
            ['label' => 'date', 'type' => ChampLibre::TYPE_DATE],
            ['label' => "projet", 'type' => ChampLibre::TYPE_TEXT],
            ['label' => "demandeur", 'type' => ChampLibre::TYPE_TEXT],
            ['label' => "date fin de projet", 'type' => ChampLibre::TYPE_DATE],
            ['label' => "lot", 'type' => ChampLibre::TYPE_TEXT],
            ['label' => "sortie", 'type' => ChampLibre::TYPE_NUMBER],
            ['label' => "commentaire", 'type' => ChampLibre::TYPE_TEXT],
            ['label' => "jours de péremption", 'type' => ChampLibre::TYPE_NUMBER],
            ['label' => 'diamètre', 'type' => ChampLibre::TYPE_NUMBER],
            ['label' => 'n° lot autre', 'type' => ChampLibre::TYPE_TEXT],
            ['label' => 'n° lot Léti', 'type' => ChampLibre::TYPE_TEXT],
            ['label' => "projet 3", 'type' => ChampLibre::TYPE_TEXT],
            ['label' => "date de retour en salle ou d'envoi à Crolles ou autre", 'type' => ChampLibre::TYPE_DATE],
            ['label' => "mois de stock", 'type' => ChampLibre::TYPE_LIST, 'elements' => ['0','1','2','3','4','5','6','7','8','9','10','11','12']],
        ];
        $listFieldsSILIInt = [
            ['label' => 'adresse', 'type' => ChampLibre::TYPE_TEXT],
            ['label' => 'famille produit', 'type' => ChampLibre::TYPE_LIST, 'elements' => ['SIL_INTERNE']],
            ['label' => 'date', 'type' => ChampLibre::TYPE_DATE],
            ['label' => 'diamètre', 'type' => ChampLibre::TYPE_NUMBER],
            ['label' => 'n° lot autre', 'type' => ChampLibre::TYPE_TEXT],
            ['label' => 'n° lot Léti', 'type' => ChampLibre::TYPE_TEXT],
            ['label' => "demandeur", 'type' => ChampLibre::TYPE_TEXT],
            ['label' => "projet 3", 'type' => ChampLibre::TYPE_TEXT],
//            ['label' => "date de retour en salle ou d'envoi à Crolles ou autre", 'type' => ChampLibre::TYPE_DATE],
            ['label' => "commentaire", 'type' => ChampLibre::TYPE_TEXT],
            ['label' => "mois de stock", 'type' => ChampLibre::TYPE_LIST, 'elements' => ['0','1','2','3','4','5','6','7','8','9','10','11','12', '13']],
        ];
        $listFieldsSILIExt = [
            ['label' => 'adresse', 'type' => ChampLibre::TYPE_TEXT],
            ['label' => 'famille produit', 'type' => ChampLibre::TYPE_LIST, 'elements' => ['SIL_EXTERNE']],
            ['label' => 'date', 'type' => ChampLibre::TYPE_DATE],
            ['label' => "projet", 'type' => ChampLibre::TYPE_TEXT],
            ['label' => "demandeur", 'type' => ChampLibre::TYPE_TEXT],
            ['label' => 'date fin de projet', 'type' => ChampLibre::TYPE_DATE],
            ['label' => 'lot', 'type' => ChampLibre::TYPE_TEXT],
//            ['label' => 'sortie', 'type' => ChampLibre::TYPE_TEXT],
//            ['label' => "commentaire", 'type' => ChampLibre::TYPE_TEXT],
//            ['label' => "jours de péremption", 'type' => ChampLibre::TYPE_NUMBER],
        ];
        $listFieldsMOB = [
            ['label' => 'adresse', 'col' => 2, 'type' => ChampLibre::TYPE_TEXT],
            ['label' => 'famille produit', 'type' => ChampLibre::TYPE_LIST, 'elements' => ['MOBILIER SB', 'MOBILIER TERTIAIRE']],
            ['label' => "stock mini", 'type' => ChampLibre::TYPE_NUMBER],
            ['label' => "stock alerte", 'type' => ChampLibre::TYPE_NUMBER],
            ['label' => "prix unitaire", 'col' => 9, 'type' => ChampLibre::TYPE_TEXT],
            ['label' => "date entrée", 'type' => ChampLibre::TYPE_DATE],
            ['label' => "prix du stock final", 'col' => 11, 'type' => ChampLibre::TYPE_DATE],
            ['label' => "alerte mini", 'type' => ChampLibre::TYPE_LIST, 'elements' => ['besoin', '']],
            ['label' => "alerte prévision", 'type' => ChampLibre::TYPE_NUMBER],
        ];
        $listFieldsSLUGCIBLE = [
            ['label' => 'famille produit', 'type' => ChampLibre::TYPE_LIST, 'elements' => ['CIBLE / SLUGS']],
            ['label' => 'zone', 'col' => 5, 'type' => ChampLibre::TYPE_TEXT],
            ['label' => 'équipementier', 'col' => 6, 'type' => ChampLibre::TYPE_TEXT],
            ['label' => 'réf équipementier', 'col' => 7, 'type' => ChampLibre::TYPE_TEXT],
            ['label' => 'machine', 'col' => 8, 'type' => ChampLibre::TYPE_TEXT],
            ['label' => "stock mini", 'type' => ChampLibre::TYPE_NUMBER],
            ['label' => "stock alerte", 'type' => ChampLibre::TYPE_NUMBER],
            ['label' => 'prix unitaire', 'type' => ChampLibre::TYPE_TEXT],
            ['label' => "date entrée", 'type' => ChampLibre::TYPE_DATE],
            ['label' => 'prix du stock final', 'type' => ChampLibre::TYPE_TEXT],
            ['label' => "alerte mini", 'type' => ChampLibre::TYPE_LIST, 'elements' => ['besoin', '']],
            ['label' => "alerte prévision", 'type' => ChampLibre::TYPE_NUMBER],
        ];

        // patch pour champs libres articles -> articles de référence
        $listFieldsRefArticlePDT = [
            ['label' => "prix unitaire", 'type' => ChampLibre::TYPE_TEXT],
            ['label' => "date entrée", 'type' => ChampLibre::TYPE_DATE],
            ['label' => "adresse", 'type' => ChampLibre::TYPE_TEXT],
        ];

        $listFieldsRefArticleCSP = [
            ['label' => "adresse", 'type' => ChampLibre::TYPE_TEXT],
            ['label' => "prix unitaire", 'col' => 9, 'type' => ChampLibre::TYPE_TEXT],
            ['label' => "date entrée", 'col' => 10, 'type' => ChampLibre::TYPE_DATE],
            ['label' => "péremptions", 'col' => 14, 'type' => ChampLibre::TYPE_DATE],
        ];


        // PDT
//        foreach ($listFieldsRefArticlePDT as $field) {
//            $this->createCL($manager, $field, Type::LABEL_PDT, CategorieCL::REFERENCE_ARTICLE);
//        }
//        foreach ($listFieldsArticlePDT as $field) {
//            $this->createCL($manager, $field, Type::LABEL_PDT, CategorieCL::ARTICLE);
//        }
//        // CSP
//        foreach ($listFieldsRefArticleCSP as $field) {
//            $this->createCL($manager, $field, Type::LABEL_CSP, CategorieCL::REFERENCE_ARTICLE);
//        }
//        foreach ($listFieldsArticleCSP as $field) {
//            $this->createCL($manager, $field, Type::LABEL_CSP, CategorieCL::ARTICLE);
//        }
        // SILI
//        foreach ($listFieldsSILI as $field) {
//            $this->createCL($manager, $field, Type::LABEL_SILI, CategorieCL::REFERENCE_ARTICLE);
//        }
        // SILI ext
        foreach ($listFieldsSILIExt as $field) {
            $this->createCL($manager, $field, Type::LABEL_SILI_EXT, CategorieCL::REFERENCE_ARTICLE);
        }
        // SILI int
        foreach ($listFieldsSILIInt as $field) {
            $this->createCL($manager, $field, Type::LABEL_SILI_INT, CategorieCL::REFERENCE_ARTICLE);
        }
        // MOB
//        foreach ($listFieldsMOB as $field) {
//            $this->createCL($manager, $field, Type::LABEL_MOB, CategorieCL::REFERENCE_ARTICLE);
//        }
        // SLUGCIBLE
//        foreach ($listFieldsSLUGCIBLE as $field) {
//            $this->createCL($manager, $field, Type::LABEL_SLUGCIBLE, CategorieCL::REFERENCE_ARTICLE);
//        }

        $manager->flush();
    }

    /**
     * @param ObjectManager $manager
     * @param $field
     * @param string $typeLabel
     * @param string $categorieCLLabel
     */
    public function createCL(ObjectManager $manager, $field, $typeLabel, $categorieCLLabel)
    {

        $typeRepository = $manager->getRepository(Type::class);
        $type = $typeRepository->findOneBy(['label' => $typeLabel]);
        $label = $field['label'] . ' (' . $type->getLabel() . ')';

        $cl = $this->champLibreRepository->findOneBy(['label' => $label]);

        if (empty($cl)) {
            $cl = new ChampLibre();
            $cl
                ->setLabel($label)
                ->setTypage($field['type'])
                ->setCategorieCL($this->categorieCLRepository->findOneByLabel($categorieCLLabel))
                ->setType($type);

            if ($field['type'] == ChampLibre::TYPE_LIST) {
                $cl->setElements($field['elements']);
            }
            $manager->persist($cl);
        }
    }

    public static function getGroups():array {
        return ['champslibres'];
    }

}
