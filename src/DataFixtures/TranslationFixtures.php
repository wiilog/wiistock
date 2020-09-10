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
                'Natures de colis autorisées' => 'Natures de colis autorisées',
                'Natures des colis' => 'Natures des colis',
                'Nature de colis' => 'Nature de colis',
                'Natures de colis' => 'Natures de colis',
                'nature' => 'nature',
                'nature colis' => 'nature colis',
                "une nature" => "une nature",
                "cette nature" => "cette nature",
                "natures requises" => "natures requises",
                'Sélectionner une nature' => 'Sélectionner une nature'
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
            'acheminement' => [
                'nouvelle demande' => 'nouvelle demande',
                'acheminements' => 'acheminements',
                'Acheminement' => 'Acheminement',
                'acheminement' => 'acheminement',
                'emplacement prise' => 'emplacement prise',
                'emplacement dépose' => 'emplacement dépose',
                'demande d\'acheminement' => 'demande d\'acheminement',
                'Le colis existe déjà dans cet acheminement' => 'Le colis existe déjà dans cet acheminement',
                'Traiter un acheminement' => 'Traiter un acheminement',
                'type d\'acheminement' => 'type d\'acheminement',
                "Quantité à acheminer" => "Quantité à acheminer",
                "Quantité colis" => "Quantité colis",
                "Cet acheminement est urgent" => "Cet acheminement est urgent",
                "L'acheminement a bien été créé" => "L'acheminement a bien été créé",
                "L'acheminement a bien été modifié" => "L'acheminement a bien été modifié",
                "L'acheminement a bien été supprimé" => "L'acheminement a bien été supprimé",
                "La fiche d'état n'existe pas pour cet acheminement" => "La fiche d'état n'existe pas pour cet acheminement"
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
            ],
            'colis' => [
                'Ajouter un colis' => 'Ajouter un colis',
                'Modifier un colis' => 'Modifier un colis',
                'Numéro colis' => 'Numéro colis',
                "Quantité colis" => "Quantité colis",
                'Votre colis a bien été modifié' => 'Votre colis a bien été modifié',
                "Voulez-vous réellement supprimer ce colis ?" => "Voulez-vous réellement supprimer ce colis ?",
                "Le colis a bien été supprimé" => "Le colis a bien été supprimé",
                "Le colis a bien été modifié" => "Le colis a bien été modifié",
                'Le colis n\'existe pas' => 'Le colis n\'existe pas',
                'Le colis a bien été sauvegardé' => 'Le colis a bien été sauvegardé'
            ],
            'services' => [
                'Types de service' => 'Types de service',
                'Service' => 'Service',
                'service' => 'service',
                'Demande de service' => 'Demande de service',
                'Supprimer la demande de service' => 'Supprimer la demande de service',
                'La demande de service a bien été supprimée' => 'La demande de service a bien été supprimée',
                'Nouvelle demande de service' => 'Nouvelle demande de service',
                'La demande de service a bien été créée' => 'La demande de service a bien été créée',
                "Détails d'une demande de service" => "Détail d'une demande de service",
                'Modifier une demande de service' => 'Modifier une demande de service',
                'La demande de service a bien été modifiée' => 'La demande de service a bien été modifiée',
                'Voulez-vous réellement supprimer cette demande de service' => 'Voulez-vous réellement supprimer cette demande de service',
                'Vous ne pouvez pas supprimer cette demande de service' => 'Vous ne pouvez pas supprimer cette demande de service',
                'Votre demande de service a bien été effectuée' => 'Votre demande de service a bien été effectuée',
                'Demande de service effectuée' => 'Demande de service effectuée',
                'Type de demande de service' => 'Type de demande de service',
                "Changement de statut d'une demande de service" => "Changement de statut d'une demande de service",
                "Une demande de service vous concernant a changé de statut" => "Une demande de service vous concernant a changé de statut"
            ]
        ];

        $translationRepository = $manager->getRepository(Translation::class);
        $allSavedTranslations = $translationRepository->findAll();

        foreach ($allSavedTranslations as $savedTranslation) {
            $menu = $savedTranslation->getMenu();
            $label = $savedTranslation->getLabel();

            if (!isset($translations[$menu][$label])) {
                $manager->remove($savedTranslation);
                dump("Suppression de la traduction :  $menu / $label");
            }
        }

        foreach ($translations as $menu => $translation) {
            foreach ($translation as $label => $translatedLabel) {
                // array_reduce to force request with case sensitive
                $translationObject = array_reduce(
                    $this->translationRepository->findBy([ 'menu' => $menu, 'label' => $label]),
                    function (?Translation $res, Translation $translation) use ($label, $menu) {
                        return $res ?? (
                            ($translation->getLabel() === $label && $translation->getMenu() === $menu)
                                ? $translation
                                : null
                        );
                    },
                    null
                );

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
