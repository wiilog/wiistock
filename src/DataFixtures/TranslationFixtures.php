<?php

namespace App\DataFixtures;

use App\Entity\Translation;
use App\Repository\TranslationRepository;
use App\Service\SpecificService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Console\Output\ConsoleOutput;

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
        $output = new ConsoleOutput();

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
                'Acheminer' => 'Acheminer',
                'Numéro de commande' => 'Numéro de commande',
            ],
            'acheminement' => [
                'Numéro de commande' => 'Numéro de commande',
                'Numéro de tracking transporteur' => 'Numéro de tracking transporteur',
                'Acheminements' => 'Acheminements',
                'Acheminement' => 'Acheminement',
                'acheminement' => 'acheminement',
                'Nouvelle demande' => 'Nouvelle demande',
                'Modifier un acheminement' => 'Modifier un acheminement',
                'Emplacement prise' => 'Emplacement prise',
                'Emplacement dépose' => 'Emplacement dépose',
                'Transporteur' => 'Transporteur',
                'Nb colis' => 'Nb colis',
                'demande d\'acheminement' => 'demande d\'acheminement',
                'Colis à acheminer' => 'Colis à acheminer',
                'Le colis {numéro} existe déjà dans cet acheminement' => 'Le colis {numéro} existe déjà dans cet acheminement',
                'type d\'acheminement' => 'type d\'acheminement',
                "Quantité à acheminer" => "Quantité à acheminer",
                "Quantité colis" => "Quantité colis",
                "Cet acheminement est urgent" => "Cet acheminement est urgent",
                "L'acheminement a bien été créé" => "L'acheminement a bien été créé",
                "L'acheminement a bien été passé en brouillon" => "L'acheminement a bien été passé en brouillon",
                "L'acheminement a bien été passé en à traiter" => "L'acheminement a bien été passé en à traiter",
                "L'acheminement a bien été traité" => "L'acheminement a bien été traité",
                "L'acheminement a bien été modifié" => "L'acheminement a bien été modifié",
                "L'acheminement a bien été supprimé" => "L'acheminement a bien été supprimé",
                'Le bon d\'acheminement n\'existe pas pour cet acheminement' => 'Le bon d\'acheminement n\'existe pas pour cet acheminement',
                "Des colis sont nécessaires pour générer un bon de livraison" => "Des colis sont nécessaires pour générer un bon de livraison",
                "Des colis sont nécessaires pour générer une lettre de voiture" => "Des colis sont nécessaires pour générer une lettre de voiture",
                "Acheminement {numéro} traité le {date}" => "Acheminement {numéro} traité le {date}",
                "L'acheminement contient plus de {nombre} colis" => "L'acheminement contient plus de {nombre} colis",
                'Générer un bon d\'acheminement' => 'Générer un bon d\'acheminement',
                'Générer un bon de livraison' => 'Générer un bon de livraison',
                'Générer une lettre de voiture' => 'Générer une lettre de voiture',
                "La lettre de voiture n'existe pas pour cet acheminement" => "La lettre de voiture n'existe pas pour cet acheminement",
                "Le bon de livraison n'existe pas pour cet acheminement" => "Le bon de livraison n'existe pas pour cet acheminement",
                "Les poids ou volumes indicatifs sont manquants sur certains colis, la lettre de voiture ne peut pas être générée" => "Les poids ou volumes indicatifs sont manquants sur certains colis, la lettre de voiture ne peut pas être générée",
                'Business unit' => 'Business unit',
                'Numéro de projet' => 'Numéro de projet'
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
                "d'articles" => "d'articles",
                'Cette réception est urgente' => 'Cette réception est urgente',
                'Une ou plusieurs références liées à cette réception sont urgentes' => 'Une ou plusieurs références liées à cette réception sont urgentes',
                'Cette réception ainsi qu\'une ou plusieurs références liées sont urgentes' => 'Cette réception ainsi qu\'une ou plusieurs références liées sont urgentes'
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
                'Date arrivage' => 'Date arrivage'
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
                'colis' => 'colis',
                'Ajouter un colis' => 'Ajouter un colis',
                'Modifier un colis' => 'Modifier un colis',
                'Informations du colis' => 'Informations du colis',
                'Numéro colis' => 'Numéro colis',
                "Quantité colis" => "Quantité colis",
                "Liste des colis" => "Liste des colis",
                "Voulez-vous réellement supprimer ce colis ?" => "Voulez-vous réellement supprimer ce colis ?",
                "Supprimer le colis" => "Supprimer le colis",
                "Le colis {numéro} a bien été supprimé" => "Le colis {numéro} a bien été supprimé",
                "Le colis {numéro} a bien été modifié" => "Le colis {numéro} a bien été modifié",
                'Le colis n\'existe pas' => 'Le colis n\'existe pas',
                'Le colis {numéro} a bien été ajouté' => 'Le colis {numéro} a bien été ajouté',
                "Ce colis est utilisé dans l'arrivage {arrivage}" => "Ce colis est utilisé dans l'arrivage {arrivage}",
                "Ce colis est référencé dans un ou plusieurs mouvements de traçabilité" => "Ce colis est référencé dans un ou plusieurs mouvements de traçabilité",
                "Ce colis est référencé dans un ou plusieurs acheminements" => "Ce colis est référencé dans un ou plusieurs acheminements",
                "Ce colis est référencé dans un ou plusieurs litiges" => "Ce colis est référencé dans un ou plusieurs litiges",
            ],
            'services' => [
                'Types de service' => 'Types de service',
                'Service' => 'Service',
                'service' => 'service',
                'Demande de service' => 'Demande de service',
                'Supprimer la demande de service' => 'Supprimer la demande de service',
                'La demande de service {numéro} a bien été supprimée' => 'La demande de service {numéro} a bien été supprimée',
                'Nouvelle demande de service' => 'Nouvelle demande de service',
                'La demande de service {numéro} a bien été créée' => 'La demande de service {numéro} a bien été créée',
                "Détails d'une demande de service" => "Détail d'une demande de service",
                'Modifier une demande de service' => 'Modifier une demande de service',
                'La demande de service {numéro} a bien été modifiée' => 'La demande de service {numéro} a bien été modifiée',
                'Voulez-vous réellement supprimer cette demande de service' => 'Voulez-vous réellement supprimer cette demande de service',
                'Vous ne pouvez pas supprimer cette demande de service' => 'Vous ne pouvez pas supprimer cette demande de service',
                'Votre demande de service a bien été effectuée' => 'Votre demande de service a bien été effectuée',
                'Demande de service effectuée' => 'Demande de service effectuée',
                'Type de demande de service' => 'Type de demande de service',
                "Changement de statut d'une demande de service" => "Changement de statut d'une demande de service",
                "Une demande de service vous concernant a changé de statut" => "Une demande de service vous concernant a changé de statut",
                "Votre demande de service a été créée" => "Votre demande de service a été créée",
                "Création d'une demande de service" => "Création d'une demande de service",
            ]
        ];

        $translationRepository = $manager->getRepository(Translation::class);
        $allSavedTranslations = $translationRepository->findAll();

        foreach ($allSavedTranslations as $savedTranslation) {
            $menu = $savedTranslation->getMenu();
            $label = $savedTranslation->getLabel();

            if (!isset($translations[$menu][$label])) {
                $manager->remove($savedTranslation);
                $output->writeln("Suppression de la traduction :  $menu / $label");
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
                    $output->writeln("Ajout de la traduction :  $menu / $label ==> $translatedLabel");
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
