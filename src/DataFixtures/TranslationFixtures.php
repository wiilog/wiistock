<?php

namespace App\DataFixtures;

use App\Entity\Translation;
use App\Repository\TranslationRepository;
use App\Service\SpecificService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class TranslationFixtures extends Fixture implements FixtureGroupInterface
{
    /**
     * @var TranslationRepository
     */
    private $translationRepository;

    private $specificService;

    public function __construct(TranslationRepository $translationRepository, SpecificService $specificService)
    {
        $this->translationRepository = $translationRepository;
        $this->specificService = $specificService;
    }

    /**
     * @param ObjectManager $manager
     */
    public function load(ObjectManager $manager)
    {
        $isCurrentClientCEA = $this->specificService->isCurrentClientNameFunction(SpecificService::CLIENT_CEA_LETI);
        $translations = [
            'natures' => [
                'Natures des colis' => 'Natures des colis',
                'Nature de colis' => 'Nature de colis',
                'Natures de colis' => 'Natures de colis',
                'nature' => 'nature',
                'nature colis' => 'nature colis',
                "une nature" => "une nature",
                "cette nature" => "cette nature"
            ],
            'arrivage' => [
                'flux - arrivages' => 'flux - arrivages',
                'arrivage' => 'arrivage',
                'arrivages' => 'arrivages',
                'nouvel arrivage' => 'nouvel arrivage',
                "n° d'arrivage" => "n° d'arrivage",
                'cet arrivage' => 'cet arrivage',
                'de colis' => 'de colis',
                'colis' => 'colis',
                'acheteurs' => 'acheteurs',
                'destinataire' => 'destinataire',
                'douane' => 'douane',
                'congelé' => 'congelé',
            ],
            'réception' => [
                'réceptions' => 'réceptions',
                'réception' => 'réception',
                'de réception' => 'de réception',
                'n° de réception' => 'n° de réception',
                'cette réception' => 'cette réception',
                'nouvelle réception' => 'nouvelle réception',
                'la' => 'la',
                'une réception' => 'une réception',
                'la réception' => 'la réception',
                'article' => 'article',
                'articles' => 'articles',
                "l'article" => "l'article",
                "d'article" => "d'article",
                "d'articles" => "d'articles"
            ],
            'urgences' => [
                'urgence' => 'urgence',
                'nouvelle urgence' => 'nouvelle urgence',
                'cette urgence' => 'cette urgence',
                "l'urgence" => "l'urgence",
                'urgences' => 'urgences',
                'acheteur' => 'acheteur',
                'date de début' => 'date de début',
                'date de fin' => 'date de fin',
                'numéro de commande' => 'numéro de commande',
            ],
            'mouvement de traçabilité' => [
                'Colis' => 'Colis'
            ],
            'reference' => [
                'références' => $isCurrentClientCEA
                    ? 'Références CEA'
                    : 'Références',
                'référence' => $isCurrentClientCEA
                    ? 'Référence CEA'
                    : 'Référence'
            ]
        ];

        foreach ($translations as $menu => $translation) {
            foreach ($translation as $label => $translatedLabel) {

                $translationObject = $this->translationRepository->findOneBy([
                    'menu' => $menu,
                    'label' => $label
                ]);

                if (empty($translationObject)) {
                    $translationObject = new Translation();
                    $translationObject
                        ->setMenu($menu)
                        ->setLabel($label)
                        ->setTranslation($translatedLabel)
                        ->setUpdated(true);
                    $manager->persist($translationObject);
                    dump("Ajout de la traduction :  $menu / $label ==> $translatedLabel");
                }
            }
        }
        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['translation', 'fixtures'];
    }
}
