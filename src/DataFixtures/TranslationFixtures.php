<?php

namespace App\DataFixtures;

use App\Entity\Language;
use App\Entity\Nature;
use App\Entity\Translation;
use App\Entity\TranslationCategory;
use App\Entity\TranslationSource;
use App\Entity\Utilisateur;
use App\Service\SpecificService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Console\Output\ConsoleOutput;
use WiiCommon\Helper\Stream;

class TranslationFixtures extends Fixture implements FixtureGroupInterface {

    const TRANSLATIONS = [
        "Général" => [
            null => [
                "Zone filtre" => [
                    "subtitle" => "Les libellés génériques présents dans tous les encarts filtre de l’application",
                    "content" => [
                        [
                            "fr" => "Du",
                            "en" => "From",
                        ],
                        [
                            "fr" => "Au",
                            "en" => "To",
                        ],
                        [
                            "fr" => "Filtrer",
                            "en" => "Filter",
                        ],
                        [
                            "fr" => "Veuillez entrer au moins 1 caractère",
                            "en" => "Please enter at least 1 character",
                        ],
                        [
                            "fr" => "Ajouter des éléments",
                            "en" => "Add elements",
                        ],
                    ],
                ],
                "Zone liste" => [
                    "subtitle" => "Les libellés génériques présents pour la recherche rapide les boutons d’action et la pagination",
                    "content" => [
                        [
                            "fr" => "Rechercher",
                            "en" => "Search",
                        ],
                        [
                            "fr" => "Entrée pour valider",
                            "en" => "Enter to validate",
                        ],
                        [
                            "fr" => "Exporter au format CSV",
                            "en" => "Export as CSV",
                        ],
                        [
                            "fr" => "Gestion des colonnes",
                            "en" => "Columns management",
                        ],
                        [
                            "fr" => "Date de création",
                            "en" => "Creation date",
                        ],
                        [
                            "fr" => "Afficher {1} éléments",
                            "en" => "Display {1} elements",
                        ],
                        [
                            "fr" => "{1} à {2} sur {3}",
                            "en" => "{1} to {2} of {3}",
                        ],
                        [
                            "fr" => "Précédent",
                            "en" => "Previous",
                        ],
                        [
                            "fr" => "Suivant",
                            "en" => "Next",
                        ],
                        [
                            "fr" => "Champs",
                            "en" => "Fields",
                        ],
                        [
                            "fr" => "Visible",
                            "en" => "Displayed",
                        ],
                        [
                            "fr" => "Enregister",
                            "en" => "Save",
                        ],
                        [
                            "fr" => "Fermer",
                            "en" => "Close",
                        ],
                        [
                            "fr" => "Chargement en cours",
                            "en" => "Loading",
                        ],
                        [
                            "fr" => "Traitement en cours",
                            "en" => "Loading",
                        ],
                    ],
                ],
                "Modale" => [
                    "subtitle" => "Les libellés génériques présents dans les modales de l’application",
                    "content" => [
                        [
                            "fr" => "Champs libres",
                            "en" => "Custom fields",
                        ],
                        [
                            "fr" => "commentaire",
                            "en" => "Comment",
                        ],
                        [
                            "fr" => "Pièces jointes",
                            "en" => "Attached documents",
                        ],
                        [
                            "fr" => "Faites glisser vos pièces jointes ou",
                            "en" => "Drag your attachments or",
                        ],
                        [
                            "fr" => "Parcourir vos fichiers",
                            "en" => "Browse your files",
                        ],
                        [
                            "fr" => "Annuler",
                            "en" => "Cancel",
                        ],
                        [
                            "fr" => "Détails",
                            "en" => "Details",
                        ],
                        [
                            "fr" => "Modifier",
                            "en" => "Modify",
                        ],
                        [
                            "fr" => "Supprimer",
                            "en" => "Delete",
                        ],
                        [
                            "fr" => "Valider",
                            "en" => "Validate",
                        ],
                        [
                            "fr" => "Veuillez renseigner les champs",
                            "en" => "Please fill in the fields",
                        ],
                        [
                            "fr" => "Ajouter",
                            "en" => "Add",
                        ],
                        [
                            "fr" => "Fermer",
                            "en" => "Close",
                        ],
                        [
                            "fr" => "Oui",
                            "en" => "Yes",
                        ],
                        [
                            "fr" => "Non",
                            "en" => "No",
                        ],
                        [
                            "fr" => "Aucune",
                            "en" => "None",
                        ],
                    ],
                ],
            ],
        ],
        "Dashboard" => [
            "content" => [
                [
                    "fr" => "Unité logistique",
                    "en" => "Logistics unit",
                    "tooltip" => "Composant \"UL en retard\"\nComposant \"Nombre d'arrivages et d'UL quotidiens\"\nComposant \"Nombres d'arrivages et d'UL hebdomadaires\"",
                ],
                [
                    "fr" => "Dépose",
                    "en" => "Drop",
                    "tooltip" => "Composant \"UL en retard\"",
                ],
                [
                    "fr" => "Délai",
                    "en" => "Deadline",
                    "tooltip" => "Composant \"UL en retard\"",
                ],
                [
                    "fr" => "Emplacement",
                    "en" => "Location",
                    "tooltip" => "Composant \"UL en retard\"",
                ],
                [
                    "fr" => "Arrivages",
                    "en" => "Deliveries",
                    "tooltip" => "Composant \"Nombre d'arrivages et d'UL quotidiens\"\nComposant \"Nombres d'arrivages et d'UL hebdomadaires\"",
                ],
                [
                    "fr" => "Nombre de lignes à traiter",
                    "en" => "Number of lines to \nprocess",
                    "tooltip" => "Composant \"Entrées à effectuer\"",
                ],
                [
                    "fr" => "Prochain emplacement à traiter",
                    "en" => "Next location to process",
                    "tooltip" => "Composant \"Entrées à effectuer\"",
                ],
                [
                    "fr" => "Retard",
                    "en" => "Delay",
                    "tooltip" => "Composant \"Entrées à effectuer\"",
                ],
                [
                    "fr" => "Moins d'{1}",
                    "en" => "Less than {1}",
                    "tooltip" => "Composant \"Entrées à effectuer\"",
                ],
                [
                    "fr" => "Date d'acheminement non estimée",
                    "en" => "Transport date not estimated",
                    "tooltip" => "Composant \"Demande en cours\"",
                ],
                [
                    "fr" => "Heure de traitement estimée",
                    "en" => "Estimated processing time",
                    "tooltip" => "Composant \"Demande en cours\"",
                ],
                [
                    "fr" => "Non estimée",
                    "en" => "Not estimated",
                    "tooltip" => "Composant \"Demande en cours\"",
                ],
                [
                    "fr" => "lignes",
                    "en" => "lines",
                    "tooltip" => "Composant \"Nombre de services du jour\", coche \"Afficher le nombre d'opération(s) réalisé(s) de la journée\"",
                ],
                [
                    "fr" => "urgences",
                    "en" => "Urgencies",
                    "tooltip" => "Composant \"Nombre de services du jour\", coche \"Afficher le nombre d'urgences de la journée\"",
                ],
                [
                    "fr" => "A traiter sous :",
                    "en" => "To be processed within:",
                    "tooltip" => "Composant \"Quantité en cours n emplacement(s)\", coche \"Afficher le délai de traitement\" et composant \"Demandes à traiter\", coche \"Délai de traitement à respecter [...]\"",
                ],
            ],
        ],
        "Traçabilité" => [
            "Général" => [
                "content" => [
                    [
                        "fr" => "Traçabilité",
                        "en" => "Traceability",
                        "tooltip" => "Menu niveau 1\nFil d'ariane",
                    ],
                    [
                        "fr" => "Unités logistiques",
                        "en" => "Logistic unit",
                        "tooltip" => "Page Flux - Arrivages :\nDétails arrivage - Liste des UL - Nom de colonnes (Renommage Code -> UL)\nDétails arrivage - Liste des litiges - Modale Nouveau litige\nDétails arrivage - Liste des litiges - Modifier litige\nMail litige\n______\nPage Mouvements : \nFiltre\nZone liste - Nom de colonnes\nGestion des colonnes\nModale Nouveau mouvement\nModale Modifier un mouvement\n______\nPage UL :\nMenu\nFil d'ariane\nFiltre (renommage UL -> UL)\nOnglet\nOnglet UL - Colonne (renommage en Numéro UL -> UL)\nOnglet UL - Modale Modifier une unité logistique (renommage en Numéro UL -> UL)\nOnglet Groupes - Carte UL dans Carte Groupe\n_____\nPage Association BR :\nFiltre \nZone liste - Nom de colonnes \n_____\nPage Encours :\nCarte emplacement - Zone liste - Nom de colonnes",
                    ],
                    [
                        "fr" => "Emplacement",
                        "en" => "Location",
                        "tooltip" => "Page UL :\nFiltre\nZone liste - Nom de colonnes\n_____\nPage Mouvements :\nZone liste - Nom de colonnes\nFiltre\nModale Nouveau mouvement\nModale Modifier un mouvement",
                    ],
                    [
                        "fr" => "Date",
                        "en" => "Date",
                        "tooltip" => "Page Mouvements :\nZone liste - Nom de colonnes\nGestion des colonnes\nModale Nouveau mouvement\nModale Modifier un mouvement\n____\nPage UL : \nModale Modifier une unité logistique\n_____\nPage Association BR :\nZone liste - Nom de colonnes",
                    ],
                    [
                        "fr" => "Utilisateur",
                        "en" => "User",
                        "tooltip" => "Page Flux - Arrivages :\nZone liste - Nom de colonnes\nModifier litige - Tableau Historique\n____\nPage Association BR :\nZone liste - Nom de colonnes",
                    ],
                    [
                        "fr" => "Opérateur",
                        "en" => "Worker",
                        "tooltip" => "Page Flux - Arrivages :\nDétails arrivage - Liste UL - Colonne\n_______\nPage Mouvements :\nModale Nouveau mouvement\nModale Modifier un mouvement\nModale Détail de mouvement",
                    ],
                    [
                        "fr" => "Quantité",
                        "en" => "Quantity",
                        "tooltip" => "Page Mouvements :\nZone liste - Nom de colonnes\nModale Modifier une unité logistique\nModale Nouvelle UL\n______\nPage UL :\nOnglet UL - Zone liste - Nom de colonnes\nOnglet UL - Modale Modifier une unité logistique",
                    ],
                    [
                        "fr" => "Nature",
                        "en" => "Nature",
                        "tooltip" => "Page Flux - Arrivages :\nDétails arrivage - Liste des UL - Colonne (renommage nature -> Nature)\n_____\nPage UL :\nOnglet UL - Colonne (renommage Nature d'UL -> Nature)\nOnglet UL - Modale Modifier une unité logistique (renommage Nature d'UL -> Nature)\nOnglet Groupes - Carte groupe\nOnglet Groupes - Carte UL dans Carte groupe",
                    ],
                    [
                        "fr" => "Natures",
                        "en" => "Natures",
                        "tooltip" => "Page UL : \nFiltre\n____\nPage Encours :\nFiltre",
                    ],
                    [
                        "fr" => "Issu de",
                        "en" => "From",
                        "tooltip" => "Page Mouvements : \nZone liste - Nom de colonnes\nGestion des colonnes\n_____\nPage UL : \nZone liste - Nom de colonnes",
                    ],
                    [
                        "fr" => "Date de création",
                        "en" => "Creation date",
                        "tooltip" => "Page Flux - Arrivages :\nZone liste - Nom de colonnes\nGestion des colonnes\nDétails arrive - Liste des litiges \n____\nPage Urgences :\nZone liste - Nom de colonnes",
                    ],
                    [
                        "fr" => "Date dernier mouvement",
                        "en" => "Last movement date",
                        "tooltip" => "Page UL :\nZone liste - Nom de colonnes (renommage Date du dernier mouvement -> Date dernier mouvement)\n_____\nPage Flux - Arrivages :\nDétails arrivage - Liste des UL - Nom de colonnes",
                    ],
                    [
                        "fr" => "Dernier emplacement",
                        "en" => "Last location",
                        "tooltip" => "Page Flux - Arrivages :\nDétails arrivage - Liste des UL - Nom de colonnes\n_____\nPage UL :\nZone liste - Nom de colonnes (renommage Emplacement -> Dernier emplacement)",
                    ],
                ],
            ],
            "Flux - Arrivages" => [
                "Divers" => [
                    "content" => [
                        [
                            "fr" => "Flux - arrivages",
                            "en" => "Arrivals",
                            "tooltip" => "Page Flux - Arrivages :\nMenu\nFil d'ariane",
                        ],
                        [
                            "fr" => "N° d'arrivage",
                            "en" => "Arrival number",
                            "tooltip" => "Page Flux - Arrivages : \nFiltre (renommage n° d'arrivage -> N° d'arrivage)\nZone liste - Nom de colonnes (renommage n° d'arrivage -> N° d'arrivage)\nGestion des colonnes\nDétails arrivage - Liste des litiges - Modale Nouveau litige (renommage ordre arrivage -> N° d'arrivage)\n_____\nPage Urgences :\nZone liste - Nom de colonnes (renommage Numéro d'arrivage -> N° d'arrivage)\n_____\nPage Qualité - Litiges :\nZone liste - Nom de colonnes",
                        ],
                        [
                            "fr" => "Fournisseurs",
                            "en" => "Suppliers",
                            "tooltip" => "Page Flux - Arrivages :\nFiltre",
                        ],
                        [
                            "fr" => "Transporteurs",
                            "en" => "Carriers",
                            "tooltip" => "Page Flux - Arrivages :\nFiltre",
                        ],
                        [
                            "fr" => "Destinataires",
                            "en" => "Recipients",
                            "tooltip" => "Page Flux - Arrivages :\nFiltre",
                        ],
                        [
                            "fr" => "Statuts",
                            "en" => "Statuses",
                            "tooltip" => "Page Flux - Arrivages :\nFiltres",
                        ],
                        [
                            "fr" => "Urgence",
                            "en" => "Urgency",
                            "tooltip" => "Page Flux - Arrivages :\nFiltre\nModale Nouveau litige\nModale Modifier le litige",
                        ],
                        [
                            "fr" => "Urgent",
                            "en" => "Urgent",
                            "tooltip" => "Page Flux - Arrivages :\nZone liste - Nom de colonnes\nGestion des colonnes",
                        ],
                        [
                            "fr" => "Nombre d'UL",
                            "en" => "Quantity L.U (Logistics unit)",
                            "tooltip" => "Page Flux - Arrivages :\nZone liste - Nom de colonnes (renommage nb um -> Nombre d'UL)\nGestion des colonnes (renommage nb um -> Nombre d'UL)",
                        ],
                        [
                            "fr" => "Nouvel arrivage (mettre majuscule)",
                            "en" => "New arrival",
                            "tooltip" => "Page Flux - Arrivages :\nBouton\nModale Nouvel arrivage",
                        ],
                        [
                            "fr" => "Ajouter une UL",
                            "en" => "Add L.U",
                            "tooltip" => "Page Flux - Arrivages :\nDétails arrivages - Liste des UL - Bouton\nDétails arrivages - Liste des UL - Modale Ajouter une UL (renommage Ajouter une unité logistique -> Ajouter une UL)",
                        ],
                        [
                            "fr" => "Nombre d'UL à ajouter :",
                            "en" => "Quantity of L.U to add :",
                            "tooltip" => "Page Flux - Arrivages :\nModale Nouvel arrivage\nDétails arrivages - Liste des UL - Modale Ajouter une UL",
                        ],
                        [
                            "fr" => "Arrivage",
                            "en" => "Arrival",
                            "tooltip" => "Page Flux - Arrivages :\nFil d'ariane\nDétails arrivage - Entête\nMail arrivage",
                        ],
                        [
                            "fr" => "Modifier arrivage",
                            "en" => "Edit arrival",
                            "tooltip" => "Page Flux - Arrivages :\nModale Modifier arrivage (renommage arrivage -> Modifier arrivage)",
                        ],
                        [
                            "fr" => "Supprimer l'arrivage",
                            "en" => "Delete the arrival",
                            "tooltip" => "Page Flux - Arrivages :\nModale Supprimer l'arrivage",
                        ],
                        [
                            "fr" => "Voulez-vous réellement supprimer cet arrivage ?",
                            "en" => "Do you really want to delete this arrival ?",
                            "tooltip" => "Page Flux - Arrivages :\nModale Supprimer l'arrivage",
                        ],
                        [
                            "fr" => "Liste des UL générées",
                            "en" => "List of L.U",
                            "tooltip" => "Page Flux - Arrivages :\nModale Liste des UL générées",
                        ],
                        [
                            "fr" => "Impression",
                            "en" => "Print label",
                            "tooltip" => "Page Flux - Arrivages :\nModale Liste des UL générées",
                        ],
                        [
                            "fr" => "Réceptionner",
                            "en" => "Receipt",
                            "tooltip" => "Zone liste - 3 points\nDétail arrivage entête",
                        ],
                        [
                            "fr" => "Un autre arrivage est en cours de création, veuillez réessayer",
                            "en" => "Another arrival was being created, please try again",
                        ],
                        [
                            "fr" => "Un autre litige d'arrivage est en cours de création, veuillez réessayer",
                            "en" => "Another arrival dispute was being created, please try again",
                        ],
                    ],
                ],
                "Champs fixes" => [
                    "content" => [
                        [
                            "fr" => "Fournisseur",
                            "en" => "Supplier",
                            "tooltip" => "Arrivages :\nZone liste - Nom de colonnes\nGestion des colonnes\nModale Nouvel arrivage\nModale Modifier arrivage\nArrivage détails - Entête\nModale Nouveau litige\n_____\nUrgences :\nZone liste - Nom de colonnes\nModale Nouvelle urgence\nModale Modifier une urgence",
                        ],
                        [
                            "fr" => "Transporteur",
                            "en" => "Carrier",
                            "tooltip" => "Zone liste - Nom de colonnes\nGestion des colonnes\nModale Nouvel arrivage\nModale Modifier arrivage\nDétails arrivage - Entête\nModale Nouveau litige",
                        ],
                        [
                            "fr" => "Chauffeur",
                            "en" => "Driver",
                            "tooltip" => "Zone liste - Nom de colonnes\nGestion des colonnes\nModale Nouvel arrivage\nModale Modifier arrivage\nArrivages détails - Entête\nModale Nouveau litige",
                        ],
                        [
                            "fr" => "N° tracking transporteur",
                            "en" => "Carrier tracking number",
                            "tooltip" => "Arrivages :\nZone liste - Nom de colonnes\nGestion des colonnes\nModale Nouvel arrivage\nModale Modifier arrivage\nArrivages détails - Entête\nModale Nouveau litige\n_____\nUrgences :\nZone liste - Nom de colonnes\nModale Nouvelle urgence (renommage Numéro tracking transporteur -> N° tracking transporteur)\nModale Modifier une urgence (renommage Numéro tracking transporteur -> N° tracking transporteur)",
                        ],
                        [
                            "fr" => "N° commande / BL",
                            "en" => "Order number",
                            "tooltip" => "Zone liste - Nom de colonnes (renommage)\nGestion des colonnes (renommage)\nModale Nouvel arrivage (renommage)\nModale Modifier arrivage (renommage)\nDétails arrivage - Entête (renommage)\nModale Nouveau litige\nMail litige",
                        ],
                        [
                            "fr" => "Type",
                            "en" => "Type",
                            "tooltip" => "Détails arrivage - Entête\nZone liste - Nom de colonnes\nGestion des colonnes\nModale Nouvel arrivage\nModale Modifier arrivage",
                        ],
                        [
                            "fr" => "Statut",
                            "en" => "Status",
                            "tooltip" => "Flux - arrivages :\nZone liste - Nom de colonnes\nModale Nouvel arrivage\nModale Modifier arrivage\nDétail arrivages - Entête\nDétail arrivages - Liste des litiges - Colonne\nModale Nouveau litige\nModale Modifier le litige",
                        ],
                        [
                            "fr" => "Emplacement de dépose",
                            "en" => "Drop location",
                            "tooltip" => "Détails arrivage - Entête\nZone liste - Nom de colonnes\nGestion des colonnes\nModale Nouvel arrivage\nModale Modifier arrivage",
                        ],
                        [
                            "fr" => "Destinataire",
                            "en" => "Recipient",
                            "tooltip" => "Arrivages :\nZone liste - Nom de colonnes\nGestion des colonnes\nModale Nouvel arrivage\nModale Modifier arrivage\nArrivage détails - Entête",
                        ],
                        [
                            "fr" => "Acheteur(s)",
                            "en" => "Buyer(s)",
                            "tooltip" => "Zone liste - Nom de colonnes (renommage Acheteurs -> Acheteur(s))\nGestion des colonnes (renommage Acheteurs -> Acheteur(s))\nModale Nouvel arrivage (renommage Acheteurs -> Acheteur(s))\nArrivages détails - Entête (renommage Acheteurs -> Acheteur(s))\nModale Nouveau litige",
                        ],
                        [
                            "fr" => "Imprimer arrivage",
                            "en" => "Print arrival",
                            "tooltip" => "Modale Nouvel arrivage\nDétails arrivages - Entête - Bouton",
                        ],
                        [
                            "fr" => "Imprimer UL",
                            "en" => "Print L.U",
                            "tooltip" => "Modale Nouvel arrivage\nDétails arrivages - Liste des UL - Bouton",
                        ],
                        [
                            "fr" => "Numéro de projet",
                            "en" => "Project number",
                            "tooltip" => "Zone liste - Nom de colonnes\nGestion des colonnes\nModale Nouvel arrivage\nModale Modifier arrivage\nDétails arrivage - Entête",
                        ],
                        [
                            "fr" => "Business unit",
                            "en" => "Business unit",
                            "tooltip" => "Détails arrivage - Entête\nZone liste - Nom de colonnes\nGestion des colonnes\nModale Nouvel arrivage\nModale Modifier arrivage",
                        ],
                        [
                            "fr" => "Douane",
                            "en" => "Customs",
                            "tooltip" => "Filtre\nZone liste - Nom de colonnes\nGestion des colonnes\nModale Nouvel arrivage\nModale Modifer arrivage\nArrivages détails - Entête",
                        ],
                        [
                            "fr" => "Congelé",
                            "en" => "Frozen",
                            "tooltip" => "Filtre \nZone liste - Nom de colonnes\nGestion des colonnes\nModale Nouvel arrivage\nModale Modifer arrivage\nArrivages détails - Entête",
                        ],
                        [
                            "fr" => "Projet",
                            "en" => "Project",
                            "tooltip" => "Modale Nouvel arrivage\nModale Modifer arrivage\nArrivages détails - Liste des unités logistiques - Modale modifier une UL",
                        ],
                    ],
                ],
                "Détails arrivage - Entête" => [
                    "content" => [
                        [
                            "fr" => "Cet arrivage est à traiter en URGENCE",
                            "en" => "This arrival needs to be dealt with urgently",
                            "tooltip" => "Détails arrivage - Entête (arrivage urgent)",
                        ],
                        [
                            "fr" => "Acheminer",
                            "en" => "Transfer",
                            "tooltip" => "Détails arrivages - Entête - Bouton",
                        ],
                    ],
                ],
                "Détails arrivage - Liste des litiges" => [
                    "content" => [
                        [
                            "fr" => "Liste des litiges",
                            "en" => "Disputes list",
                            "tooltip" => "Détails arrivages - Liste des litiges",
                        ],
                        [
                            "fr" => "Date de modification",
                            "en" => "Last edit date",
                            "tooltip" => "Détails arrivages - Liste des litiges - Colonne",
                        ],
                        [
                            "fr" => "Nouveau litige",
                            "en" => "Create dispute",
                            "tooltip" => "Détails arrivages - Liste des litiges - Bouton",
                        ],
                        [
                            "fr" => "Type",
                            "en" => "Type",
                            "tooltip" => "Détails arrivage - Liste des litiges - Colonne\nModale Nouveau litige\nModale Modifier le litige",
                        ],
                        [
                            "fr" => "Déclarant",
                            "en" => "Declarant",
                            "tooltip" => "Modale Nouveau litige\nModale Modifier le litige",
                        ],
                        [
                            "fr" => "Modifier le litige",
                            "en" => "Modify the dispute",
                            "tooltip" => "Modale Modifier le litige",
                        ],
                        [
                            "fr" => "Historique",
                            "en" => "History",
                            "tooltip" => "Modale Modifier le litige",
                        ],
                        [
                            "fr" => "Date",
                            "en" => "Date",
                            "tooltip" => "Modale Modifier le litige",
                        ],
                    ],
                ],
                "Mail arrivage" => [
                    "content" => [
                        [
                            "fr" => "Arrivage reçu :  le {1} à {2}",
                            "en" => "Arrival received : on {1} at {2}",
                            "tooltip" => "Mail arrivage",
                        ],
                        [
                            "fr" => "Bonjour,",
                            "en" => "Hello,",
                            "tooltip" => "Mail arrivage",
                        ],
                        [
                            "fr" => "Votre commande est arrivée :",
                            "en" => "Your order has arrived",
                            "tooltip" => "Mail arrivage",
                        ],
                        [
                            "fr" => "Unités logistiques réceptionnées :",
                            "en" => "Receipted logistic units",
                            "tooltip" => "Mail arrivage",
                        ],
                        [
                            "fr" => "Nature",
                            "en" => "Nature",
                            "tooltip" => "Mail arrivage",
                        ],
                        [
                            "fr" => "Quantité",
                            "en" => "Quantity",
                            "tooltip" => "Mail arrivage",
                        ],
                        [
                            "fr" => "Bonne journée",
                            "en" => "Have a good day,",
                            "tooltip" => "Mail arrivage",
                        ],
                        [
                            "fr" => "L'équipe GT Logistics.",
                            "en" => "The GT Logistics team",
                            "tooltip" => "Mail arrivage",
                        ],
                        [
                            "fr" => "Cliquez ici pour accéder à Follow GT",
                            "en" => "Click here to access Follow GT",
                            "tooltip" => "Mail arrivage",
                        ],
                    ],
                ],
                "Mail litige" => [
                    "content" => [
                        [
                            "fr" => "Un litige a été déclaré sur un arrivage vous concernant :",
                            "en" => "A dispute has been declared on a delivery concerning you:",
                            "tooltip" => "Mail litige",
                        ],
                        [
                            "fr" => "1 litige vous concerne :",
                            "en" => "1 dispute concerns you:",
                            "tooltip" => "Mail litige",
                        ],
                        [
                            "fr" => "Type de litige",
                            "en" => "Type of dispute",
                            "tooltip" => "Mail litige",
                        ],
                        [
                            "fr" => "Statut du litige",
                            "en" => "Status of dispute",
                            "tooltip" => "Mail litige",
                        ],
                    ],
                ],
                "Modale Nouvelle demande d'acheminement" => [
                    "subtitle" => "La plupart des libellés ont leur traduction qui s'applique à partir de la page Acheminement",
                    "content" => [
                        [
                            "fr" => "UL à acheminer",
                            "en" => "Asset to transfert",
                            "tooltip" => "Modale acheminer (idem que acheminement)",
                        ],
                    ],
                ],
            ],
            "Urgences" => [
                "content" => [
                    [
                        "fr" => "Urgences",
                        "en" => "Urgencies",
                        "tooltip" => "Fil d'ariane\nMenu",
                    ],
                    [
                        "fr" => "Date de début",
                        "en" => "Start date",
                        "tooltip" => "Zone liste - Nom de colonnes (renommage date de début -> Date de début)\nModale Nouvelle urgence (renommage date de début -> Date de début)\nModale Modifier une urgence (renommage date de début -> Date de début)",
                    ],
                    [
                        "fr" => "Date de fin",
                        "en" => "End date",
                        "tooltip" => "Zone liste - Nom de colonnes (renommage date de fin -> Date de fin) \nModale Nouvelle urgence (renommage date de fin -> Date de fin)\nModale Modifier une urgence (renommage date de fin -> Date de fin)",
                    ],
                    [
                        "fr" => "N° poste (ligne de commande)",
                        "en" => "Item number",
                        "tooltip" => "Zone liste - Nom de colonnes\nModale Nouvelle urgence (renommage Numéro de poste -> N° poste)\nModale Modifier une urgence (renommage Numéro de poste -> N° poste)",
                    ],
                    [
                        "fr" => "Acheteur",
                        "en" => "Buyer",
                        "tooltip" => "Zone liste - Nom de colonnes(renommage acheteur -> Acheteur)\nModale Nouvelle urgence (renommage acheteur -> Acheteur)\nModale Modifier une urgence (renommage acheteur -> Acheteur)",
                    ],
                    [
                        "fr" => "Date arrivage",
                        "en" => "Arrival date",
                        "tooltip" => "Zone liste - Nom de colonnes",
                    ],
                    [
                        "fr" => "Date de création",
                        "en" => "Creation date",
                        "tooltip" => "Zone liste - Nom de colonnes",
                    ],
                    [
                        "fr" => "Nouvelle urgence",
                        "en" => "New urgency",
                        "tooltip" => "Modale Nouvelle urgence (renommage nouvelle urgence -> Nouvelle urgence)",
                    ],
                    [
                        "fr" => "Modifier une urgence",
                        "en" => "Modify an urgency",
                        "tooltip" => "Modale Modifier une urgence",
                    ],
                    [
                        "fr" => "Supprimer l'urgence",
                        "en" => "Delete the urgency",
                        "tooltip" => "Modale Supprimer l'urgence",
                    ],
                    [
                        "fr" => "Cette urgence est liée à un arrivage.\nVous ne pouvez pas la supprimer",
                        "en" => "This urgency is linked to an arrival. You can not delete it",
                        "tooltip" => "Modale Supprimer l'urgence (renommage cette -> Cette)",
                    ],
                    [
                        "fr" => "Voulez-vous réellement supprimer cette urgence ?",
                        "en" => "Do you really want to delete this urgency ?",
                        "tooltip" => "Modale Supprimer l'urgence",
                    ],
                ],
            ],
            "Unités logistiques" => [
                "Divers" => [
                    "content" => [
                        [
                            "fr" => "Natures",
                            "en" => "Nature",
                            "tooltip" => "Filtre",
                        ],
                        [
                            "fr" => "N° d'arrivage",
                            "en" => "Arrival number",
                            "tooltip" => "Filtre",
                        ],
                    ],
                ],
                "Onglet \"Unités logistiques\"" => [
                    "content" => [
                        [
                            "fr" => "Unité logistique",
                            "en" => "Logistic unit number",
                            "tooltip" => "Zone liste\nModale Modifier une unité logistique",
                        ],
                        [
                            "fr" => "Nature d'unité logistique",
                            "en" => "Logistic unit nature",
                            "tooltip" => "Zone liste\nModale Modifier une unité logistique",
                        ],
                        [
                            "fr" => "Modifier une unité logistique",
                            "en" => "Edit L.U.",
                            "tooltip" => "Modale Modifier une unité logistique",
                        ],
                        [
                            "fr" => "Poids (kg)",
                            "en" => "Weight (kg)",
                            "tooltip" => "Modale Modifier une unité logistique",
                        ],
                        [
                            "fr" => "Volume (m3)",
                            "en" => "Volume (m3)",
                            "tooltip" => "Modale Modifier une unité logistique",
                        ],
                        [
                            "fr" => "Historique de groupage",
                            "en" => "Grouping history",
                            "tooltip" => "Modale Modifier une unité logistique",
                        ],
                        [
                            "fr" => "Projets assignés",
                            "en" => "Assigned projects",
                            "tooltip" => "Modale Modifier une unité logistique",
                        ],
                        [
                            "fr" => "Projet",
                            "en" => "Project",
                            "tooltip" => "Modale Modifier une unité logistique",
                        ],
                        [
                            "fr" => "Assigné le",
                            "en" => "Assigned on",
                            "tooltip" => "Modale Modifier une unité logistique",
                        ],
                        [
                            "fr" => "Supprimer l'UL",
                            "en" => "Delete the L.U.",
                            "tooltip" => "Modale Supprimer l'UL (renommage Supprimer la référence article -> Supprimer l'UL)",
                        ],
                        [
                            "fr" => "Voulez-vous réellement supprimer cette UL ?",
                            "en" => "Do you really want to delete this L.U. ?",
                            "tooltip" => "Modale Supprimer l'UL (renommage Supprimer la référence article -> Supprimer l'UL)",
                        ],
                        [
                            "fr" => "X articles dans l'unité logistique. Cliquez sur le logo pour voir le contenu de l'unité logistique.",
                            "en" => "X units in the logistics unit. Click on the logo to see the contents of the logistics unit.",
                            "tooltip" => "Zone liste",
                        ],
                        [
                            "fr" => "Contenu unité logistique",
                            "en" => "Logistics unit content",
                            "tooltip" => "Zone liste - contenu UL",
                        ],
                        [
                            "fr" => "Articles",
                            "en" => "Units",
                            "tooltip" => "Zone liste - contenu UL",
                        ],
                        [
                            "fr" => "Historique des articles",
                            "en" => "Units history",
                            "tooltip" => "Zone liste - contenu UL",
                        ],
                        [
                            "fr" => "L'unité logistique ne contient aucun article actuellement",
                            "en" => "The logistics unit does not currently contain any items",
                            "tooltip" => "Zone liste - contenu UL",
                        ],
                        [
                            "fr" => "Ref",
                            "en" => "Ref",
                            "tooltip" => "Zone liste - contenu UL",
                        ],
                        [
                            "fr" => "Quantité",
                            "en" => "Quantity",
                            "tooltip" => "Zone liste - contenu UL",
                        ],
                        [
                            "fr" => "Lot",
                            "en" => "Bundle / Batch / Lot",
                            "tooltip" => "Zone liste - contenu UL",
                        ],
                        [
                            "fr" => "Entrée",
                            "en" => "Entry / In",
                            "tooltip" => "Zone liste - contenu UL",
                        ],
                        [
                            "fr" => "Sortie",
                            "en" => "Removal / Out",
                            "tooltip" => "Zone liste - contenu UL",
                        ],
                        [
                            "fr" => "Disponible",
                            "en" => "Available",
                            "tooltip" => "Zone liste - contenu UL",
                        ],
                        [
                            "fr" => "En transit",
                            "en" => "In transit",
                            "tooltip" => "Zone liste - contenu UL",
                        ],
                        [
                            "fr" => "Indisponible",
                            "en" => "Unavailable",
                            "tooltip" => "Zone liste - contenu UL",
                        ],
                    ],
                ],
                "Onglet \"Groupes\"" => [
                    "content" => [
                        [
                            "fr" => "Groupes",
                            "en" => "Groups",
                            "tooltip" => "Onglet",
                        ],
                        [
                            "fr" => "Groupe",
                            "en" => "Group",
                            "tooltip" => "Onglet \"Groupes\"",
                        ],
                        [
                            "fr" => "Nombre d'UL",
                            "en" => "Quantity of L.U.",
                            "tooltip" => "Onglet \"Groupes\"",
                        ],
                        [
                            "fr" => "Mouvementé la dernière fois le {1}",
                            "en" => "Moved for the last time on the {1}",
                            "tooltip" => "Onglet \"Groupes\"",
                        ],
                        [
                            "fr" => "sur l'emplacement {1} par {2}",
                            "en" => "on the location {1} by {2}",
                            "tooltip" => "Onglet \"Groupes\"",
                        ],
                    ],
                ],
            ],
            "Mouvements" => [
                "content" => [
                    [
                        "fr" => "Mouvements",
                        "en" => "Movements",
                        "tooltip" => "Menu\nFil d'arianne",
                    ],
                    [
                        "fr" => "Types",
                        "en" => "Types",
                        "tooltip" => "Filtre",
                    ],
                    [
                        "fr" => "Opérateurs",
                        "en" => "Workers",
                        "tooltip" => "Filtre",
                    ],
                    [
                        "fr" => "Référence",
                        "en" => "Item",
                        "tooltip" => "Zone liste - Nom de colonnes\nGestion des colonnes",
                    ],
                    [
                        "fr" => "Article",
                        "en" => "Article",
                        "tooltip" => "Zone liste - Nom de colonnes\nGestion des colonnes",
                    ],
                    [
                        "fr" => "Article(s)",
                        "en" => "Article(s)",
                        "tooltip" => "Modale nouveau mouvement (dépose dans UL)",
                    ],
                    [
                        "fr" => "Groupe",
                        "en" => "Group",
                        "tooltip" => "Zone liste - Nom de colonnes\nGestion des colonnes\n____\nUL : \nModale Modifier une unité logistique",
                    ],
                    [
                        "fr" => "Type",
                        "en" => "Type",
                        "tooltip" => "Zone liste - Nom de colonnes\nGestion des colonnes\n____\nUL : \nModale Modifier une unité logistique",
                    ],
                    [
                        "fr" => "Prise dans UL",
                        "en" => "Pick up in L.U.",
                        "tooltip" => "Zone liste - type de mouvement",
                    ],
                    [
                        "fr" => "Nouveau mouvement",
                        "en" => "New movement",
                        "tooltip" => "Bouton",
                    ],
                    [
                        "fr" => "Action",
                        "en" => "Action",
                        "tooltip" => "Modale Nouveau mouvement\nModale Modifier un mouvement\nModale Détail de mouvement",
                    ],
                    [
                        "fr" => "Prise",
                        "en" => "Pick up",
                        "tooltip" => "Modale Nouveau mouvement - Choix liste \"Action\"",
                    ],
                    [
                        "fr" => "Dépose",
                        "en" => "Drop off",
                        "tooltip" => "Modale Nouveau mouvement - Choix liste \"Action\"",
                    ],
                    [
                        "fr" => "Prises et déposes",
                        "en" => "Pick-up and Drop off",
                        "tooltip" => "Modale Nouveau mouvement - Choix liste \"Action\"",
                    ],
                    [
                        "fr" => "Groupage",
                        "en" => "Grouping",
                        "tooltip" => "Modale Nouveau mouvement - Choix liste \"Action\"",
                    ],
                    [
                        "fr" => "Dégroupage",
                        "en" => "Ungroup",
                        "tooltip" => "Modale Nouveau mouvement - Choix liste \"Action\"",
                    ],
                    [
                        "fr" => "Passage à vide",
                        "en" => "Empty passage",
                        "tooltip" => "Modale Nouveau mouvement - Choix liste \"Action\"",
                    ],
                    [
                        "fr" => "Dépose dans UL",
                        "en" => "Drop off in L.U.",
                        "tooltip" => "Modale Nouveau mouvement - Choix liste \"Action\"",
                    ],
                    [
                        "fr" => "Emplacement de prise",
                        "en" => "Picking location",
                        "tooltip" => "Modale Nouveau mouvement - Action sélectionné sur \"prises et déposes\"",
                    ],
                    [
                        "fr" => "Emplacement de dépose",
                        "en" => "Drop location",
                        "tooltip" => "Modale Nouveau mouvement - Action sélectionné sur \"prises et déposes\"",
                    ],
                    [
                        "fr" => "UL contenante",
                        "en" => "Containing L.U.",
                        "tooltip" => "Modale Nouveau mouvement - Action sélectionné sur \"groupage\"",
                    ],
                    [
                        "fr" => "Détail de mouvement",
                        "en" => "Movement details",
                        "tooltip" => "Modale Détail de mouvement",
                    ],
                    [
                        "fr" => "Supprimer le mouvement",
                        "en" => "Delete the movement",
                        "tooltip" => "Modale Supprimer le mouvement",
                    ],
                    [
                        "fr" => "Voulez-vous réellement supprimer ce mouvement ?",
                        "en" => "Do you really want to delete this movement ?",
                        "tooltip" => "Modale Supprimer le mouvement",
                    ],
                ],
            ],
            "Association BR" => [
                "content" => [
                    [
                        "fr" => "Association BR",
                        "en" => "Receipt and L.U. matching",
                        "tooltip" => "Menu\nFil d'Ariane\nZone liste - Bouton",
                    ],
                    [
                        "fr" => "Réceptions",
                        "en" => "Receipts",
                        "tooltip" => "Filtre\nZone liste - Nom de colonnes",
                    ],
                    [
                        "fr" => "Utilisateurs",
                        "en" => "Users",
                        "tooltip" => "Filtre",
                    ],
                    [
                        "fr" => "Enregistrer une réception",
                        "en" => "Save a receipt",
                        "tooltip" => "Modale Enregistrer une réception",
                    ],
                    [
                        "fr" => "Association UL",
                        "en" => "L.U. association",
                        "tooltip" => "Modale Enregistrer une réception",
                    ],
                    [
                        "fr" => "N° de réception",
                        "en" => "Receipt number",
                        "tooltip" => "Modale Enregistrer une réception",
                    ],
                    [
                        "fr" => "Sans arrivage",
                        "en" => "Without arrival",
                        "tooltip" => "Modale Enregistrer une réception",
                    ],
                    [
                        "fr" => "Avec arrivage",
                        "en" => "With arrival",
                        "tooltip" => "Modale Enregistrer une réception",
                    ],
                    [
                        "fr" => "Supprimer l'association BR",
                        "en" => "Delete this match",
                        "tooltip" => "Modale Supprimer l'association BR",
                    ],
                    [
                        "fr" => "Voulez-vous réellement supprimer cette association BR ?",
                        "en" => "Do you really want to delete this match ?",
                        "tooltip" => "Modale Supprimer l'association BR",
                    ],
                    [
                        "fr" => "Un numéro de réception minimum est requis pour procéder à l'association",
                        "en" => "A receipt number is required to match with arrival",
                        "tooltip" => "Modale Supprimer l'association BR",
                    ],
                    [
                        "fr" => "Une association sans unité logistique avec ce numéro de réception existe déjà",
                        "en" => "A match without L.U. with this receipt number already exists",
                        "tooltip" => "Modale Supprimer l'association BR",
                    ],
                    [
                        "fr" => "Les unités logistiques suivantes n'existent pas :",
                        "en" => "The following L.U does not exist :",
                        "tooltip" => "Modale Supprimer l'association BR",
                    ],
                ],
            ],
            "Encours" => [
                "content" => [
                    [
                        "fr" => "Encours",
                        "en" => "Ongoing",
                        "tooltip" => "Fil d'arriane\nMenu",
                    ],
                    [
                        "fr" => "Emplacements",
                        "en" => "Locations",
                        "tooltip" => "Filtres",
                    ],
                    [
                        "fr" => "Vous devez sélectionner au moins un emplacement dans les filtres",
                        "en" => "You must select at least one location in the filters",
                        "tooltip" => "Erreur",
                    ],
                    [
                        "fr" => "Date de dépose",
                        "en" => "Drop off date",
                        "tooltip" => "Zone liste - Nom de colonnes",
                    ],
                    [
                        "fr" => "Délai",
                        "en" => "Period",
                        "tooltip" => "Zone liste - Nom de colonnes",
                    ],
                    [
                        "fr" => "Actualisé le {1} à {2}",
                        "en" => "Updated on {1} at {2}",
                        "tooltip" => "Page",
                    ],
                ],
            ],
        ],
        "Qualité" => [
            "Litiges" => [
                "content" => [
                    [
                        "fr" => "Qualité",
                        "en" => "Quality",
                        "tooltip" => "Menu\nFil d'ariane",
                    ],
                    [
                        "fr" => "Litiges",
                        "en" => "Disputes",
                        "tooltip" => "Menu\nFil d'ariane",
                    ],
                    [
                        "fr" => "Statuts",
                        "en" => "Statuses",
                        "tooltip" => "Filtres",
                    ],
                    [
                        "fr" => "Statut",
                        "en" => "Status",
                        "tooltip" => "Zone liste - Nom de colonnes",
                    ],
                    [
                        "fr" => "Origines",
                        "en" => "Origins",
                        "tooltip" => "Filtres",
                    ],
                    [
                        "fr" => "Acheteurs",
                        "en" => "Buyers",
                        "tooltip" => "Filtres",
                    ],
                    [
                        "fr" => "Acheteur",
                        "en" => "Buyer",
                        "tooltip" => "Zone liste - Nom de colonnes",
                    ],
                    [
                        "fr" => "Numéro de litige",
                        "en" => "Dispute number",
                        "tooltip" => "Filtres\nZone liste - Nom de colonnes",
                    ],
                    [
                        "fr" => "Déclarant",
                        "en" => "Declarant",
                        "tooltip" => "Filtres\nZone liste - Nom de colonnes",
                    ],
                    [
                        "fr" => "Type",
                        "en" => "Type",
                        "tooltip" => "Filtres\nZone liste - Nom de colonnes",
                    ],
                    [
                        "fr" => "Urgence ?",
                        "en" => "Urgent ?",
                        "tooltip" => "Filtres",
                    ],
                    [
                        "fr" => "N° commande / BL",
                        "en" => "Order number",
                        "tooltip" => "Zone liste - Nom de colonnes",
                    ],
                    [
                        "fr" => "Fournisseur",
                        "en" => "Supplier",
                        "tooltip" => "Zone liste - Nom de colonnes",
                    ],
                    [
                        "fr" => "Créé le",
                        "en" => "Created on",
                        "tooltip" => "Zone liste - Nom de colonnes",
                    ],
                    [
                        "fr" => "Modifié le",
                        "en" => "Edited on",
                        "tooltip" => "Zone liste - Nom de colonnes",
                    ],
                    [
                        "fr" => "Dernier historique",
                        "en" => "Last history",
                        "tooltip" => "Zone liste - Nom de colonnes",
                    ],
                    [
                        "fr" => "Supprimer le litige",
                        "en" => "Delete the dispute",
                        "tooltip" => "Modale Supprimer le litige",
                    ],
                    [
                        "fr" => "Voulez-vous réellement supprimer ce litige ?",
                        "en" => "Do you really want to delete this dispute ?",
                        "tooltip" => "Modale Supprimer le litige",
                    ],
                ],
            ],
        ],
        "Demande" => [
            "Général" => [
                "content" => [
                    [
                        "fr" => "Nouvelle demande",
                        "en" => "New operation",
                        "tooltip" => "Menu \"+\"",
                    ],
                    [
                        "fr" => "Demande",
                        "en" => "Operation",
                        "tooltip" => "Menu\nFil d'ariane",
                    ],
                    [
                        "fr" => "Statuts",
                        "en" => "Statuses",
                        "tooltip" => "Acheminement :\nFiltre\n_____\nService :\nFiltre",
                    ],
                    [
                        "fr" => "Demandeurs",
                        "en" => "Applicants",
                        "tooltip" => "Acheminement :\nFiltre\n_____\nService :\nFiltre",
                    ],
                    [
                        "fr" => "Demandeur",
                        "en" => "Applicant",
                        "tooltip" => "Acheminement : \nZone liste - Nom de colonnes\nModale de création\nPDF bon acheminement\n_____\nService : \nZone liste - Nom de colonnes\nModale de création",
                    ],
                    [
                        "fr" => "Urgences",
                        "en" => "Urgencies",
                        "tooltip" => "Acheminement :\nFiltre\n_____\nService :\nFiltre",
                    ],
                    [
                        "fr" => "Destinataire(s)",
                        "en" => "Addressees",
                        "tooltip" => "Acheminement :\nFiltre \nModale Nouvelle demande\nModale Modifier un acheminement\nDétails acheminement - Entête\nPDF bon acheminement\n(renommage Destinataires -> Destinataire(s))\n_____\nService :\nFiltre\nModale Nouvelle demande de service\nModale Modifier une demande de service\n(renommage Destinataires -> Destinataire(s))",
                    ],
                    [
                        "fr" => "Type",
                        "en" => "Type",
                        "tooltip" => "Acheminement :\nZone liste - Nom de colonnes\nModale Nouvelle demande\nModale Modifier un acheminement\nDétails acheminement - Entête\n_____\nService :\nFiltre\nZone liste - Nom de colonnes\nModale Nouvelle demande de service\nModale Modifier une demande de service",
                    ],
                    [
                        "fr" => "Statut",
                        "en" => "Status",
                        "tooltip" => "Acheminement :\nZone liste - Nom de colonnes\nModale Nouvelle demande\nModale Modifier un acheminement\nDétails acheminement - Entête\nDétails acheminements - Liste des UL - Colonne\n_____\nService :\nZone liste - Nom de colonnes\nModale Nouvelle demande de service\nModale Modifier une demande de service",
                    ],
                    [
                        "fr" => "Urgence",
                        "en" => "Urgency",
                        "tooltip" => "Acheminement :\nZone liste - Nom de colonnes\nModale Nouvelle demande\nModale Modifier un acheminement\nDétails acheminement - Entête\n_____\nService :\nModale Nouvelle demande de service\nModale Modifier une demande de service",
                    ],
                ],
            ],
            "Acheminement" => [
                "Divers" => [
                    "content" => [
                        [
                            "fr" => "Acheminement",
                            "en" => "Transfer",
                            "tooltip" => "Menu\nFil d'ariane\nMenu \"+\"\nDétails",
                        ],
                        [
                            "fr" => "Nouvelle demande d'acheminement",
                            "en" => "New transfer operation",
                            "tooltip" => "Modale Nouvelle demande d'acheminement (renommage Nouvelle demande -> Nouvelle demande d'acheminement)",
                        ],
                        [
                            "fr" => "N° demande",
                            "en" => "Transfer number",
                            "tooltip" => "Filtre \nZone liste - Nom de colonnes\n(renommage Numéro demande -> N° demande)\nNomade (renommage Numéro -> N° demande)",
                        ],
                        [
                            "fr" => "Types",
                            "en" => "Types",
                            "tooltip" => "Filtre",
                        ],
                        [
                            "fr" => "Transporteurs",
                            "en" => "Carriers",
                            "tooltip" => "Filtre",
                        ],
                        [
                            "fr" => "Supprimer la demande d'acheminement",
                            "en" => "Delete the transfer operation",
                            "tooltip" => "Modale Supprimer la demande d'acheminement",
                        ],
                        [
                            "fr" => "Vous-vous réllement supprimer cette demande d'acheminement ?",
                            "en" => "Do you really want to delete this transfer operation ?",
                            "tooltip" => "Modale Supprimer la demande d'acheminement",
                        ],
                    ],
                ],
                "Champs fixes" => [
                    "content" => [
                        [
                            "fr" => "N° commande",
                            "en" => "Order number",
                            "tooltip" => "Filtre \nZone liste - Nom de colonnes\nModale Nouvelle demande \nModale Modifier un acheminement \nDétails acheminement - Entête \n(renommage Numéro de commande -> N° commande)",
                        ],
                        [
                            "fr" => "Destination",
                            "en" => "Destination",
                            "tooltip" => "Filtre \nZone liste - Nom de colonnes\nModale Nouvelle demande\nModale Modifier un acheminement \nDétails acheminement - Entête \nPDF bon acheminement",
                        ],
                        [
                            "fr" => "Transporteur",
                            "en" => "Carrier",
                            "tooltip" => "Zone liste - Nom de colonnes\nModale Nouvelle demande\nModale Modifier un acheminement \nDétails acheminement - Entête",
                        ],
                        [
                            "fr" => "Emplacement prise",
                            "en" => "Picking location",
                            "tooltip" => "Zone liste - Nom de colonnes\nModale Nouvelle demande\nModale Modifier un acheminement \nDétails acheminement - Entête \nNomade\nPDF bon acheminement",
                        ],
                        [
                            "fr" => "Emplacement dépose",
                            "en" => "Drop location",
                            "tooltip" => "Zone liste - Nom de colonnes\nModale Nouvelle demande\nModale Modifier un acheminement \nDétails acheminement - Entête \nNomade\nPDF bon acheminement",
                        ],
                        [
                            "fr" => "N° tracking transporteur",
                            "en" => "Carrier tracking ID",
                            "tooltip" => "Zone liste - Nom de colonnes\nModale Nouvelle demande\nModale Modifier un acheminement \nDétails acheminement - Entête \n(renommage Numéro de tracking transporteur -> N° tracking transporteur)",
                        ],
                        [
                            "fr" => "N° projet",
                            "en" => "Project ID",
                            "tooltip" => "Zone liste - Nom de colonnes\nModale Nouvelle demande\nModale Modifier un acheminement \nDétails acheminement - Entête \n(renommage Numéro de projet -> N° projet)",
                        ],
                        [
                            "fr" => "Business unit",
                            "en" => "Business unit",
                            "tooltip" => "Zone liste - Nom de colonnes\nModale Nouvelle demande\nModale Modifier un acheminement \nDétails acheminement - Entête",
                        ],
                        [
                            "fr" => "Dates d'échéances",
                            "en" => "Due dates",
                            "tooltip" => "Détails\nModale Nouvelle demande (renommage Echéance -> Dates d'échéances)",
                        ],
                    ],
                ],
                "Zone liste - Noms de colonnes" => [
                    "content" => [
                        [
                            "fr" => "Date de création",
                            "en" => "Creation date",
                            "tooltip" => "Zone liste - Nom de colonnes\nDétails acheminements - Entête\nPDF bon acheminement",
                        ],
                        [
                            "fr" => "Date de validation",
                            "en" => "Validation date",
                            "tooltip" => "Zone liste - Nom de colonnes\nDétails acheminements - Entête\nPDF bon acheminement",
                        ],
                        [
                            "fr" => "Date de traitement",
                            "en" => "Finish date",
                            "tooltip" => "Zone liste - Nom de colonnes\nDétails acheminements - Entête\nPDF bon acheminement",
                        ],
                        [
                            "fr" => "Date d'échéance",
                            "en" => "Due date",
                            "tooltip" => "Zone liste - Nom de colonnes",
                        ],
                        [
                            "fr" => "Nombre d'UL",
                            "en" => "L.U. quantity",
                            "tooltip" => "Zone liste - Nom de colonnes",
                        ],
                        [
                            "fr" => "Traité par",
                            "en" => "Finished by",
                            "tooltip" => "Zone liste - Nom de colonnes\nDétails",
                        ],
                    ],
                ],
                "Détails acheminement - Entête" => [
                    "content" => [
                        [
                            "fr" => "Générer un bon de livraison",
                            "en" => "Generate a delivery note",
                            "tooltip" => "Détails acheminements - Bouton sous flèche",
                        ],
                        [
                            "fr" => "Générer un bon de surconsommation",
                            "en" => "Generate an overconsumption note",
                            "tooltip" => "Détails acheminements - Bouton sous flèche",
                        ],
                        [
                            "fr" => "Générer une lettre de voiture",
                            "en" => "Generate a road consignment note",
                            "tooltip" => "Détails acheminements - Bouton sous flèche",
                        ],
                        [
                            "fr" => "Générer un bon d'acheminement",
                            "en" => "Generate a transfer note",
                            "tooltip" => "Détails acheminements - Bouton sous flèche",
                        ],
                        [
                            "fr" => "Retour au statut Brouillon",
                            "en" => "Return to draft status",
                            "tooltip" => "Détails acheminements - Bouton sous flèche",
                        ],
                        [
                            "fr" => "Valider la demande",
                            "en" => "Validate the operation",
                            "tooltip" => "Détails acheminements - Bouton",
                        ],
                        [
                            "fr" => "Terminer la demande",
                            "en" => "Finish the operation",
                            "tooltip" => "Détails acheminements - Bouton",
                        ],
                    ],
                ],
                "Détails acheminement - Liste des unités logistiques" => [
                    "content" => [
                        [
                            "fr" => "Liste des UL",
                            "en" => "L.U. list",
                            "tooltip" => "Détails acheminements - Liste des UL",
                        ],
                        [
                            "fr" => "Quantité à acheminer",
                            "en" => "Quantity to transfer",
                            "tooltip" => "Détails acheminements - Liste des UL - Nom de colonnes\nPDF bon acheminement",
                        ],
                        [
                            "fr" => "Nature (mettre majuscule)",
                            "en" => "Nature",
                            "tooltip" => "Détails acheminements - Liste des UL - Nom de colonnes\nPDF bon acheminement",
                        ],
                        [
                            "fr" => "Poids (kg)",
                            "en" => "Weight (kg)",
                            "tooltip" => "Détails acheminements - Liste des UL - Nom de colonnes\nPDF bon acheminement",
                        ],
                        [
                            "fr" => "Volume (m3)",
                            "en" => "Volume (m3)",
                            "tooltip" => "Détails acheminements - Liste des UL - Nom de colonnes\nPDF bon acheminement",
                        ],
                        [
                            "fr" => "Date dernier mouvement",
                            "en" => "Last movement date",
                            "tooltip" => "Détails acheminements - Liste des UL - Nom de colonnes",
                        ],
                        [
                            "fr" => "Dernier emplacement",
                            "en" => "Last location",
                            "tooltip" => "Détails acheminements - Liste des UL - Nom de colonnes",
                        ],
                        [
                            "fr" => "Opérateur",
                            "en" => "Worker",
                            "tooltip" => "Détails acheminements - Liste des UL - Nom de colonnes",
                        ],
                        [
                            "fr" => "Nouvelle UL",
                            "en" => "Create L.U.",
                            "tooltip" => "Détails acheminements - Liste des UL - Ajout UL",
                        ],
                        [
                            "fr" => "Supprimer la ligne",
                            "en" => "Delete the line",
                            "tooltip" => "Modale Supprimer la ligne",
                        ],
                        [
                            "fr" => "Voulez-vous vraiment supprimer la ligne ?",
                            "en" => "Do you really want to delete the line ?",
                            "tooltip" => "Modale Supprimer la ligne",
                        ],
                        [
                            "fr" => "La ligne a bien été supprimée",
                            "en" => "The line has been deleted",
                            "tooltip" => "Message succès Supprimer la ligne",
                        ],
                    ],
                ],
                "Lettre de voiture" => [
                    "content" => [
                        [
                            "fr" => "Création/modification Lettre de voiture",
                            "en" => "Road consignment note creation/modification",
                            "tooltip" => "Modale Création/Modification Lettre de voiture",
                        ],
                        [
                            "fr" => "Inverser expéditeur/destinataire",
                            "en" => "Reverse Consigner / Receiver",
                            "tooltip" => "Modale Création/Modification Lettre de voiture",
                        ],
                        [
                            "fr" => "Inverser contact expéditeur / contact destinataire",
                            "en" => "Reverse Consigner contact / Receiver contact",
                            "tooltip" => "Modale Création/Modification Lettre de voiture",
                        ],
                        [
                            "fr" => "Inverser chargement / déchargement",
                            "en" => "Reverse loading zone / unloading zone",
                            "tooltip" => "Modale Création/Modification Lettre de voiture",
                        ],
                        [
                            "fr" => "Transporteur",
                            "en" => "Carrier",
                            "tooltip" => "Modale Création/Modification Lettre de voiture",
                        ],
                        [
                            "fr" => "Date d'acheminement",
                            "en" => "Transfer date",
                            "tooltip" => "Modale Création/Modification Lettre de voiture",
                        ],
                        [
                            "fr" => "Expéditeur",
                            "en" => "Consigner",
                            "tooltip" => "Modale Création/Modification Lettre de voiture",
                        ],
                        [
                            "fr" => "Destinataire",
                            "en" => "Receiver",
                            "tooltip" => "Modale Création/Modification Lettre de voiture",
                        ],
                        [
                            "fr" => "Contact expéditeur",
                            "en" => "Consigner contact",
                            "tooltip" => "Modale Création/Modification Lettre de voiture",
                        ],
                        [
                            "fr" => "Contact destinataire",
                            "en" => "Receiver contact",
                            "tooltip" => "Modale Création/Modification Lettre de voiture",
                        ],
                        [
                            "fr" => "Téléphone - Email",
                            "en" => "Phone number - Mail",
                            "tooltip" => "Modale Création/Modification Lettre de voiture",
                        ],
                        [
                            "fr" => "Lieu de chargement",
                            "en" => "Loading zone",
                            "tooltip" => "Modale Création/Modification Lettre de voiture",
                        ],
                        [
                            "fr" => "Lieu de déchargement",
                            "en" => "Unloading zone",
                            "tooltip" => "Modale Création/Modification Lettre de voiture",
                        ],
                        [
                            "fr" => "Not de bas de page",
                            "en" => "Footnote",
                            "tooltip" => "Modale Création/Modification Lettre de voiture",
                        ],
                        [
                            "fr" => "Information : Le contenu des UL doit être modifié sur les UL",
                            "en" => "Information: The  L.U.s' content must be changed on the L.U.s",
                            "tooltip" => "Modale Création/Modification Lettre de voiture",
                        ],
                    ],
                ],
                "Bon d'acheminement" => [
                    "content" => [
                        [
                            "fr" => "Bon d'acheminement",
                            "en" => "Transfer note",
                            "tooltip" => "PDF bon acheminement",
                        ],
                    ],
                ],
                "Bon de livraison" => [
                    "content" => [
                        [
                            "fr" => "Création modification BL",
                            "en" => "Create / Modify Order",
                            "tooltip" => "Modale Création modification BL",
                        ],
                        [
                            "fr" => "Expéditeur",
                            "en" => "Consigner",
                            "tooltip" => "Modale Création modification BL",
                        ],
                        [
                            "fr" => "Adresse de livraison",
                            "en" => "Delivery address",
                            "tooltip" => "Modale Création modification BL",
                        ],
                        [
                            "fr" => "Numéro de livraison",
                            "en" => "Delivery number",
                            "tooltip" => "Modale Création modification BL",
                        ],
                        [
                            "fr" => "Date de livraison",
                            "en" => "Delivery date",
                            "tooltip" => "Modale Création modification BL",
                        ],
                        [
                            "fr" => "Numéro de commande de vente",
                            "en" => "Sales order number",
                            "tooltip" => "Modale Création modification BL",
                        ],
                        [
                            "fr" => "Lettre de voiture",
                            "en" => "Road consignment note",
                            "tooltip" => "Modale Création modification BL",
                        ],
                        [
                            "fr" => "Bon de commande client",
                            "en" => "Customer PO Number",
                            "tooltip" => "Modale Création modification BL",
                        ],
                        [
                            "fr" => "Date commande client",
                            "en" => "Customer PO Date",
                            "tooltip" => "Modale Création modification BL",
                        ],
                        [
                            "fr" => "Réponse numéro commande",
                            "en" => "Response order number",
                            "tooltip" => "Modale Création modification BL",
                        ],
                        [
                            "fr" => "Numéro de projet",
                            "en" => "Project number",
                            "tooltip" => "Modale Création modification BL",
                        ],
                        [
                            "fr" => "Urgence",
                            "en" => "Urgent",
                            "tooltip" => "Modale Création modification BL",
                        ],
                        [
                            "fr" => "Contact",
                            "en" => "Handled by",
                            "tooltip" => "Modale Création modification BL",
                        ],
                        [
                            "fr" => "Téléphone (renommage)",
                            "en" => "Phone number",
                            "tooltip" => "Modale Création modification BL",
                        ],
                        [
                            "fr" => "Fax",
                            "en" => "Fax",
                            "tooltip" => "Modale Création modification BL",
                        ],
                        [
                            "fr" => "Client acheteur",
                            "en" => "Buying customer",
                            "tooltip" => "Modale Création modification BL",
                        ],
                        [
                            "fr" => "Numéro facture",
                            "en" => "Invoice number",
                            "tooltip" => "Modale Création modification BL",
                        ],
                        [
                            "fr" => "Numéro vente",
                            "en" => "Sold number",
                            "tooltip" => "Modale Création modification BL",
                        ],
                        [
                            "fr" => "Facturé à",
                            "en" => "Invoice to",
                            "tooltip" => "Modale Création modification BL",
                        ],
                        [
                            "fr" => "Vendu à",
                            "en" => "Sold to",
                            "tooltip" => "Modale Création modification BL",
                        ],
                        [
                            "fr" => "Nom dernier utilisateur",
                            "en" => "Last user's name",
                            "tooltip" => "Modale Création modification BL",
                        ],
                        [
                            "fr" => "Livrer à",
                            "en" => "Deliver to",
                            "tooltip" => "Modale Création modification BL",
                        ],
                        [
                            "fr" => "Ligne du BL",
                            "en" => "Order line",
                            "tooltip" => "Modale Création modification BL",
                        ],
                        [
                            "fr" => "Note(s)",
                            "en" => "Notes",
                            "tooltip" => "Modale Création modification BL",
                        ],
                        [
                            "fr" => "Copier vers Numéro vente",
                            "en" => "Copy invoice to Sold",
                            "tooltip" => "Modale Création modification BL",
                        ],
                        [
                            "fr" => "Copier vers Utilisateur final",
                            "en" => "Copy invoice to End user",
                            "tooltip" => "Modale Création modification BL",
                        ],
                        [
                            "fr" => "Copier vers Livrer à",
                            "en" => "Copy invoice tu Deliver to",
                            "tooltip" => "Modale Création modification BL",
                        ],
                    ],
                ],
                "Mails" => [
                    "content" => [
                        [
                            "fr" => "Changement de statut d'un(e) demande d'acheminement.",
                            "en" => "Transfer operation status changed",
                            "tooltip" => "Mail changement statut acheminement",
                        ],
                        [
                            "fr" => "Changement de statut d'un(e) demande d'acheminement de type {1} vous concernant :\nBonjour,\nVotre acheminement/expédition est en cours de traitement avec les informations suivantes :",
                            "en" => "Status change of a transfer operation of type {1} for you:\nHello,\nYour transfer / shipment is being processed with the following information:",
                            "tooltip" => "Mail changement statut acheminement",
                        ],
                        [
                            "fr" => "Bonne journée, \n\nL'équipe GT Logistics.",
                            "en" => "Have a nice day,\n\nThe GT Logistics team",
                            "tooltip" => "Mail changement statut acheminement\nMail traitement acheminement",
                        ],
                        [
                            "fr" => "Cliquez ici pour accéder à Follow GT",
                            "en" => "Click here to access Follow GT",
                            "tooltip" => "Mail changement statut acheminement\nMail traitement acheminement",
                        ],
                        [
                            "fr" => "Notification de traitement d'une demande d'acheminement",
                            "en" => "Notification upon transfer  operation finishing",
                            "tooltip" => "Mail traitement acheminement",
                        ],
                        [
                            "fr" => "Acheminement {1} traité le {2} à {3}",
                            "en" => "Transfer {1} finished on {2} at {3}",
                            "tooltip" => "Mail traitement acheminement",
                        ],
                        [
                            "fr" => "Bonjour,\nVotre acheminement/expédition est traité(e) avec les informations suivantes :",
                            "en" => "Hello,\nYour transfer / shipment is finished with the following information :",
                            "tooltip" => "Mail traitement acheminement",
                        ],
                    ],
                ],
            ],
            "Services" => [
                "content" => [
                    [
                        "fr" => "Service",
                        "en" => "Service",
                        "tooltip" => "Menu \"+\"\nMenu\nFil d'ariane",
                    ],
                    [
                        "fr" => "{1} statut sélectionné",
                        "en" => "{1} selected status",
                        "tooltip" => "Filtre",
                    ],
                    [
                        "fr" => "{1} statuts sélectionnés",
                        "en" => "{1} selected statuses",
                        "tooltip" => "Filtre",
                    ],
                    [
                        "fr" => "Date de création",
                        "en" => "Creation date",
                        "tooltip" => "Filtre date",
                    ],
                    [
                        "fr" => "Tout sélectionner",
                        "en" => "Select all",
                        "tooltip" => "Filtre",
                    ],
                    [
                        "fr" => "Nouvelle demande de service",
                        "en" => "New service operation",
                        "tooltip" => "Modale Nouvelle demande de service",
                    ],
                    [
                        "fr" => "Objet",
                        "en" => "Object",
                        "tooltip" => "Filtre\nZone liste - Nom de colonnes\nModale Nouvelle demande de service\nModale Modifier une demande de service",
                    ],
                    [
                        "fr" => "Date demande",
                        "en" => "Request date",
                        "tooltip" => "Zone liste - Nom de colonnes",
                    ],
                    [
                        "fr" => "Date de réalisation",
                        "en" => "Finish date",
                        "tooltip" => "Zone liste - Nom de colonnes\nModale Modifier une demande de service",
                    ],
                    [
                        "fr" => "Chargement",
                        "en" => "Loading",
                        "tooltip" => "Modale Nouvelle demande de service Modale Modifier une demande de service",
                    ],
                    [
                        "fr" => "Déchargement",
                        "en" => "Unloading",
                        "tooltip" => "Modale Nouvelle demande de service Modale Modifier une demande de service",
                    ],
                    [
                        "fr" => "Nombre d'opération(s) réalisée(s)",
                        "en" => "Number of operations performed",
                        "tooltip" => "Modale Nouvelle demande de service Modale Modifier une demande de service",
                    ],
                    [
                        "fr" => "Traité par",
                        "en" => "Finished by",
                        "tooltip" => "Zone liste - Nom de colonnes",
                    ],
                    [
                        "fr" => "Modifier une demande de service",
                        "en" => "Edit a service request",
                        "tooltip" => "Modale Modifier une demande de service",
                    ],
                    [
                        "fr" => "Date attendue",
                        "en" => "Due date",
                        "tooltip" => "Zone liste - Nom de colonnes\nModale Nouvelle demande de service Modale Modifier une demande de service",
                    ],
                    [
                        "fr" => "Statut",
                        "en" => "Status",
                        "tooltip" => "Page détails\nModales modification de statut",
                    ],
                    [
                        "fr" => "Changer de statut",
                        "en" => "Edit status",
                        "tooltip" => "Page détails",
                    ],
                    [
                        "fr" => "Modifier le statut",
                        "en" => "Edit status",
                        "tooltip" => "Modales modification de statut",
                    ],
                    [
                        "fr" => "Type à choisir",
                        "en" => "Choose a type",
                        "tooltip" => "Modale Nouvelle demande de service",
                    ],
                ],
            ],
        ],
    ];

    private const CHILD = [
        "category" => "menu",
        "menu" => "submenu",
    ];

    private ?ObjectManager $manager = null;

    private ?ConsoleOutput $console = null;

    private array $languages = [];

    public function load(ObjectManager $manager) {
        $this->console = new ConsoleOutput();
        $this->manager = $manager;

        $languageRepository = $manager->getRepository(Language::class);

        if(!$languageRepository->findOneBy(["slug" => Language::FRENCH_DEFAULT_SLUG])) {
            $frenchDefault = (new Language())
                ->setLabel("Français")
                ->setSlug(Language::FRENCH_DEFAULT_SLUG)
                ->setFlag("/svg/flags/fr.svg")
                ->setSelectable(true)
                ->setSelected(true)
                ->setHidden(true);

            $manager->persist($frenchDefault);

            $this->console->writeln("Created default language \"Français\"");
        }

        if(!$languageRepository->findOneBy(["slug" => Language::FRENCH_SLUG])) {
            $french = (new Language())
                ->setLabel("Français")
                ->setSlug(Language::FRENCH_SLUG)
                ->setFlag("/svg/flags/fr.svg")
                ->setSelectable(false)
                ->setSelected(false)
                ->setHidden(false);

            $manager->persist($french);

            $this->console->writeln("Created language \"Français\"");
        }

        if(!$languageRepository->findOneBy(["slug" => Language::ENGLISH_DEFAULT_SLUG])) {
            $englishDefault = (new Language())
                ->setLabel("English")
                ->setSlug(Language::ENGLISH_DEFAULT_SLUG)
                ->setFlag("/svg/flags/gb.svg")
                ->setSelectable(true)
                ->setSelected(false)
                ->setHidden(true);

            $manager->persist($englishDefault);

            $this->console->writeln("Created default language \"English\"");
        }

        if(!$languageRepository->findOneBy(["slug" => Language::ENGLISH_SLUG])) {
            $english = (new Language())
                ->setLabel("English")
                ->setSlug(Language::ENGLISH_SLUG)
                ->setFlag("/svg/flags/gb.svg")
                ->setSelectable(false)
                ->setSelected(false)
                ->setHidden(false);

            $manager->persist($english);

            $this->console->writeln("Created language \"English\"");
        }

        if(isset($frenchDefault) || isset($french) || isset($englishDefault) || isset($english)) {
            $manager->flush();
        }

        foreach(self::TRANSLATIONS as $categoryLabel => $menus) {
            $this->handleCategory("category", null, $categoryLabel, $menus);
        }

        $this->manager->flush();

        $this->initTranslationSources();

        $this->manager->flush();

        $this->updateUsers();
        $this->manager->flush();
    }

    private function handleCategory(string $type, ?TranslationCategory $parent, string $label, array $content) {
        $categoryRepository = $this->manager->getRepository(TranslationCategory::class);
        $translationSourceRepository = $this->manager->getRepository(TranslationSource::class);

        $category = $categoryRepository->findOneBy(["parent" => $parent, "label" => $label]);
        if(!$category) {
            $category = (new TranslationCategory())
                ->setParent($parent)
                ->setType($type)
                ->setLabel($label);

            $this->manager->persist($category);

            $parentLabel = $parent?->getLabel() ?: $parent?->getParent()?->getLabel();
            $this->console->writeln(($label ? "Created $type \"$label\"" : "Created single $type") . ($parentLabel ? " in \"$parentLabel\"" : ""));
        }

        if(!isset($content["content"])) {
            foreach($content as $childLabel => $childContent) {
                $this->handleCategory(self::CHILD[$type], $category, $childLabel, $childContent);
            }

            $this->deleteUnusedCategories($category, $content);
        } else {
            $category->setSubtitle($content["subtitle"] ?? null);

            foreach($content["content"] as $translation) {
                $transSource = $category->getId() ? $translationSourceRepository->findByDefaultFrenchTranslation($category, $translation["fr"]) : null;
                if(!$transSource) {
                    $transSource = new TranslationSource();
                    $transSource->setCategory($category);

                    $this->manager->persist($transSource);
                }

                $transSource->setTooltip($translation["tooltip"] ?? null);

                $french = $transSource->getTranslationIn("french-default");
                if(!$french) {
                    $french = (new Translation())
                        ->setLanguage($this->getLanguage("french-default"))
                        ->setSource($transSource)
                        ->setTranslation($translation["fr"]);

                    $this->manager->persist($french);

                    $this->console->writeln("Created french source translation \"" . str_replace("\n", "\\n ", $translation["fr"]) . "\"");
                }

                $english = $transSource->getTranslationIn("english-default");
                if(!$english) {
                    $english = (new Translation())
                        ->setLanguage($this->getLanguage("english-default"))
                        ->setSource($transSource)
                        ->setTranslation($translation["en"]);

                    $this->manager->persist($english);

                    $this->console->writeln("Created english source translation \"" . str_replace("\n", "\\n ", $translation["en"]) . "\"");
                } else if($english->getTranslation() != $translation["en"]) {
                    $english->setTranslation($translation["en"]);

                    $this->console->writeln("Updated english source translation \"" . str_replace("\n", "\\n ", $translation["en"]) . "\"");
                }

                $french = $transSource->getTranslationIn("french");
                if(!$french) {
                    $french = (new Translation())
                        ->setLanguage($this->getLanguage("french"))
                        ->setSource($transSource)
                        ->setTranslation($translation["fr"]);

                    $this->manager->persist($french);

                    $this->console->writeln("Created french translation \"" . str_replace("\n", "\\n ", $translation["fr"]) . "\"");
                }

                $english = $transSource->getTranslationIn("english");
                if(!$english) {
                    $english = (new Translation())
                        ->setLanguage($this->getLanguage("english"))
                        ->setSource($transSource)
                        ->setTranslation($translation["en"]);

                    $this->manager->persist($english);

                    $this->console->writeln("Created english translation \"" . str_replace("\n", "\\n ", $translation["en"]) . "\"");
                }
            }

            $this->deleteUnusedTranslations($category, $content["content"]);
        }
    }

    private function deleteUnusedCategories(TranslationCategory $parent, array $categories) {
        $categoryRepository = $this->manager->getRepository(TranslationCategory::class);

        if($parent->getId()) {
            $fixtureCategories = array_keys($categories);
            $unusedCategories = $categoryRepository->findUnusedCategories($parent, $fixtureCategories);
            foreach($unusedCategories as $category) {
                $this->deleteCategory($category, true);
                $this->manager->remove($category);
            }
        }
    }

    private function deleteCategory(?TranslationCategory $category, bool $root = false) {
        $this->manager->remove($category);
        if($root) {
            $this->console->writeln("Deleting unused category \"{$category->getLabel()}\"");
        } else {
            $this->console->writeln("Cascade deleting unused category \"{$category->getLabel()}\", child of \"{$category->getParent()->getLabel()}\"");
        }

        foreach($category->getChildren() as $child) {
            $this->deleteCategory($child);
        }

        foreach($category->getTranslationSources() as $source) {
            $this->manager->remove($source);

            $translation = $source->getTranslationIn(Language::FRENCH_DEFAULT_SLUG);
            if($translation) {
                $this->console->writeln("Cascade deleting unused source \"{$translation->getTranslation()}\" child of category \"{$category->getParent()->getLabel()}\"");
            } else {
                $this->console->writeln("Cascade deleting unknown unused source child of category \"{$category->getParent()->getLabel()}\"");
            }
        }
    }

    private function deleteUnusedTranslations(TranslationCategory $category, array $translations) {
        $translationSourceRepository = $this->manager->getRepository(TranslationSource::class);

        if($category->getId()) {
            $fixtureTranslations = array_map(fn(array $item) => $item["fr"], $translations);
            $unusedTranslations = $translationSourceRepository->findUnusedTranslations($category, $fixtureTranslations);
            foreach($unusedTranslations as $source) {
                $this->manager->remove($source);
                $this->console->writeln("Deleting unused source \"{$source->getTranslationIn("french-default")->getTranslation()}\" and all associated translations");
            }
        }
    }

    private function getLanguage(string $slug) {
        if(!isset($this->languages[$slug])) {
            $this->languages[$slug] = $this->manager->getRepository(Language::class)->findOneBy(["slug" => $slug]);
        }

        return $this->languages[$slug];
    }

    private function updateUsers() {
        $users = $this->manager->getRepository(Utilisateur::class)->findAll();
        $french = $this->manager->getRepository(Language::class)->findOneBy(["slug" => "french"]);

        foreach($users as $user) {
            if(!$user->getLanguage() || !$user->getDateFormat()) {
                $user->setLanguage($french)
                    ->setDateFormat("jj/mm/aaaa");
            }
        }
    }

    public static function getGroups(): array {
        return ["fixtures", "language"];
    }


    private function initTranslationSources() {
        $natures = $this->manager->getRepository(Nature::class)->findBy(['labelTranslation' => null]);

        foreach ($natures as $nature) {
            $natureSource = new TranslationSource();
            $this->manager->persist($natureSource);

            $natureTranslation = new Translation();
            $natureTranslation
                ->setLanguage($this->getLanguage(Translation::FRENCH_SLUG))
                ->setSource($natureSource)
                ->setTranslation($nature->getLabel());

            $natureSource->addTranslation($natureTranslation);
            $nature->setLabelTranslation($natureSource);
            $this->manager->persist($natureTranslation);
        }
    }
}
