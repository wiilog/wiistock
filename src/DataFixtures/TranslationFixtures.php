<?php

namespace App\DataFixtures;

use App\Entity\Language;
use App\Entity\Translation;
use App\Entity\TranslationCategory;
use App\Entity\TranslationSource;
use App\Entity\Utilisateur;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Console\Output\ConsoleOutput;

class TranslationFixtures extends Fixture implements FixtureGroupInterface
{

    const TRANSLATIONS = [
        "Général" => [
            null => [
                "Header" => [
                    "content" => [
                        [
                            "fr" => "Accueil",
                            "en" => "Home",
                        ],
                        [
                            "fr" => "Wiilog",
                            "en" => "Wiilog",
                        ],
                        [
                            "fr" => "Détails",
                            "en" => "Details",
                        ],
                        [
                            "fr" => "Déconnexion",
                            "en" => "Log out",
                        ],
                        [
                            "fr" => "Format de date",
                            "en" => "Date format",
                        ],
                    ],
                ],
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
                            "fr" => "Utilisateurs",
                            "en" => "Users",
                            "tooltip" => "Filtre Association BR",
                        ],
                        [
                            "fr" => "Filtrer",
                            "en" => "Filter",
                        ],
                        [
                            "fr" => "Veuillez entrer au moins {1} caractère{2}.",
                            "en" => "Please enter at least {1} character{2}.",
                        ],
                        [
                            "fr" => "Recherche en cours...",
                            "en" => "Search in progress...",
                        ],
                        [
                            "fr" => "Aucun résultat.",
                            "en" => "No results.",
                        ],
                        [
                            "fr" => "Ajouter des éléments",
                            "en" => "Add elements",
                        ],
                        [
                            "fr" => "Unité logistique",
                            "en" => "Logistic unit",
                        ],
                        [
                            "fr" => "Unité(s) logistique(s)",
                            "en" => "Logistic unit(s)",
                        ],
                    ],
                ],
                "Zone liste" => [
                    "subtitle" => "Les libellés génériques présents pour la recherche rapide les boutons d’action et la pagination",
                    "content" => [
                        [
                            "fr" => "Rechercher : ",
                            "en" => "Search: ",
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
                            "tooltip" => "Page Arrivages UL :\nZone liste - Nom de colonnes\nGestion des colonnes\nDétails arrivage UL - Liste des litiges\n_____\nPage Urgences :\nZone liste - Nom de colonnes\n_____\nPage acheminements :\nZone liste - Nom de colonnes\nDétails acheminements - Entête\nPDF bon acheminement\nEmail de traitement\nEmail de changement de statut",
                        ],
                        [
                            "fr" => "Traité par",
                            "en" => "Finished by",
                            "tooltip" => "Page acheminements :\nZone liste - Nom de colonnes\nDétails\nEmails\n_____\nPage service:\nZone liste - Nom de colonnes\nDétails",
                        ],
                        [
                            "fr" => "Du {1} au {2}",
                            "en" => "From {1} to {2}",
                            "tooltip" => "Page acheminements :\nEmails",
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
                            "fr" => "Chargement en cours",
                            "en" => "Loading",
                        ],
                        [
                            "fr" => "Traitement en cours",
                            "en" => "Loading",
                        ],
                        [
                            "fr" => "Imprimer",
                            "en" => "Print",
                        ],
                        [
                            "fr" => "Vos préférences de colonnes à afficher ont bien été sauvegardées",
                            "en" => "Your displayed columns preferences have been saved",
                        ],
                        [
                            "fr" => "Vos préférences d'ordre de colonnes ont bien été enregistrées",
                            "en" => "Your column order preferences have been saved",
                        ],
                        [
                            "fr" => "Succès",
                            "en" => "Success",
                        ],
                        [
                            "fr" => "Erreur",
                            "en" => "Error",
                        ],
                        [
                            "fr" => "Information",
                            "en" => "Information",
                        ],
                        [
                            "fr" => "Aucun élément à afficher",
                            "en" => "No elements to display",
                        ],
                        [
                            "fr" => "Aucune donnée disponible",
                            "en" => "No data available",
                        ],
                        [
                            "fr" => "(filtré de {1} éléments au total)",
                            "en" => "(filtered by {1} elements in total)",
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
                            "fr" => "Commentaire",
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
                            "en" => "Edit",
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
                        [
                            "fr" => "Enregistrer",
                            "en" => "Save",
                        ],
                        [
                            "fr" => "Veuillez renseigner le champ : {1}",
                            "en" => "Please fill in the field : {1}",
                        ],
                        [
                            "fr" => "Veuillez renseigner les champs : {1}",
                            "en" => "Please fill in the fields : {1}",
                        ],
                        [
                            "fr" => "Veuillez saisir des dates dans le filtre en haut de page.",
                            "en" => "Please enter dates in the filter at the top of the page.",
                        ],
                        [
                            "fr" => "Veuillez renseigner au moins un {1}",
                            "en" => "Please fill in at least one {1}",
                        ],
                        [
                            "fr" => "L'opération est en cours de traitement",
                            "en" => "The operation is currently being processed",
                        ],
                        [
                            "fr" => "Le commentaire excède les {1} caractères maximum.",
                            "en" => "The comment exceeds {1} characters maximum.",
                        ],
                        [
                            "fr" => "Vous devez ajouter au moins une pièce jointe.",
                            "en" => "You must add at least one attachment.",
                        ],
                        [
                            "fr" => "\"{1}\" : Le format de votre pièce jointe n'est pas supporté. Le fichier doit avoir une extension.",
                            "en" => "''{1}'' : The format of your attachment is not supported. The file must have an extension.",
                        ],
                        [
                            "fr" => "\"{1}\" : La taille du fichier ne doit pas dépasser 10 Mo.",
                            "en" => "\"{1}\": The file size must not exceed 10 MB.",
                        ],
                        [
                            "fr" => "Le nombre de caractères du champ {1} ne peut être supérieur à {2}.",
                            "en" => "{1} field length can't be upper than {2}",
                        ],
                        [
                            "fr" => "Le nombre de caractères du champ {1} ne peut être inférieur à {2}.",
                            "en" => "{1} field length can't be lower than {2}",
                        ],
                        [
                            "fr" => "Le nombre de caractères de ce champ ne peut être inférieur à {1}.",
                            "en" => "Field length can't be upper than {1}",
                        ],
                        [
                            "fr" => "Le nombre de caractères de ce champ ne peut être inférieur à {1}.",
                            "en" => "Field length can't be lower than {1}",
                        ],
                    ],
                ],
                "Emails" => [
                    "content" => [
                        [
                            "fr" => "Bonjour,",
                            "en" => "Hello,",
                        ],
                        [
                            "fr" => "Bonne journée,",
                            "en" => "Have a nice day,",
                        ],
                        [
                            "fr" => "L'équipe Wiilog.",
                            "en" => "The Wiilog team",
                        ],
                        [
                            "fr" => "Cliquez ici pour accéder à ",
                            "en" => "Click here to access ",
                        ],
                    ]
                ]
            ],
        ],
        "Dashboard" => [
            "content" => [
                [
                    "fr" => "Unité logistique",
                    "en" => "Logistic unit",
                    "tooltip" => "Composant \"UL en retard\"\nComposant \"Nombre d'arrivages UL et d'UL quotidiens\"\nComposant \"Nombres d'arrivages et d'UL hebdomadaires\"",
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
                    "fr" => "Arrivages UL",
                    "en" => "LU Arrivals",
                    "tooltip" => "Composant \"Nombre d'arrivages UL et d'UL quotidiens\"\nComposant \"Nombres d'arrivages UL et d'UL hebdomadaires\"",
                ],
                [
                    "fr" => "Nombre de lignes à traiter",
                    "en" => "Number of lines to process",
                    "tooltip" => "Composant \"Entrées à effectuer\"",
                ],
                [
                    "fr" => "Prochain emplacement à traiter",
                    "en" => "Next location to process",
                    "tooltip" => "Composant \"Entrées à effectuer\"",
                ],
                [
                    "fr" => "Prochain élément à traiter",
                    "en" => "Next element to process",
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
                    "en" => "Transfer date not estimated",
                    "tooltip" => "Composant \"Demande en cours\"",
                ],
                [
                    "fr" => "Date de traitement non estimée",
                    "en" => "Process date not estimated",
                    "tooltip" => "Composant \"Demande en cours\"",
                ],
                [
                    "fr" => "Date de livraison non estimée",
                    "en" => "Delivery date not estimated",
                    "tooltip" => "Composant \"Demande en cours\"",
                ],
                [
                    "fr" => "Date de collecte non estimée",
                    "en" => "Collect date not estimated",
                    "tooltip" => "Composant \"Demande en cours\"",
                ],
                [
                    "fr" => "Date de transfert non estimée",
                    "en" => "Transfer date not estimated",
                    "tooltip" => "Composant \"Demande en cours\"",
                ],
                [
                    "fr" => "Date et heure d'acheminement prévue",
                    "en" => "Expected date and hour of shipment",
                    "tooltip" => "Composant \"Demande en cours\"",
                ],
                [
                    "fr" => "Date et heure de transfert prévue",
                    "en" => "Expected date and hour of transfer",
                    "tooltip" => "Composant \"Demande en cours\"",
                ],
                [
                    "fr" => "Date et heure de collecte prévue",
                    "en" => "Expected date and hour of collect",
                    "tooltip" => "Composant \"Demande en cours\"",
                ],
                [
                    "fr" => "Date et heure de traitement prévue",
                    "en" => "Expected date and hour of process",
                    "tooltip" => "Composant \"Demande en cours\"",
                ],
                [
                    "fr" => "Date et heure de livraison prévue",
                    "en" => "Expected date and hour of delivery",
                    "tooltip" => "Composant \"Demande en cours\"",
                ],
                [
                    "fr" => "Heure de traitement estimée",
                    "en" => "Estimated process time",
                    "tooltip" => "Composant \"Demande en cours\"",
                ],
                [
                    "fr" => "Heure d'acheminement estimée",
                    "en" => "Estimated transport time",
                    "tooltip" => "Composant \"Demande en cours\"",
                ],
                [
                    "fr" => "Heure de collecte estimée",
                    "en" => "Estimated collect time",
                    "tooltip" => "Composant \"Demande en cours\"",
                ],
                [
                    "fr" => "Heure de livraison estimée",
                    "en" => "Estimated delivery time",
                    "tooltip" => "Composant \"Demande en cours\"",
                ],
                [
                    "fr" => "Heure de transfert estimée",
                    "en" => "Estimated delivery time",
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
                    "fr" => "Dont {1} urgences",
                    "en" => "Including {1} urgencies",
                    "tooltip" => "Composant \"Nombre de services du jour\", coche \"Afficher le nombre d'urgences de la journée\"",
                ],
                [
                    "fr" => "urgences",
                    "en" => "urgencies",
                    "tooltip" => "Composant \"Nombre de services du jour\", coche \"Afficher le nombre d'urgences de la journée\"",
                ],
                [
                    "fr" => "A traiter sous :",
                    "en" => "To process within:",
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
                        "en" => "Logistic units",
                        "tooltip" => "Page Arrivages UL :\nDétails arrivage - Liste des UL - Nom de colonnes\nDétails arrivage - Liste des litiges - Modale Nouveau litige\nDétails arrivage - Liste des litiges - Modifier litige\nEmail litige\n_____\nPage Mouvements : \nFiltre\nZone liste - Nom de colonnes\nGestion des colonnes\nModale Nouveau mouvement\nModale Modifier un mouvement\n_____\nPage UL :\nMenu\nFil d'ariane\nFiltre\nOnglet\nOnglet UL - Colonne\nOnglet UL - Modale Modifier une unité logistique\nOnglet Groupes - Carte UL dans Carte Groupe\n_____\nPage Association BR :\nFiltre \nZone liste - Nom de colonnes \n_____\nPage Encours :\nCarte emplacement - Zone liste - Nom de colonnes",
                    ],
                    [
                        "fr" => "Unité logistique",
                        "en" => "Logistic unit",
                        "tooltip" => "Page Mouvements :\nFiltre\nZone liste - Nom de colonnes\nGestion des colonnes\nModale Nouveau mouvement\nModale Modifier un mouvement\n_____\nPage Association BR :\nFiltre\nZone liste - Nom de colonnes",
                    ],
                    [
                        "fr" => "Emplacement",
                        "en" => "Location",
                        "tooltip" => "Page UL :\nFiltre\nZone liste - Nom de colonnes\n_____\nPage Mouvements :\nZone liste - Nom de colonnes\nFiltre\nModale Nouveau mouvement\nModale Modifier un mouvement",
                    ],
                    [
                        "fr" => "Emplacement encours",
                        "en" => "Ongoing location",
                        "tooltip" => "Page UL :\nFiltre\nZone liste - Nom de colonnes",
                    ],
                    [
                        "fr" => "Dernier emplacement",
                        "en" => "Last location",
                        "tooltip" => "Page UL :\nFiltre\nZone liste - Nom de colonnes",
                    ],
                    [
                        "fr" => "Emplacement sur lequel se trouve l'unité logistique actuellement",
                        "en" => "Location where the logistics unit is currently located",
                        "tooltip" => "Page UL :\nFiltre\nZone liste - Nom de colonnes\n_____\nPage Mouvements :\nZone liste - Nom de colonnes\nFiltre\nModale Nouveau mouvement\nModale Modifier un mouvement",
                    ],
                    [
                        "fr" => "Date",
                        "en" => "Date",
                        "tooltip" => "Page Mouvements :\nZone liste - Nom de colonnes\nGestion des colonnes\nModale Nouveau mouvement\nModale Modifier un mouvement\n_____\nPage UL : \nModale Modifier une unité logistique\n_____\nPage Association BR :\nZone liste - Nom de colonnes",
                    ],
                    [
                        "fr" => "Utilisateur",
                        "en" => "User",
                        "tooltip" => "Page Arrivages UL :\nZone liste - Nom de colonnes\nModifier litige - Tableau Historique\n_____\nPage Association BR :\nZone liste - Nom de colonnes",
                    ],
                    [
                        "fr" => "Opérateur",
                        "en" => "Worker",
                        "tooltip" => "Page Arrivages UL :\nDétails arrivage - Liste UL - Colonne\n_____\nPage Mouvements :\nModale Nouveau mouvement\nModale Modifier un mouvement\nModale Détail de mouvement",
                    ],
                    [
                        "fr" => "Quantité",
                        "en" => "Quantity",
                        "tooltip" => "Page Mouvements :\nZone liste - Nom de colonnes\n Modale Modifier un mouvement\nModale Nouveau mouvement\n______\nPage UL :\nOnglet UL - Zone liste - Nom de colonnes\nOnglet UL - Modale Modifier une unité logistique\nOnglet UL - contenu UL",
                    ],
                    [
                        "fr" => "Nature",
                        "en" => "Nature",
                        "tooltip" => "Page Arrivages UL :\nDétails arrivage - Liste des UL - Colonne\n_____\nPage UL :\nOnglet UL - Colonne\nOnglet UL - Modale Modifier une unité logistique\nOnglet Groupes - Carte groupe\nOnglet Groupes - Carte UL dans Carte groupe\nOnglet Groupes - Exports",
                    ],
                    [
                        "fr" => "Natures",
                        "en" => "Natures",
                        "tooltip" => "Page Unités Logistiques :\nFiltre\n_____\nPage Encours :\nFiltre",
                    ],
                    [
                        "fr" => "Issu de",
                        "en" => "From",
                        "tooltip" => "Page Mouvements : \nZone liste - Nom de colonnes\nGestion des colonnes\n_____\nPage UL : \nZone liste - Nom de colonnes\nExport UL\nExport groupes",
                    ],
                    [
                        "fr" => "Issu de (numéro)",
                        "en" => "From (number)",
                        "tooltip" => "Page UL : \nExport UL\nExport groupes",
                    ],
                    [
                        "fr" => "Date dernier mouvement",
                        "en" => "Last movement date",
                        "tooltip" => "Page UL :\nZone liste - Nom de colonnes\n_____\nPage Arrivages UL :\nDétails arrivage UL - Liste des UL - Nom de colonnes",
                    ],
                    [
                        "fr" => "Dernier emplacement",
                        "en" => "Last location",
                        "tooltip" => "Page Arrivages UL :\nDétails arrivage UL - Liste des UL - Nom de colonnes",
                    ],
                    [
                        "fr" => "Sélectionner une nature",
                        "en" => "Select nature",
                        "tooltip" => "Modale modification groupe",
                    ],
                    [
                        "fr" => "{1} à {2}",
                        "en" => "{1} at {2}",
                        "tooltip" => "Email de confirmation de livraison d'unité logistique (pour la date)"
                    ],
                    [
                        "fr" => "Dépose effectuée",
                        "en" => "Drop done",
                        "tooltip" => "Email de confirmation de livraison d'unité logistique",
                    ],
                    [
                        "fr" => "Votre unité logistique a été livrée",
                        "en" => "Your logistic unit has been delivered",
                        "tooltip" => "Email de confirmation de livraison d'unité logistique",
                    ],
                    [
                        "fr" => "Article",
                        "en" => "Item",
                        "tooltip" => "Page Mouvements : \nZone liste - Nom de colonnes\nGestion des colonnes",
                    ],
                ],
            ],
            "Arrivages UL" => [
                "Divers" => [
                    "content" => [
                        [
                            "fr" => "Arrivages unités logistiques",
                            "en" => "Logistic units arrivals",
                            "tooltip" => "Page Arrivages UL :\nMenu\nFil d'ariane",
                        ],
                        [
                            "fr" => "N° d'arrivage UL",
                            "en" => "LU Arrival number",
                            "tooltip" => "Page Arrivages UL : \nFiltre\nZone liste - Nom de colonnes\nGestion des colonnes\nDétails arrivage UL - Liste des litiges - Modale Nouveau litige\n_____\nPage Urgences :\nZone liste - Nom de colonnes\n_____\nPage Qualité - Litiges :\nZone liste - Nom de colonnes",
                        ],
                        [
                            "fr" => "Fournisseurs",
                            "en" => "Suppliers",
                            "tooltip" => "Page Arrivages UL :\nFiltre",
                        ],
                        [
                            "fr" => "Transporteurs",
                            "en" => "Carriers",
                            "tooltip" => "Page Arrivages UL :\nFiltre",
                        ],
                        [
                            "fr" => "Destinataire(s)",
                            "en" => "Recipients",
                            "tooltip" => "Page Arrivages UL :\nFiltre",
                        ],
                        [
                            "fr" => "Statuts",
                            "en" => "Statuses",
                            "tooltip" => "Page Arrivages UL :\nFiltres",
                        ],
                        [
                            "fr" => "Urgence",
                            "en" => "Emergency",
                            "tooltip" => "Page Arrivages UL :\nFiltre\nModale Nouveau litige\nModale Modifier le litige",
                        ],
                        [
                            "fr" => "Urgent",
                            "en" => "Urgent",
                            "tooltip" => "Page Arrivages UL :\nZone liste - Nom de colonnes\nGestion des colonnes",
                        ],
                        [
                            "fr" => "Nombre d'UL",
                            "en" => "Quantity L.U.",
                            "tooltip" => "Page Arrivages UL :\nZone liste - Nom de colonnes\nGestion des colonnes",
                        ],
                        [
                            "fr" => "Poids total (kg)",
                            "en" => "Total weight (kg)",
                            "tooltip" => "Page Arrivages UL :\nZone liste - Nom de colonnes\nGestion des colonnes",
                        ],
                        [
                            "fr" => "Nouvel arrivage UL",
                            "en" => "New LU arrival",
                            "tooltip" => "Page Arrivages UL :\nBouton\nModale Nouvel arrivage UL",
                        ],
                        [
                            "fr" => "Ajouter des unités logistiques",
                            "en" => "Add logistic units",
                            "tooltip" => "Page Arrivages UL :\nDétails arrivages - Liste des unités logistiques - Bouton\nDétails arrivages - Liste des unités logistiques - Modale Ajouter unité logistique",
                        ],
                        [
                            "fr" => "Liste des unités logistiques",
                            "en" => "Logistic unit list",
                            "tooltip" => "Détails arrivages UL - Liste des unités logistiques",
                        ],
                        [
                            "fr" => "Nombre d'unités logistiques à ajouter :",
                            "en" => "Quantity of L.U. to add :",
                            "tooltip" => "Page Arrivages UL :\nModale Nouvel arrivage UL\nDétails arrivages - Liste des UL - Modale Ajouter une UL",
                        ],
                        [
                            "fr" => "Arrivage UL",
                            "en" => "LU Arrival",
                            "tooltip" => "Page Arrivages UL :\nFil d'ariane\nDétails arrivage UL - Entête\nEmail arrivage UL",
                        ],
                        [
                            "fr" => "arrivage UL",
                            "en" => "LU arrival",
                            "tooltip" => "Page Arrivages UL :\nFil d'ariane\nDétails arrivage UL - Entête\nEmail arrivage UL",
                        ],
                        [
                            "fr" => "Modifier arrivage UL",
                            "en" => "Edit LU arrival",
                            "tooltip" => "Page Arrivages UL :\nModale Modifier arrivage UL",
                        ],
                        [
                            "fr" => "Supprimer l'arrivage UL",
                            "en" => "Delete the LU arrival",
                            "tooltip" => "Page Arrivages UL :\nModale Supprimer l'arrivage UL",
                        ],
                        [
                            "fr" => "Voulez-vous réellement supprimer cet arrivage UL ?",
                            "en" => "Do you really want to delete this LU arrival ?",
                            "tooltip" => "Page Arrivages UL :\nModale Supprimer l'arrivage UL",
                        ],
                        [
                            "fr" => "(attention, un litige a été créé sur cet arrivage UL : il sera également supprimé)",
                            "en" => "(attention, a dispute has been created on this LU arrival: it will also be deleted)",
                            "tooltip" => "Page Arrivages UL :\nModale Supprimer l'arrivage UL",
                        ],
                        [
                            "fr" => "Liste des UL générées",
                            "en" => "List of L.U",
                            "tooltip" => "Page Arrivages UL :\nModale Liste des UL générés",
                        ],
                        [
                            "fr" => "Impression",
                            "en" => "Print label",
                            "tooltip" => "Page Arrivages UL :\nModale Liste des UL générées",
                        ],
                        [
                            "fr" => "N° UL",
                            "en" => "N°L.U.",
                            "tooltip" => "Page Arrivages UL :\nModale Liste des UL générées",
                        ],
                        [
                            "fr" => "Réceptionner",
                            "en" => "Receipt",
                            "tooltip" => "Zone liste - 3 points\nDétail arrivage UL entête",
                        ],
                        [
                            "fr" => "Un autre arrivage UL est en cours de création, veuillez réessayer",
                            "en" => "Another LU arrival is being created, please try again",
                        ],
                        [
                            "fr" => "Un autre litige d'arrivage UL est en cours de création, veuillez réessayer",
                            "en" => "Another LU arrival dispute is being created, please try again",
                        ],
                    ],
                ],
                "Champs fixes" => [
                    "content" => [
                        [
                            "fr" => "Fournisseur",
                            "en" => "Supplier",
                            "tooltip" => "Arrivages UL :\nZone liste - Nom de colonnes\nGestion des colonnes\nModale Nouvel arrivage UL\nModale Modifier arrivage UL\nArrivage détails - Entête\nModale Nouveau litige\n_____\nUrgences :\nZone liste - Nom de colonnes\nModale Nouvelle urgence\nModale Modifier une urgence",
                        ],
                        [
                            "fr" => "Transporteur",
                            "en" => "Carrier",
                            "tooltip" => "Zone liste - Nom de colonnes\nGestion des colonnes\nModale Nouvel arrivage UL\nModale Modifier arrivage UL\nDétails arrivage UL - Entête\nModale Nouveau litige",
                        ],
                        [
                            "fr" => "Chauffeur",
                            "en" => "Driver",
                            "tooltip" => "Zone liste - Nom de colonnes\nGestion des colonnes\nModale Nouvel arrivage UL\nModale Modifier arrivage UL\nArrivages détails - Entête\nModale Nouveau litige",
                        ],
                        [
                            "fr" => "N° tracking transporteur",
                            "en" => "Carrier tracking number",
                            "tooltip" => "Arrivages :\nZone liste - Nom de colonnes\nGestion des colonnes\nModale Nouvel arrivage UL\nModale Modifier arrivage UL\nArrivages UL détails - Entête\nModale Nouveau litige\n_____\nUrgences :\nZone liste - Nom de colonnes\nModale Nouvelle urgence\nModale Modifier une urgence",
                        ],
                        [
                            "fr" => "N° commande / BL",
                            "en" => "Order number",
                            "tooltip" => "Zone liste - Nom de colonnes\nGestion des colonnes\nModale Nouvel arrivage UL\nModale Modifier arrivage UL\nDétails arrivage UL - Entête\nModale Nouveau litige\nEmail litige",
                        ],
                        [
                            "fr" => "Type",
                            "en" => "Type",
                            "tooltip" => "Détails arrivage UL - Entête\nZone liste - Nom de colonnes\nGestion des colonnes\nModale Nouvel arrivage UL\nModale Modifier arrivage UL",
                        ],
                        [
                            "fr" => "Statut",
                            "en" => "Status",
                            "tooltip" => "Arrivages unités logistiques :\nZone liste - Nom de colonnes\nModale Nouvel arrivage UL\nModale Modifier arrivage UL\nDétail arrivages UL - Entête\nDétail arrivages - Liste des litiges - Colonne\nModale Nouveau litige\nModale Modifier le litige",
                        ],
                        [
                            "fr" => "Emplacement de dépose",
                            "en" => "Drop location",
                            "tooltip" => "Détails arrivage UL - Entête\nZone liste - Nom de colonnes\nGestion des colonnes\nModale Nouvel arrivage UL\nModale Modifier arrivage UL",
                        ],
                        [
                            "fr" => "Destinataire(s)",
                            "en" => "Addressee",
                            "tooltip" => "Arrivages :\nZone liste - Nom de colonnes\nGestion des colonnes\nModale Nouvel arrivage UL\nModale Modifier arrivage UL\nArrivage UL détails - Entête",
                        ],
                        [
                            "fr" => "Acheteur(s)",
                            "en" => "Buyer(s)",
                            "tooltip" => "Zone liste - Nom de colonnes\nGestion des colonnes\nModale Nouvel arrivage UL\nArrivages UL détails - Entête\nModale Nouveau litige",
                        ],
                        [
                            "fr" => "Imprimer arrivage UL",
                            "en" => "Print LU arrival",
                            "tooltip" => "Modale Nouvel arrivage UL\nDétails arrivages UL - Entête - Bouton",
                        ],
                        [
                            "fr" => "Imprimer unités logistiques",
                            "en" => "Print L.U.",
                            "tooltip" => "Modale Nouvel arrivage UL\nDétails arrivages UL - Liste des UL - Bouton",
                        ],
                        [
                            "fr" => "Numéro de projet",
                            "en" => "Project number",
                            "tooltip" => "Zone liste - Nom de colonnes\nGestion des colonnes\nModale Nouvel arrivage UL\nModale Modifier arrivage UL\nDétails arrivage UL - Entête",
                        ],
                        [
                            "fr" => "Business unit",
                            "en" => "Business unit",
                            "tooltip" => "Détails arrivage UL - Entête\nZone liste - Nom de colonnes\nGestion des colonnes\nModale Nouvel arrivage UL\nModale Modifier arrivage UL",
                        ],
                        [
                            "fr" => "Douane",
                            "en" => "Customs",
                            "tooltip" => "Filtre\nZone liste - Nom de colonnes\nGestion des colonnes\nModale Nouvel arrivage UL\nModale Modifer arrivage UL\nArrivages UL détails - Entête",
                        ],
                        [
                            "fr" => "Congelé",
                            "en" => "Frozen",
                            "tooltip" => "Filtre \nZone liste - Nom de colonnes\nGestion des colonnes\nModale Nouvel arrivage UL\nModale Modifer arrivage UL\nArrivages UL détails - Entête",
                        ],
                        [
                            "fr" => "Projet",
                            "en" => "Project",
                            "tooltip" => "Modale Nouvel arrivage UL\nModale Modifer arrivage UL\nArrivages UL détails - Liste des unités logistiques - Modale modifier une UL",
                        ],
                    ],
                ],
                "Détails arrivage UL - Entête" => [
                    "content" => [
                        [
                            "fr" => "Cet arrivage UL est à traiter en URGENCE",
                            "en" => "This LU arrival needs to be dealt with urgently",
                            "tooltip" => "Détails arrivage UL - Entête (arrivage UL urgent)",
                        ],
                        [
                            "fr" => "Acheminer",
                            "en" => "Transfer",
                            "tooltip" => "Zone liste - Bouton\nDétails arrivages UL - Entête - Bouton",
                        ],
                    ],
                ],
                "Modale création nouvel arrivage UL" => [
                    "content" =>[
                        [
                            "fr" => "Nom",
                            "en" => "Surname",
                            "tooltip" => "Création Fournisseur\nCréation Transporteur\nCréation Chauffeur",
                        ],
                        [
                            "fr" => "Code",
                            "en" => "Code",
                            "tooltip" => "Création Fournisseur\nCréation Transporteur",
                        ],
                        [
                            "fr" => "Prénom",
                            "en" => "First name",
                            "tooltip" => "Création Chauffeur",
                        ],
                        [
                            "fr" => "DocumentID",
                            "en" => "DocumentID",
                            "tooltip" => "Création Chauffeur",
                        ],
                        [
                            "fr" => "Type à choisir...",
                            "en" => "Choose a type...",
                            "tooltip" => "Modale Nouvel arrivage UL",
                        ],
                        [
                            "fr" => "Choisir un statut...",
                            "en" => "Choose a status...",
                            "tooltip" => "Modale Nouvel arrivage UL",
                        ],
                        [
                            "fr" => "Créer",
                            "en" => "Create",
                            "tooltip" => "Modale Nouvel arrivage UL",
                        ],
                        [
                            "fr" => "Veuillez renseigner au moins une unité logistique",
                            "en" => "Fill in at least one logistic unit",
                            "tooltip" => "Modale Nouvel arrivage UL",
                        ],
                    ],
                ],
                "Détails arrivage UL - Liste des litiges" => [
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
                            "tooltip" => "Détails arrivage UL - Liste des litiges - Colonne\nModale Nouveau litige\nModale Modifier le litige",
                        ],
                        [
                            "fr" => "Déclarant",
                            "en" => "Declarant",
                            "tooltip" => "Modale Nouveau litige\nModale Modifier le litige",
                        ],
                        [
                            "fr" => "Modifier le litige",
                            "en" => "Edit the dispute",
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
                "Email arrivage UL" => [
                    "content" => [
                        [
                            "fr" => "Arrivage UL",
                            "en" => "LU Arrivals",
                            "tooltip" => "Email arrivage UL : objet ",
                        ],
                        [
                            "fr" => "Arrivage UL urgent",
                            "en" => "Urgent LU arrival",
                            "tooltip" => "Email arrivage UL : objet ",
                        ],
                        [
                            "fr" => "Arrivage UL reçu : le {1} à {2}",
                            "en" => "LU Arrival received : on {1} at {2}",
                            "tooltip" => "Email arrivage UL",
                        ],
                        [
                            "fr" => "Votre commande est arrivée :",
                            "en" => "Your order has arrived",
                            "tooltip" => "Email arrivage UL",
                        ],
                        [
                            "fr" => "Votre commande urgente est arrivée :",
                            "en" => "Your urgent order has arrived:",
                            "tooltip" => "Email arrivage UL",
                        ],
                        [
                            "fr" => "Unités logistiques réceptionnées :",
                            "en" => "Receipted logistic units",
                            "tooltip" => "Email arrivage UL",
                        ],
                        [
                            "fr" => "Nature",
                            "en" => "Nature",
                            "tooltip" => "Email arrivage UL",
                        ],
                        [
                            "fr" => "Quantité",
                            "en" => "Quantity",
                            "tooltip" => "Email arrivage UL",
                        ],
                    ],
                ],
                "Email litige" => [
                    "content" => [
                        [
                            "fr" => "Litige sur {1}",
                            "en" => "Dispute on {1}",
                            "tooltip" => "Email litige : objet",
                        ],
                        [
                            "fr" => "Changement de statut d'un litige sur {1}",
                            "en" => "Change of status of a dispute on {1}",
                            "tooltip" => "Email litige : objet",
                        ],
                        [
                            "fr" => "Récapitulatif de vos litiges",
                            "en" => "Summary of your disputes",
                            "tooltip" => "Email litige : objet",
                        ],

                        [
                            "fr" => "Un litige a été déclaré sur {1} vous concernant :",
                            "en" => "A dispute has been declared on {1} concerning you:",
                            "tooltip" => "Email litige",
                        ],
                        [
                            "fr" => "Changement de statut d'un litige sur {1} vous concernant :",
                            "en" => "Change the status of a dispute on {1} concerning you:",
                            "tooltip" => "Email litige",
                        ],
                        [
                            "fr" => "un arrivage UL",
                            "en" => "a LU arrival",
                            "tooltip" => "Email litige",
                        ],
                        [
                            "fr" => "1 litige vous concerne :",
                            "en" => "1 dispute concerns you:",
                            "tooltip" => "Email litige",
                        ],
                        [
                            "fr" => "{1} litiges vous concernent :",
                            "en" => "{1} disputes concern you:",
                            "tooltip" => "Email litige",
                        ],
                        [
                            "fr" => "Type de litige",
                            "en" => "Type of dispute",
                            "tooltip" => "Email litige",
                        ],
                        [
                            "fr" => "Statut du litige",
                            "en" => "Status of dispute",
                            "tooltip" => "Email litige",
                        ],
                        [
                            "fr" => "Récapitulatif de vos litiges",
                            "en" => "Summary of your disputes",
                            "tooltip" => "Email litige",
                        ],
                    ],
                ],
                "Modale Nouvelle demande d'acheminement" => [
                    "subtitle" => "La plupart des libellés ont leur traduction qui s'applique à partir de la page Acheminement",
                    "content" => [
                        [
                            "fr" => "Annuler acheminer",
                            "en" => "Cancel transfer",
                            "tooltip" => "Zone liste - Mode acheminer",
                        ],
                        [
                            "fr" => "Valider arrivages UL à acheminer",
                            "en" => "Validate LU arrivals to transfer",
                            "tooltip" => "Zone liste - Mode acheminer",
                        ],
                        [
                            "fr" => "Demande d'acheminement",
                            "en" => "Transfer operation",
                            "tooltip" => "Mode acheminer",
                        ],
                        [
                            "fr" => "Créer une nouvelle demande",
                            "en" => "Create a new operation",
                            "tooltip" => "Modale acheminer",
                        ],

                        [
                            "fr" => "Ajouter à une demande existante",
                            "en" => "Add to an existing operation",
                            "tooltip" => "Modale acheminer",
                        ],
                        [
                            "fr" => "Ma demande d'acheminement",
                            "en" => "My transfer operation",
                            "tooltip" => "Mode acheminer",
                        ],
                        [
                            "fr" => "Mes demandes",
                            "en" => "My operations",
                            "tooltip" => "Mode acheminer",
                        ],
                        [
                            "fr" => "Sélectionnez  un acheminement",
                            "en" => "Select a transfer",
                            "tooltip" => "Mode acheminer",
                        ],
                        [
                            "fr" => "Unités logistiques à acheminer",
                            "en" => "Logistic unit to ship",
                            "tooltip" => "Modale acheminer",
                        ],
                    ],
                ],
            ],
            "Unités logistiques" => [
                "Divers" => [
                    "content" => [
                        [
                            "fr" => "N° d'arrivage UL",
                            "en" => "LU Arrival number",
                            "tooltip" => "Filtre",
                        ],
                        [
                            "fr" => "Poids (kg)",
                            "en" => "Weight (kg)",
                            "tooltip" => "Page Unité logistique\nZone liste\nModale Modifier une unité logistique\n____\nExport",
                        ],
                        [
                            "fr" => "Volume (m3)",
                            "en" => "Volume (m3)",
                            "tooltip" => "Page Unité logistique\nZone liste\nModale Modifier une unité logistique\n____\nExport",
                        ],
                        [
                            "fr" => "Délai de traitement",
                            "en" => "Processing Time",
                            "tooltip" => "Page Unité logistique\nZone liste\nModale Modifier une unité logistique\n____\nExport\n___\nNomade",
                        ],
                    ],
                ],
                "Onglet \"Unités logistiques\"" => [
                    "content" => [
                        [
                            "fr" => "Numéro d'UL",
                            "en" => "L.U. number",
                            "tooltip" => "Zone liste\nModale Modifier une unité logistique",
                        ],
                        [
                            "fr" => "Modifier une unité logistique",
                            "en" => "Edit L.U.",
                            "tooltip" => "Modale Modifier une unité logistique",
                        ],
                        [
                            "fr" => "Historique de groupage",
                            "en" => "Grouping history",
                            "tooltip" => "Modale Modifier une unité logistique",
                        ],
                        [
                            "fr" => "Historique des délais de traitement",
                            "en" => "Tracking delays history",
                            "tooltip" => "Page Unité logistique",
                        ],
                        [
                            "fr" => "Historique des données",
                            "en" => "Data history",
                            "tooltip" => "Zone liste",
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
                            "tooltip" => "Modale Supprimer l'UL",
                        ],
                        [
                            "fr" => "Voulez-vous réellement supprimer cette UL ?",
                            "en" => "Do you really want to delete this L.U. ?",
                            "tooltip" => "Modale Supprimer l'UL",
                        ],
                        [
                            "fr" => "{1} articles dans l'unité logistique. Cliquez pour voir le contenu.",
                            "en" => "{1} items in the logistic unit. Click to see the contents.",
                            "tooltip" => "Zone liste",
                        ],
                        [
                            "fr" => "Contenu unité logistique",
                            "en" => "Logistic unit content",
                            "tooltip" => "Zone liste - contenu UL",
                        ],
                        [
                            "fr" => "Articles",
                            "en" => "Items",
                            "tooltip" => "Zone liste - contenu UL",
                        ],
                        [
                            "fr" => "Historique des articles",
                            "en" => "Items history",
                            "tooltip" => "Zone liste - contenu UL",
                        ],
                        [
                            "fr" => "L'unité logistique ne contient aucun article actuellement",
                            "en" => "The logistic unit does not currently contain any items",
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
                        [
                            "fr" => "Liste des unités logistiques",
                            "en" => "L.U. list",
                        ],
                        [
                            "fr" => "L'unité logistique {1} a bien été supprimée",
                            "en" => "The L.U. {1} has been deleted",
                        ],
                        [
                            "fr" => "L'unité logistique {1} a bien été modifiée",
                            "en" => "The L.U. {1} has been edited",
                        ],
                        [
                            "fr" => "Cette unité logistique est utilisé dans l'arrivage UL {1}",
                            "en" => "This logistic unit is in use in the LU arrival {1}",
                        ],
                        [
                            "fr" => "Cette unité logistique est référencée dans un ou plusieurs mouvements de traçabilité",
                            "en" => "This logistic unit appears use in one or more movements",
                        ],
                        [
                            "fr" => "Cette unité logistique est référencée dans un ou plusieurs acheminements",
                            "en" => "This logistic unit appears in one or more transfers",
                        ],
                        [
                            "fr" => "Cette unité logistique est référencée dans un ou plusieurs litiges",
                            "en" => "This logistic unit appears in one or more disputes",
                        ],
                        [
                            "fr" => "Cette unité logistique est utilisé dans un ordre de livraison",
                            "en" => "This logistic unit is used in a delivery order",
                        ],
                        [
                            "fr" => "Cette unité logistique contient des articles",
                            "en" => "This logistic unit contains articles",
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
                            "fr" => "Dégrouper",
                            "en" => "Ungrouped",
                            "tooltip" => "Onglet \"Groupes\"",
                        ],
                        [
                            "fr" => "Vous êtes sur le point de dégrouper le groupe {1}. Les unités logistiques suivant seront déposés sur l'emplacement sélectionné : {2}.",
                            "en" => "You are about to ungroup the group {1}. The following logistic units will be dropped off at the selected location : {2}.",
                            "tooltip" => "Modale Dégrouper",
                        ],
                        [
                            "fr" => "Nombre d'UL",
                            "en" => "Quantity of L.U.",
                            "tooltip" => "Onglet \"Groupes\"",
                        ],
                        [
                            "fr" => "Numéro groupe",
                            "en" => "Group number",
                            "tooltip" => "Modale Modifier le groupe",
                        ],
                        [
                            "fr" => "Mouvementé la dernière fois le {1}",
                            "en" => "Moved for the last time on {1}",
                            "tooltip" => "Onglet \"Groupes\"",
                        ],
                        [
                            "fr" => "sur l'emplacement {1} par {2}",
                            "en" => "on the location {1} by {2}",
                            "tooltip" => "Onglet \"Groupes\"",
                        ],
                        [
                            "fr" => "Modifier le groupe",
                            "en" => "Edit group",
                            "tooltip" => "Modale Modifier le groupe",
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
                        "fr" => "Libellé",
                        "en" => "Label",
                        "tooltip" => "Zone liste - Nom de colonnes\nGestion des colonnes",
                    ],
                    [
                        "fr" => "Article",
                        "en" => "Item",
                        "tooltip" => "Zone liste - Nom de colonnes\nGestion des colonnes",
                    ],
                    [
                        "fr" => "Article(s)",
                        "en" => "Item(s)",
                        "tooltip" => "Modale nouveau mouvement (dépose dans UL)",
                    ],
                    [
                        "fr" => "Groupe",
                        "en" => "Group",
                        "tooltip" => "Zone liste - Nom de colonnes\nGestion des colonnes\n_____\nUL : \nModale Modifier une unité logistique",
                    ],
                    [
                        "fr" => "Type",
                        "en" => "Type",
                        "tooltip" => "Zone liste - Nom de colonnes\nGestion des colonnes\n_____\nUL : \nModale Modifier une unité logistique",
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
                        "fr" => "prise",
                        "en" => "pick up",
                        "tooltip" => "Modale Nouveau mouvement - Choix liste \"Action\"",
                    ],
                    [
                        "fr" => "depose",
                        "en" => "drop off",
                        "tooltip" => "Modale Nouveau mouvement - Choix liste \"Action\"",
                    ],
                    [
                        "fr" => "prises et deposes",
                        "en" => "pick-up and drop off",
                        "tooltip" => "Modale Nouveau mouvement - Choix liste \"Action\"",
                    ],
                    [
                        "fr" => "groupage",
                        "en" => "grouping",
                        "tooltip" => "Modale Nouveau mouvement - Choix liste \"Action\"",
                    ],
                    [
                        "fr" => "dégroupage",
                        "en" => "ungroup",
                        "tooltip" => "Modale Nouveau mouvement - Choix liste \"Action\"",
                    ],
                    [
                        "fr" => "passage à vide",
                        "en" => "empty passage",
                        "tooltip" => "Modale Nouveau mouvement - Choix liste \"Action\"",
                    ],
                    [
                        "fr" => "prise dans UL",
                        "en" => "pick up in L.U.",
                        "tooltip" => "Zone liste - type de mouvement",
                    ],
                    [
                        "fr" => "dépose dans UL",
                        "en" => "drop off in L.U.",
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
                    [
                        "fr" => "Modifier un mouvement",
                        "en" => "Edit a movement",
                        "tooltip" => "Modale modifier un mouvement"
                    ],
                    [
                        "fr" => "natures requises",
                        "en" => "required natures",
                    ],
                    [
                        "fr" => "Mouvements créés avec succès.",
                        "en" => "Movements created successfully",
                    ],
                    [
                        "fr" => "Mouvement créé avec succès.",
                        "en" => "Movement created successfully",
                    ],
                    [
                        "fr" => "Aucun mouvement créé.",
                        "en" => "No movement created",
                    ],
                    [
                        "fr" => "L'unité logistique ne doit pas correspondre à un article",
                        "en" => "The logistic unit must not match an item",
                    ],
                    [
                        "fr" => "L'unité logistique est dans une réception en attente et ne peut pas être mouvementée.",
                        "en" => "The logistic unit is in a pending reception and can not be moved.",
                    ]
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
                        "fr" => "Réception",
                        "en" => "Receipt",
                        "tooltip" => "Filtre\nZone liste - Nom de colonnes",
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
                        "en" => "The following L.U. does not exist :",
                        "tooltip" => "Modale Supprimer l'association BR",
                    ],
                    [
                        "fr" => "L'association BR a bien été créée",
                        "en" => "The Receipt and L.U. matching has been created",
                        "tooltip" => "Modale Enregistrer une réception",
                    ],
                    [
                        "fr" => "L'association BR a bien été supprimée",
                        "en" => "The Receipt and L.U. matching has been deleted",
                        "tooltip" => "Modale Supprimer l'association BR",
                    ],
                ],
            ],
            "Encours" => [
                "content" => [
                    [
                        "fr" => "Encours",
                        "en" => "Current",
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
                        "fr" => "Veuillez paramétrer le délai maximum de vos emplacements pour visualiser leurs encours.",
                        "en" => "Please set the maximum time for your locations to view their in progress.",
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
                    [
                        "fr" => "Natures",
                        "en" => "Natures",
                        "tooltip" => "Filtres",
                    ],
                ],
            ],
        ],
        "Qualité & Urgences" => [
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
                        "tooltip" => "Zone liste - Nom de colonnes\nModale modifier un litige"
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
                        "tooltip" => "Filtres\nZone liste - Nom de colonnes\nModale modifier un litige",
                    ],
                    [
                        "fr" => "Type",
                        "en" => "Type",
                        "tooltip" => "Filtres\nZone liste - Nom de colonnes\nModale modifier un litige",
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
                        "fr" => "articles",
                        "en" => "items",
                        "tooltip" => "Modale modifier un litige",
                    ],
                    [
                        "fr" => "Modifier un litige",
                        "en" => "Edit a dispute",
                        "tooltip" => "Modale modifier un litige",
                    ],
                    [
                        "fr" => "Code article",
                        "en" => "Item code",
                        "tooltip" => "Modale modifier un litige",
                    ],
                    [
                        "fr" => "Libellé",
                        "en" => "Label",
                        "tooltip" => "Modale modifier un litige",
                    ],
                    [
                        "fr" => "Référence article",
                        "en" => "Item reference",
                        "tooltip" => "Modale modifier un litige",
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
                    [
                        "fr" => "Le litige {1} a bien été supprimé",
                        "en" => "The dispute {1} has been deleted successfully",
                        "tooltip" => "Modale Supprimer le litige",
                    ],
                    [
                        "fr" => "Le litige {1} a bien été créée",
                        "en" => "The dispute {1} has been created successfully",
                        "tooltip" => "Modale Créer un litige",
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
                        "tooltip" => "Zone liste - Nom de colonnes\nModale Nouvelle urgence\nModale Modifier une urgence",
                    ],
                    [
                        "fr" => "Date de fin",
                        "en" => "End date",
                        "tooltip" => "Zone liste - Nom de colonnes\nModale Nouvelle urgence\nModale Modifier une urgence",
                    ],
                    [
                        "fr" => "N° poste",
                        "en" => "Item number",
                        "tooltip" => "Zone liste - Nom de colonnes\nModale Nouvelle urgence\nModale Modifier une urgence",
                    ],
                    [
                        "fr" => "Acheteur",
                        "en" => "Buyer",
                        "tooltip" => "Zone liste - Nom de colonnes\nModale Nouvelle urgence\nModale Modifier une urgence",
                    ],
                    [
                        "fr" => "Date arrivage UL",
                        "en" => "LU arrival date",
                        "tooltip" => "Zone liste - Nom de colonnes",
                    ],
                    [
                        "fr" => "Nouvelle urgence",
                        "en" => "New emergency",
                        "tooltip" => "Modale Nouvelle urgence",
                    ],
                    [
                        "fr" => "Modifier une urgence",
                        "en" => "Edit an emergency",
                        "tooltip" => "Modale Modifier une urgence",
                    ],
                    [
                        "fr" => "Supprimer l'urgence",
                        "en" => "Delete the emergency",
                        "tooltip" => "Modale Supprimer l'urgence",
                    ],
                    [
                        "fr" => "Cette urgence est liée à un arrivage UL.\nVous ne pouvez pas la supprimer",
                        "en" => "This emergency is linked to an LU arrival. You cannot delete it",
                        "tooltip" => "Modale Supprimer l'urgence",
                    ],
                    [
                        "fr" => "Voulez-vous réellement supprimer cette urgence ?",
                        "en" => "Do you really want to delete this emergency ?",
                        "tooltip" => "Modale Supprimer l'urgence",
                    ],
                    [
                        "fr" => "N° de commande",
                        "en" => "Order number",
                        "tooltip" => "Zone filtre",
                    ],
                    [
                        "fr" => "Transporteur",
                        "en" => "Carrier",
                        "tooltip" => "Zone liste - Nom de colonne\nModale modifier une urgence\nModale Créer une nouvelle urgence",
                    ],
                    [
                        "fr" => "Fournisseur",
                        "en" => "Supplier",
                        "tooltip" => "Zone liste - Nom de colonne\nModale modifier une urgence\nModale Créer une nouvelle urgence",
                    ],
                    [
                        "fr" => "N° tracking transporteur",
                        "en" => "Carrier tracking number",
                        "tooltip" => "Modale modifier une urgence\nModale Créer une nouvelle urgence",
                    ],
                    [
                        "fr" => "Numéro d'arrivage UL",
                        "en" => "LU arrival number",
                        "tooltip" => "Zone liste - Nom de colonnes",
                    ],
                    [
                        "fr" => "Code article interne",
                        "en" => "Internal article code",
                        "tooltip" => "Modale modifier une urgence\nModale Créer une nouvelle urgence",
                    ],
                    [
                        "fr" => "Code article fournisseur",
                        "en" => "Supplier article code",
                        "tooltip" => "Modale modifier une urgence\nModale Créer une nouvelle urgence",
                    ],
                ]
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
                        "tooltip" => "Acheminement :\nFiltre\nEmails\n_____\nService :\nFiltre",
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
                        "fr" => "Urgent",
                        "en" => "Urgent",
                        "tooltip" => "Service :\nZone liste - Nom de colonnes",
                    ],
                    [
                        "fr" => "Non urgent",
                        "en" => "Not urgent",
                        "tooltip" => "Modale Nouvelle demande de service - Urgence\nModifier une demande de service\nModale Nouvelle demande d'acheminement - Urgence\nModale Modifier un acheminement",

                    ],
                    [
                        "fr" => "Destinataire(s)",
                        "en" => "Addressee(s)",
                        "tooltip" => "Acheminement :\nFiltre \nModale Nouvelle demande\nModale Modifier un acheminement\nDétails acheminement - Entête\nPDF bon acheminement\nEmails\n\n_____\nService :\nFiltre\nModale Nouvelle demande de service\nModale Modifier une demande de service\n",
                    ],
                    [
                        "fr" => "Type",
                        "en" => "Type",
                        "tooltip" => "Acheminement :\nZone liste - Nom de colonnes\nModale Nouvelle demande\nModale Modifier un acheminement\nDétails acheminement - Entête\nEmails\n_____\nService :\nFiltre\nZone liste - Nom de colonnes\nModale Nouvelle demande de service\nModale Modifier une demande de service",
                    ],
                    [
                        "fr" => "Statut",
                        "en" => "Status",
                        "tooltip" => "Acheminement :\nZone liste - Nom de colonnes\nModale Nouvelle demande\nModale Modifier un acheminement\nDétails acheminement - Entête\nDétails acheminements - Liste des UL - Colonne\nEmails\n_____\nService :\nZone liste - Nom de colonnes\nModale Nouvelle demande de service\nModale Modifier une demande de service",
                    ],
                    [
                        "fr" => "Urgence",
                        "en" => "Emergency",
                        "tooltip" => "Acheminement :\nFiltre\nZone liste - Nom de colonnes\nModale Nouvelle demande\nModale Modifier un acheminement\nDétails acheminement - Entête\n_____\nService :\nFiltre\nModale Nouvelle demande de service\nModale Modifier une demande de service",
                    ],
                    [
                        "fr" => "Aller vers la création des statuts",
                        "en" => "Go to the creation of the statuses",
                        "tooltip" => "Acheminement :\nModale Nouvelle demande d'acheminement\n_____\nService :\nModale Nouvelle demande de service",
                    ],
                    [
                        "fr" => "Aucun statut brouillon pour ce type",
                        "en" => "No draft status for this type",
                        "tooltip" => "Acheminement :\nModale Nouvelle demande d'acheminement\n_____\nService :\nModale Nouvelle demande de service",
                    ],
                ],
            ],
            "Acheminements" => [
                "Général" => [
                    "content" => [
                        [
                            "fr" => "Acheminement",
                            "en" => "Transfer",
                            "tooltip" => "Menu\nFil d'ariane\nMenu \"+\"\nDétails",
                        ],
                        [
                            "fr" => "Acheminements",
                            "en" => "Transfers",
                            "tooltip" => "Menu nomade",
                        ],
                        [
                            "fr" => "Nouvelle demande d'acheminement",
                            "en" => "New transfer operation",
                            "tooltip" => "Modale Nouvelle demande d'acheminement",
                        ],
                        [
                            "fr" => "N° demande",
                            "en" => "Transfer number",
                            "tooltip" => "Filtre \nZone liste - Nom de colonnes\n\nNomade",
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
                            "fr" => "Quantité UL",
                            "en" => "LU quantity",
                        ],
                        [
                            "fr" => "Date de validation",
                            "en" => "Validation date",
                            "tooltip" => "Zone liste - Nom de colonnes\nDétails acheminements - Entête\nPDF bon acheminement\nPDF bon de surconsommation\nEmails",
                        ],
                        [
                            "fr" => "Date de traitement",
                            "en" => "Finish date",
                            "tooltip" => "Zone liste - Nom de colonnes\nDétails acheminements - Entête\nPDF bon acheminement\nEmails",
                        ],
                        [
                            "fr" => "Dates d'échéance",
                            "en" => "Due dates",
                            "tooltip" => "Détails\nModale Nouvelle demande\nEmails",
                        ],
                        [
                            "fr" => "Date d'échéance",
                            "en" => "Due date",
                            "tooltip" => "Zone liste - Nom de colonnes",
                        ],
                        [
                            "fr" => "Transporteur",
                            "en" => "Carrier",
                            "tooltip" => "Zone liste - Nom de colonnes\nModale Nouvelle demande\nModale Modifier un acheminement \nDétails acheminement - Entête\nEmails",
                        ],
                        [
                            "fr" => "Date statut partiel",
                            "en" => "Partial status date",
                            "tooltip" => "Zone liste - Nom de colonnes",
                        ],
                        [
                            "fr" => "N° tracking transporteur",
                            "en" => "Carrier tracking ID",
                            "tooltip" => "Zone liste - Nom de colonnes\nModale Nouvelle demande\nModale Modifier un acheminement \nDétails acheminement - Entête\nEmails",
                        ],
                        [
                            "fr" => "N° projet",
                            "en" => "Project ID",
                            "tooltip" => "Zone liste - Nom de colonnes\nModale Nouvelle demande\nModale Modifier un acheminement \nDétails acheminement - Entête\nEmails",
                        ],
                        [
                            "fr" => "Business unit",
                            "en" => "Business unit",
                            "tooltip" => "Zone liste - Nom de colonnes\nModale Nouvelle demande\nModale Modifier un acheminement \nDétails acheminement - Entête\nEmails",
                        ],
                        [
                            "fr" => "Nature",
                            "en" => "Nature",
                            "tooltip" => "Détails acheminements - Liste des UL - Nom de colonnes\nPDF bon acheminement\nPDF lettre de voiture\nPDF bon de surconsommation\nEmails",
                        ],
                        [
                            "fr" => "Code",
                            "en" => "Code",
                            "tooltip" => "Liste des UL\nEmails\nPDF bon de livraison",
                        ],
                        [
                            "fr" => "Quantité à acheminer",
                            "en" => "Quantity to transfer",
                            "tooltip" => "Détails acheminements - Liste des UL - Nom de colonnes\nPDF bon acheminement\nPDF lettre de voiture\nEmails",
                        ],
                        [
                            "fr" => "Date dernier mouvement",
                            "en" => "Last movement date",
                            "tooltip" => "Détails acheminements - Liste des UL - Nom de colonnes\nEmails",
                        ],
                        [
                            "fr" => "Dernier emplacement",
                            "en" => "Last location",
                            "tooltip" => "Détails acheminements - Liste des UL - Nom de colonnes\nEmails",
                        ],
                        [
                            "fr" => "Opérateur",
                            "en" => "Worker",
                            "tooltip" => "Détails acheminements - Liste des UL - Nom de colonnes\nEmails",
                        ],
                        [
                            "fr" => "Poids (kg)",
                            "en" => "Weight (kg)",
                            "tooltip" => "Détails acheminements - Liste des UL - Nom de colonnes\nPDF bon acheminement\nPDF lettre de voiture\nEmails",
                        ],
                        [
                            "fr" => "Volume (m3)",
                            "en" => "Volume (m3)",
                            "tooltip" => "Détails acheminements - Liste des UL - Nom de colonnes\nPDF bon acheminement\nPDF lettre de voiture\nEmails",
                        ],
                        [
                            "fr" => "Hauteur (m)",
                            "en" => "Height",
                            "tooltip" => "Détails acheminements - Liste des UL - Nom de colonnes\nPDF étiquette d'UL",
                        ],
                        [
                            "fr" => "Largeur (m)",
                            "en" => "Width",
                            "tooltip" => "Détails acheminements - Liste des UL - Nom de colonnes\nPDF étiquette d'UL",
                        ],
                        [
                            "fr" => "Longueur (m)",
                            "en" => "Length",
                            "tooltip" => "Détails acheminements - Liste des UL - Nom de colonnes\nPDF étiquette d'UL",
                        ],
                        [
                            "fr" => "Traité",
                            "en" => "Processed",
                            "tooltip" => "Détails acheminements - Liste des unités logistiques - Nom de colonnes\nEmails",
                        ],
                        [
                            "fr" => "À traiter",
                            "en" => "To process",
                            "tooltip" => "Détails acheminements - Liste des unités logistiques - Nom de colonnes\nEmails",
                        ],
                        [
                            "fr" => "L'acheminement a bien été créé",
                            "en" => "The transfer has been successfully created",
                        ],
                        [
                            "fr" => "Supprimer la demande d'acheminement",
                            "en" => "Delete the transfer operation",
                            "tooltip" => "Modale Supprimer la demande d'acheminement",
                        ],
                        [
                            "fr" => "Voulez-vous réellement supprimer cette demande d'acheminement ?",
                            "en" => "Do you really want to delete this transfer operation ?",
                            "tooltip" => "Modale Supprimer la demande d'acheminement",
                        ],
                        [
                            "fr" => "Une autre demande d'acheminement est en cours de création, veuillez réessayer",
                            "en" => "Another operation of transfer is being created, please try again",
                        ],
                        [
                            "fr" => "Une unité logistique minimum est nécessaire pour procéder à l'acheminement",
                            "en" => "A logistic unit is required to proceed with the transfer",
                        ],
                        [
                            "fr" => "Les unités logistiques de l'arrivage ont bien été ajoutés dans l'acheminement {1}",
                            "en" => "L.U.s of the arrival have been added successfully to the transfer {1}",
                        ],
                        [
                            "fr" => "Veuillez renseigner un statut valide.",
                            "en" => "Please fill in a valid status",
                        ],
                        [
                            "fr" => "Il n'y a aucun emplacement de prise ou de dépose paramétré pour ce type.Veuillez en paramétrer ou rendre les champs visibles à la création et/ou modification.",
                            "en" => "There is no pickup or drop location set for this type. Please set or make the fields visible during the creation and/or modification",
                        ],
                        [
                            "fr" => "La date de fin d'échéance est inférieure à la date de début.",
                            "en" => "End date is inferior to the start date",
                        ],
                        [
                            "fr" => "demande d'acheminement",
                            "en" => "transfer operation",
                            "tooltip" => "Modale demande d'acheminement",
                        ],
                        [
                            "fr" => "L'acheminement a bien été modifié",
                            "en" => "The transfer has been successfully modified",
                        ],
                        [
                            "fr" => "L'acheminement a bien été supprimé",
                            "en" => "The transfer has been successfully deleted",
                        ],
                        [
                            "fr" => "L'acheminement a bien été passé en à traiter",
                            "en" => "Transfer has been changed successfully in to process",
                        ],
                        [
                            "fr" => "L'acheminement a bien été traité",
                            "en" => "Transfer has been processed successfully",
                        ],
                        [
                            "fr" => "Modifier un acheminement",
                            "en" => "Edit transfer",
                            "tooltip" => "Modale modifier un acheminement",
                        ],
                        [
                            "fr" => "Vous n'avez pas configuré de statut {1} pour ce type d'acheminement",
                            "en" => "You have not configured a status {1} for this transfer type",
                            "tooltip" => "Modale Nouvelle demande d'acheminement",
                        ],
                    ],
                ],
                "Champs fixes" => [
                    "content" => [
                        [
                            "fr" => "N° commande",
                            "en" => "Order number",
                            "tooltip" => "Filtre \nZone liste - Nom de colonnes\nModale Nouvelle demande \nModale Modifier un acheminement \nDétails acheminement - Entête \n",
                        ],
                        [
                            "fr" => "Destination",
                            "en" => "Destination",
                            "tooltip" => "Filtre \nZone liste - Nom de colonnes\nModale Nouvelle demande\nModale Modifier un acheminement \nDétails acheminement - Entête \nPDF bon acheminement",
                        ],
                        [
                            "fr" => "Emplacement de prise",
                            "en" => "Picking location",
                            "tooltip" => "Zone liste - Nom de colonnes\nModale Nouvelle demande\nModale Modifier un acheminement \nDétails acheminement - Entête \nNomade\nPDF bon acheminement",
                        ],
                        [
                            "fr" => "Emplacement de dépose",
                            "en" => "Drop location",
                            "tooltip" => "Zone liste - Nom de colonnes\nModale Nouvelle demande\nModale Modifier un acheminement \nDétails acheminement - Entête \nNomade\nPDF bon acheminement",
                        ],
                        [
                            "fr" => "Client",
                            "en" => "Customer",
                            "tooltip" => "Zone liste - Nom de colonnes\nModale Nouvelle demande\nModale Modifier un acheminement \nDétails acheminement - Entête",
                        ],
                        [
                            "fr" => "Téléphone client",
                            "en" => "Customer phone",
                            "tooltip" => "Zone liste - Nom de colonnes\nModale Nouvelle demande\nModale Modifier un acheminement \nDétails acheminement - Entête \n",
                        ],
                        [
                            "fr" => "À l'attention de",
                            "en" => "Customer recipient",
                            "tooltip" => "Zone liste - Nom de colonnes\nModale Nouvelle demande\nModale Modifier un acheminement \nDétails acheminement - Entête",
                        ],
                        [
                            "fr" => "Adresse de livraison",
                            "en" => "Customer address",
                            "tooltip" => "Zone liste - Nom de colonnes\nModale Nouvelle demande\nModale Modifier un acheminement \nDétails acheminement - Entête",
                        ],
                    ],
                ],
                "Zone liste - Noms de colonnes" => [
                    "content" => [
                        [
                            "fr" => "Nombre d'UL",
                            "en" => "L.U. quantity",
                            "tooltip" => "Zone liste - Nom de colonnes",
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
                            "fr" => "Générer une étiquette",
                            "en" => "Generate label",
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
                        [
                            "fr" => "Cet acheminement est urgent",
                            "en" => "This transfer is urgent",
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
                            "fr" => "Unité logistique",
                            "en" => "Logistic unit",
                            "tooltip" => "Détails acheminements - Liste des unités logistiques - Nom de colonnes\nPDF bon acheminement\nPDF lettre de voiture",
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
                        [
                            "fr" => "L'unité logistique {1} a bien été modifiée",
                            "en" => "Logistic unit {1} has been modified successfully",
                        ],
                        [
                            "fr" => "L'unité logistique {1} a bien été ajoutée",
                            "en" => "Logistic unit {1} has been added",
                        ],
                    ],
                ],
                "Modale" => [
                    "content" => [
                        [
                            "fr" => "Echéance",
                            "en" => "Due",
                            "tooltip" => "Modale Nouvelle demande d'acheminement\nModale Modifier un acheminement ",
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
                            "en" => "Addressee",
                            "tooltip" => "Modale Création/Modification Lettre de voiture",
                        ],
                        [
                            "fr" => "Contact expéditeur",
                            "en" => "Consigner contact",
                            "tooltip" => "Modale Création/Modification Lettre de voiture",
                        ],
                        [
                            "fr" => "Contact destinataire",
                            "en" => "Addressee contact",
                            "tooltip" => "Modale Création/Modification Lettre de voiture",
                        ],
                        [
                            "fr" => "Nom",
                            "en" => "Name",
                            "tooltip" => "Modale Création/Modification Lettre de voiture",
                        ],
                        [
                            "fr" => "Téléphone - Email",
                            "en" => "Phone number - Email",
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
                            "fr" => "Note de bas de page",
                            "en" => "Footnote",
                            "tooltip" => "Modale Création/Modification Lettre de voiture",
                        ],
                        [
                            "fr" => "Information : Le contenu des UL doit être modifié sur les UL",
                            "en" => "Information: The L.U.s' content must be changed on the L.U.s",
                            "tooltip" => "Modale Création/Modification Lettre de voiture",
                        ],
                        [
                            "fr" => "Des unités logistiques sont nécessaires pour générer une lettre de voiture",
                            "en" => "Logistic units are necessary to create a consignment note",
                        ],
                        [
                            "fr" => "L'acheminement contient plus de {1} UL",
                            "en" => "The transfer contains more than {1} LU",
                        ],
                        [
                            "fr" => "La lettre de voiture n'existe pas pour cet acheminement",
                            "en" => "The consignment note does not exist for this transfer",
                        ],
                        [
                            "fr" => "Lettre de voiture N°{1}",
                            "en" => "Road consignment note N°{1}",
                            "tooltip" => "PDF lettre de voiture"
                        ],
                        [
                            "fr" => "Marchandise",
                            "en" => "Merchandise",
                            "tooltip" => "PDF lettre de voiture"
                        ],
                        [
                            "fr" => "Autres informations",
                            "en" => "Other information",
                            "tooltip" => "PDF lettre de voiture"
                        ],
                        [
                            "fr" => "Total",
                            "en" => "Total",
                            "tooltip" => "PDF lettre de voiture"
                        ]
                    ],
                ],
                "Bon d'acheminement" => [
                    "content" => [
                        [
                            "fr" => "Bon d'acheminement",
                            "en" => "Transfer note",
                            "tooltip" => "PDF bon acheminement",
                        ],
                        [
                            "fr" => "Le bon d'acheminement n'existe pas pour cet acheminement",
                            "en" => "The transfer note does not exist for this transfer",
                        ],
                        [
                            "fr" => "Destinataires",
                            "en" => "Addressees",
                            "tooltip" => "PDF bon de livraison"
                        ],
                    ],
                ],
                "Bon de livraison" => [
                    "content" => [
                        [
                            "fr" => "Création modification BL",
                            "en" => "Create / Edit Order",
                            "tooltip" => "Modale Création modification BL",
                        ],
                        [
                            "fr" => "Expéditeur",
                            "en" => "Consigner",
                            "tooltip" => "Modale Création modification BL\nPDF bon de livraison",
                        ],
                        [
                            "fr" => "Adresse de livraison",
                            "en" => "Delivery address",
                            "tooltip" => "Modale Création modification BL\nPDF bon de livraison",
                        ],
                        [
                            "fr" => "Numéro de livraison",
                            "en" => "Delivery number",
                            "tooltip" => "Modale Création modification BL\nPDF bon de livraison",
                        ],
                        [
                            "fr" => "Date de livraison",
                            "en" => "Delivery date",
                            "tooltip" => "Modale Création modification BL\nPDF bon de livraison",
                        ],
                        [
                            "fr" => "Numéro de commande de vente",
                            "en" => "Sales order number",
                            "tooltip" => "Modale Création modification BL\nPDF bon de livraison",
                        ],
                        [
                            "fr" => "Lettre de voiture",
                            "en" => "Road consignment note",
                            "tooltip" => "Modale Création modification BL\nPDF bon de livraison",
                        ],
                        [
                            "fr" => "Bon de commande client",
                            "en" => "Customer PO Number",
                            "tooltip" => "Modale Création modification BL\nPDF bon de livraison",
                        ],
                        [
                            "fr" => "Date commande client",
                            "en" => "Customer PO Date",
                            "tooltip" => "Modale Création modification BL\nPDF bon de livraison",
                        ],
                        [
                            "fr" => "Réponse numéro commande",
                            "en" => "Response order number",
                            "tooltip" => "Modale Création modification BL\nPDF bon de livraison",
                        ],
                        [
                            "fr" => "Numéro de projet",
                            "en" => "Project number",
                            "tooltip" => "Modale Création modification BL\nPDF bon de livraison",
                        ],
                        [
                            "fr" => "Urgence",
                            "en" => "Urgent",
                            "tooltip" => "Modale Création modification BL",
                        ],
                        [
                            "fr" => "Contact",
                            "en" => "Handled by",
                            "tooltip" => "Modale Création modification BL\nPDF bon de livraison",
                        ],
                        [
                            "fr" => "Téléphone",
                            "en" => "Phone number",
                            "tooltip" => "Modale Création modification BL\nPDF bon de livraison",
                        ],
                        [
                            "fr" => "Fax",
                            "en" => "Fax",
                            "tooltip" => "Modale Création modification BL\nPDF bon de livraison",
                        ],
                        [
                            "fr" => "Client acheteur",
                            "en" => "Buying customer",
                            "tooltip" => "Modale Création modification BL\nPDF bon de livraison",
                        ],
                        [
                            "fr" => "Numéro facture",
                            "en" => "Invoice number",
                            "tooltip" => "Modale Création modification BL\nPDF bon de livraison",
                        ],
                        [
                            "fr" => "Numéro vente",
                            "en" => "Sold number",
                            "tooltip" => "Modale Création modification BL",
                        ],
                        [
                            "fr" => "Facturé à",
                            "en" => "Invoiced to",
                            "tooltip" => "Modale Création modification BL\nPDF bon de livraison",
                        ],
                        [
                            "fr" => "Vendu à",
                            "en" => "Sold to",
                            "tooltip" => "Modale Création modification BL\nPDF bon de livraison",
                        ],
                        [
                            "fr" => "Nom dernier utilisateur",
                            "en" => "Last user's name",
                            "tooltip" => "Modale Création modification BL",
                        ],
                        [
                            "fr" => "Dernier utilisateur",
                            "en" => "Last user",
                            "tooltip" => "Modale Création modification BL\nPDF bon de livraison",
                        ],
                        [
                            "fr" => "Livrer à",
                            "en" => "Deliver to",
                            "tooltip" => "Modale Création modification BL\nPDF bon de livraison",
                        ],
                        [
                            "fr" => "Numéro",
                            "en" => "Number",
                            "tooltip" => "Modale Création modification BL",
                        ],
                        [
                            "fr" => "Ligne du BL",
                            "en" => "Order line",
                            "tooltip" => "Modale Création modification BL",
                        ],
                        [
                            "fr" => "Date",
                            "en" => "Date",
                            "tooltip" => "Modale Création modification BL\nPDF bon de livraison",
                        ],
                        [
                            "fr" => "Note(s)",
                            "en" => "Note(s)",
                            "tooltip" => "Modale Création modification BL\nPDF bon de livraison",
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
                        [
                            "fr" => "Des unités logistiques sont nécessaires pour générer un bon de livraison",
                            "en" => "Logistic units are necessary to generate a delivery note",
                        ],
                        [
                            "fr" => "Le bon de livraison n'existe pas pour cet acheminement",
                            "en" => "The delivery note does not exist for this transfer",
                        ],
                        [
                            "fr" => "Description",
                            "en" => "Description",
                            "tooltip" => "PDF bon de livraison"
                        ],
                        [
                            "fr" => "Quantité",
                            "en" => "Quantity",
                            "tooltip" => "PDF bon de livraison"
                        ],
                        [
                            "fr" => "Bon de livraison - Original",
                            "en" => "Delivery note - Original",
                            "tooltip" => "PDF bon de livraison"
                        ],
                        [
                            "fr" => "Destinataire",
                            "en" => "Addressee",
                            "tooltip" => "PDF bon de livraison"
                        ],
                        [
                            "fr" => "Cachet et signature de l'entreprise",
                            "en" => "Company stamp & signature",
                            "tooltip" => "PDF bon de livraison"
                        ],
                        [
                            "fr" => "Signataire autorisé",
                            "en" => "Authorized signatory",
                            "tooltip" => "PDF bon de livraison"
                        ],
                        [
                            "fr" => "Page",
                            "en" => "Page",
                            "tooltip" => "PDF bon de livraison"
                        ],
                        [
                            "fr" => "Article contenu",
                            "en" => "Contained article",
                            "tooltip" => "PDF bon de livraison",
                        ],
                        [
                            "fr" => "Référence",
                            "en" => "Reference",
                            "tooltip" => "PDF bon de livraison",
                        ],
                        [
                            "fr" => "Désignation",
                            "en" => "Designation",
                            "tooltip" => "PDF bon de livraison",
                        ],
                    ],
                ],
                "Bon de transport" => [
                    "content" => [
                        [
                            "fr" => "Générer un bon de transport",
                            "en" => "Shipment note",
                            "tooltip" => "Bouton générer un bon de transport",
                        ],
                    ],
                ],
                "Bon de surconsommation" => [
                    "content" => [
                        [
                            "fr" => "Bon de surconsommation",
                            "en" => "Overconsumption note",
                            "tooltip" => "PDF bon de surconsommation",
                        ],
                        [
                            "fr" => "Caractéristiques de la demande",
                            "en" => "Operation characteristics",
                            "tooltip" => "PDF bon de surconsommation",
                        ],
                        [
                            "fr" => "N° de demande",
                            "en" => "Operation N°",
                            "tooltip" => "PDF bon de surconsommation",
                        ],
                        [
                            "fr" => "Ligne de dépose",
                            "en" => "Drop line",
                            "tooltip" => "PDF bon de surconsommation",
                        ],
                        [
                            "fr" => "Faite par",
                            "en" => "Done by",
                            "tooltip" => "PDF bon de surconsommation",
                        ],
                        [
                            "fr" => "PN",
                            "en" => "PN",
                            "tooltip" => "PDF bon de surconsommation",
                        ],
                        [
                            "fr" => "Code barre",
                            "en" => "Barcode",
                            "tooltip" => "PDF bon de surconsommation",
                        ],
                        [
                            "fr" => "Qté livrée",
                            "en" => "Delivered qty",
                            "tooltip" => "PDF bon de surconsommation",
                        ],
                        [
                            "fr" => "Qte demandée",
                            "en" => "Requested qty",
                            "tooltip" => "PDF bon de surconsommation",
                        ],
                        [
                            "fr" => "Emplacement",
                            "en" => "Location",
                            "tooltip" => "PDF bon de surconsommation",
                        ],
                        [
                            "fr" => "N° lot / Cure date / N°EASA si besoin / Commentaire",
                            "en" => "Batch number / Cure date / EASA number if needed / Comment",
                            "tooltip" => "PDF bon de surconsommation",
                        ],
                        [
                            "fr" => "Document à ne pas dissocier de l'OF",
                            "en" => "Document not to be dissociated from the OF",
                            "tooltip" => "PDF bon de surconsommation"
                        ],
                        [
                            "fr" => "Rappel",
                            "en" => "Reminder",
                            "tooltip" => "PDF bon de surconsommation"
                        ],
                        [
                            "fr" => "1 à 5 lignes : service sous 15 minutes après demande aux équipes GT<br/>6 lignes et + : demande sous OF, service dans l'heure (fonction du nb de ligne)",
                            "en" => "1 to 5 lines: service within 15 minutes after request to the GT teams<br/>6 lines and more: request under OF, service within the hour (depending on the number of lines)",
                            "tooltip" => "PDF bon de surconsommation"
                        ],
                        [
                            "fr" => "Visa et nom opérateur magasin",
                            "en" => "Visa and store operator name",
                            "tooltip" => "PDF bon de surconsommation"
                        ],
                        [
                            "fr" => "Date",
                            "en" => "Date",
                            "tooltip" => "PDF bon de surconsommation"
                        ],
                        [
                            "fr" => "Demande de surconsommation OF / complément",
                            "en" => "Added parts request OF",
                            "tooltip" => "PDF bon de surconsommation"
                        ],
                    ],
                ],
                "Emails" => [
                    "content" => [
                        [
                            "fr" => "Changement de statut d'un(e) demande d'acheminement.",
                            "en" => "The status of the transfer operation changed",
                            "tooltip" => "Email changement statut acheminement",
                        ],
                        [
                            "fr" => "Votre acheminement/expédition est traité(e) avec les informations suivantes :",
                            "en" => "Your transfer operation is finished with the following information:",
                            "tooltip" => "Email traitement acheminement",
                        ],
                        [
                            "fr" => "Votre acheminement/expédition est en cours de traitement avec les informations suivantes :",
                            "en" => "Your transfer is being processed with the following information:",
                            "tooltip" => "Email changement statut acheminement",
                        ],
                        [
                            "fr" => "Notification de traitement d'une demande d'acheminement",
                            "en" => "Notification upon transfer operation finishing",
                            "tooltip" => "Email traitement acheminement",
                        ],
                        [
                            "fr" => "Création d'une demande d'acheminement",
                            "en" => "Transfer operation has been updated",
                            "tooltip" => "Email traitement acheminement",
                        ],
                        [
                            "fr" => "Changement de statut d'une demande d'acheminement",
                            "en" => "Status change for a transfer operation",
                            "tooltip" => "Email changement statut acheminement",
                        ],
                        [
                            "fr" => "Urgence : Notification de traitement d'une demande d'acheminement",
                            "en" => "Emergency : Notification upon transfer operation finishing",
                            "tooltip" => "Email traitement acheminement",
                        ],
                        [
                            "fr" => "Acheminement {1} traité le {2} à {3}",
                            "en" => "Transfer {1} finished on {2} at {3}",
                            "tooltip" => "Email traitement acheminement",
                        ],
                        [
                            "fr" => "Une demande d'acheminement de type {1} vous concerne :",
                            "en" => "A transfer operation of type {1} concerns you:",
                            "tooltip" => "Email de création d'acheminement",
                        ],
                        [
                            "fr" => "Changement de statut d'une demande d'acheminement de type {1} vous concernant :",
                            "en" => "Status change for a transfer operation of type {1} concerning you:",
                            "tooltip" => "Email changement statut acheminement",
                        ],
                        [
                            "fr" => "Bon d'enlèvement généré pour l'acheminement {1} au statut {2} le {3}",
                            "en" => "Report generated for dispatch {1} ({2}) on {3}",
                            "tooltip" => "Email de signature groupée",
                        ],
                        [
                            "fr" => "Acheminement {1} traité partiellement le {2}",
                            "en" => "transfer {1} partially processed on {2}",
                            "tooltip" => "Email traitement acheminement",
                        ],
                        [
                            "fr" => "Acheminement {1} traité le {2}",
                            "en" => "transfer {1} processed on {2}",
                            "tooltip" => "Email traitement acheminement",
                        ],
                    ],
                ],
            ],
            "Services" => [
                null => [
                    "content" => [
                        [
                            "fr" => "Service",
                            "en" => "Service",
                            "tooltip" => "Menu \"+\"\nMenu\nFil d'ariane",
                        ],
                        [
                            "fr" => "Une autre demande de service est en cours de création, veuillez réessayer.",
                            "en" => "Another service operation is being created, please try again.",
                            "tooltip" => "Création demande de service",
                        ],
                        [
                            "fr" => "La demande de service {1} a bien été créée.",
                            "en" => "Service request {1} has been successfully created.",
                            "tooltip" => "Création demande de service"
                        ],
                        [
                            "fr" => "La demande de service {1} a bien été modifiée.",
                            "en" => "Service request {1} has been successfully modified.",
                            "tooltip" => "Modification demande de service",
                        ],
                        [
                            "fr" => "La demande de service {1} a bien été supprimée.",
                            "en" => "Service request {1} has been successfully deleted.",
                            "tooltip" => "Suppression demande de service",
                        ],
                        [
                            "fr" => "Supprimer la demande de service",
                            "en" => "Delete service request",
                            "tooltip" => "Suppression demande de service",
                        ],
                        [
                            "fr" => "Voulez-vous réellement supprimer cette demande de service",
                            "en" => "Do you really want to delete this service request",
                            "tooltip" => "Suppression demande de service",
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
                            "fr" => "Type",
                            "en" => "Type",
                            "tooltip" => "Paramètrage des champs fixes, sélection de destinataires par type de service",
                        ],
                    ],
                ],
                "Zone liste - Nom de colonnes" => [
                    "content" => [
                        [
                            "fr" => "Numéro de demande",
                            "en" => "Operation number",
                            "tooltip" => "Zone liste - Nom de colonne",
                        ],[
                            "fr" => "Destinataires",
                            "en" => "Addressees",
                            "tooltip" => "Zone liste - Nom de colonne\nModale Modifier une demande de service",
                        ],[
                            "fr" => "Objet",
                            "en" => "Object",
                            "tooltip" => "Filtre\nZone liste - Nom de colonne\nModale Nouvelle demande de service\nModale Modifier une demande de service\nPage détails",
                        ],[
                            "fr" => "Date de réalisation",
                            "en" => "Completion date",
                            "tooltip" => "Zone liste - Nom de colonne\nModale Nouvelle demande de service\nModale Modifier une demande de service\nPage détails",
                        ],[
                            "fr" => "Date demande",
                            "en" => "Operation date",
                            "tooltip" => "Zone liste - Nom de colonne",
                        ],
                    ],
                ],
                "Modale et détails" => [
                    "content" => [
                        [
                            "fr" => "Chargement",
                            "en" => "Loading ",
                            "tooltip" => "Modale Nouvelle demande de service Modale Modifier une demande de service\nPage détails",
                        ],
                        [
                            "fr" => "Déchargement",
                            "en" => "Unloading",
                            "tooltip" => "Modale Nouvelle demande de service Modale Modifier une demande de service\nPage détails",
                        ],
                        [
                            "fr" => "Nombre d'opération(s) réalisée(s)",
                            "en" => "Amount of operations completed",
                            "tooltip" => "Modale Nouvelle demande de service Modale Modifier une demande de service\nPage détails",
                        ],
                        [
                            "fr" => "Modifier une demande de service",
                            "en" => "Edit a service operation",
                            "tooltip" => "Modale Modifier une demande de service",
                        ],
                        [
                            "fr" => "Dates",
                            "en" => "Dates",
                            "tooltip" => "Page détails"],
                        [
                            "fr" => "Date attendue",
                            "en" => "Due date",
                            "tooltip" => "Zone liste - Nom de colonnes\nModale Nouvelle demande de service Modale Modifier une demande de service\nPage détails",
                        ],
                        [
                            "fr" => "Statut",
                            "en" => "Status",
                            "tooltip" => "Page détails\nModales modification de statut",
                        ],
                        [
                            "fr" => "Choisir un statut...",
                            "en" => "Choose a status...",
                            "tooltip" => "Page détails\nModales modification de statut",
                        ],
                        [
                            "fr" => "Changer de statut",
                            "en" => "Edit status",
                            "tooltip" => "Page détails",
                        ],
                        [
                            "fr" => "Type à choisir",
                            "en" => "Choose a type",
                            "tooltip" => "Modale Nouvelle demande de service",
                        ],
                        [
                            "fr" => "Ce service n'a aucune pièce jointe",
                            "en" => "This service has no attached documents",
                            "tooltip" => "Détails",
                        ],
                        [
                            "fr" => "Ce service n'a aucun champ libre",
                            "en" => "This service has no custom fields",
                            "tooltip" => "Détails",
                        ],
                        [
                            "fr" => "Génération de l'historique en cours",
                            "en" => "Generation of the history",
                            "tooltip" => "Détails",
                        ],
                        [
                            "fr" => "Timeline à venir",
                            "en" => "Timeline coming soon",
                            "tooltip" => "Détails",
                        ],

                    ],
                ],
                "Emails" => [
                    "content" => [
                        [
                            "fr" => "Votre demande de service a été créée",
                            "en" => "Your service operation has been created",
                            "tooltip" => "Email de création",
                        ],
                        [
                            "fr" => "Création d'une demande de service",
                            "en" => "Creation of a service operation",
                            "tooltip" => "Email de création",
                        ],
                        [
                            "fr" => "Changement de statut d'une demande de service",
                            "en" => "Changing the status of a service operation",
                            "tooltip" => "Email changement de statut",
                        ],
                        [
                            "fr" => "Une demande de service vous concernant a changé de statut",
                            "en" => "A service operation concerning you changed her status",
                            "tooltip" => "Email changement de statut",
                        ],
                        [
                            "fr" => "Demande de service effectuée",
                            "en" => "Service operation completed",
                            "tooltip" => "Email traitement demande de service",
                        ],
                        [
                            "fr" => "Votre demande de service a bien été effectuée",
                            "en" => "Service operation has been completed successfully",
                            "tooltip" => "Email traitement demande de service",
                        ],
                        [
                            "fr" => "Chargement",
                            "en" => "Source",
                            "tooltip" => "Email traitement demande de service",
                        ],
                        [
                            "fr" => "Déchargement",
                            "en" => "Destination",
                            "tooltip" => "Email traitement demande de service",
                        ],
                        [
                            "fr" => "Modifié par",
                            "en" => "Modified by",
                            "tooltip" => "Email traitement demande de service",
                        ],
                    ],
                ],
            ],
            "Livraison" => [
                "content" => [
                    ["fr" => "Livraison"],
                    ["fr" => "Demande de livraison"]
                ]
            ],
            "Expédition" => [
                "content" => [
                    ["fr" => "Expédition"],
                    ["fr" => "Demande d'expédition"]
                ]
            ],
        ],
        "Ordre" => [
            "Réceptions" => [
                "content" => [
                    ["fr" => "réceptions"],
                    ["fr" => "Réception"],
                    ["fr" => "Réceptions"],
                    ["fr" => "réception"],
                    ["fr" => "de réception"],
                    ["fr" => "n° de réception"],
                    ["fr" => "cette réception"],
                    ["fr" => "nouvelle réception"],
                    ["fr" => "la"],
                    ["fr" => "une réception"],
                    ["fr" => "la réception"],
                    ["fr" => "article"],
                    ["fr" => "articles"],
                    ["fr" => "l'article"],
                    ["fr" => "d'article"],
                    ["fr" => "d'articles"],
                    ["fr" => "Cette réception est urgente"],
                    ["fr" => "Une ou plusieurs références liées à cette réception sont urgentes"],
                    ["fr" => "Cette réception ainsi qu'une ou plusieurs références liées sont urgentes"],
                    ["fr" => "Une autre réception est en cours de création, veuillez réessayer."],
                    ["fr" => "Un autre litige de réception est en cours de création, veuillez réessayer."],
                    ["fr" => "Êtes-vous sûr de vouloir la finir"],
                    ["fr" => "Cette réception contient des articles à finir de réceptionner."],
                    ["fr" => "Voulez-vous réellement supprimer cette réception"],
                    ["fr" => "Cette réception contient des articles."],
                    ["fr" => "Vous devez d'abord les supprimer."],
                    ["fr" => "Supprimer la réception"],
                    ["fr" => "Annuler la réception"],
                ],
            ],
            "Livraison" => [
                "content" => [
                    ["fr" => "Livraison"],
                    ["fr" => "Ordre de livraison"]
                ]
            ]
        ],
        "Référentiel" => [
            "Projet" => [
                "content" => [
                    ["fr" => "Projet"]
                ]
            ]
        ],
        "Stock" => [
            null => [],
            "Références" => [
                "Général" => [
                    "content" => [
                        [
                            "fr" => "Référence",
                            "en" => "Reference"
                        ],
                        [
                            "fr" => "référence",
                            "en" => "reference"
                        ],
                        [
                            "fr" => "références",
                            "en" => "references"
                        ],
                        [
                            "fr" => "Références",
                            "en" => "References"
                        ],
                        [
                            "fr" => "Type",
                            "en" => "Type"
                        ],
                        [
                            "fr" => "Libellé",
                            "en" => "Label"
                        ],
                        [
                            "fr" => "Quantité",
                            "en" => "Quantity"
                        ],
                        [
                            "fr" => "Code barre",
                            "en" => "Barcode"
                        ],
                        [
                            "fr" => "En tant que gestionnaires des articles présents dans cette liste, vous devez choisir une action parmi les options à gauche de chaque article puis cliquer sur le bouton valider en bas de la page pour gérer votre stock.",
                            "en" => "As managers of the articles in this list, you must choose an action from the options to the left of each article, then click on the validate button at the bottom of the page to manage your stock."
                        ],
                    ],
                ],
                "Email stock dormant" => [
                    "content" => [
                        [
                            "fr" => "Date max de stockage",
                            "en" => "Max storage date"
                        ],
                        [
                            "fr" => "Cliquez ici pour gérer vos articles",
                            "en" => "Click here to manage articles",
                        ],
                        [
                            "fr" => "Les articles suivants approchent du seuil autorisé de stockage.",
                            "en" => "The following items are approaching the authorized stocking threshold."
                        ],
                        [
                            "fr" => "Sans réponse de votre part, nous détruirons les articles concernés dans un délai de 15 jours à partir de ce mail.",
                            "en" => "If you do not respond, we will destroy the items concerned within 15 days of this email."
                        ],
                        [
                            "fr" => "Seuil d’alerte stock dormant atteint",
                            "en" => "Sleeping stock alert threshold reached"
                        ],
                        [
                            "fr" => "Cette liste n’est pas exhaustive, {1} références sont également en alerte stock dormant.",
                            "en" => "This list is not exhaustive, {1} references are also on dormant stock alert."
                        ],
                    ],
                ],
            ],
        ],
        "IoT" => [
            null => [
                "content" => [
                    "IoT" => ["fr" => "IoT"],
                    "Fonction/Zone" => ["fr" => "Fonction/Zone"],
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

    public function load(ObjectManager $manager)
    {
        $this->console = new ConsoleOutput();
        $this->manager = $manager;

        $languageRepository = $manager->getRepository(Language::class);

        if (!$languageRepository->findOneBy(["slug" => Language::FRENCH_DEFAULT_SLUG])) {
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

        if (!$languageRepository->findOneBy(["slug" => Language::FRENCH_SLUG])) {
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

        if (!$languageRepository->findOneBy(["slug" => Language::ENGLISH_DEFAULT_SLUG])) {
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

        if (!$languageRepository->findOneBy(["slug" => Language::ENGLISH_SLUG])) {
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

        if (isset($frenchDefault) || isset($french) || isset($englishDefault) || isset($english)) {
            $manager->flush();
        }

        foreach (self::TRANSLATIONS as $categoryLabel => $menus) {
            $this->handleCategory("category", null, $categoryLabel, $menus);
        }

        $this->manager->flush();

        $this->deleteUnusedCategories(null, self::TRANSLATIONS);
        $this->manager->flush();

        $this->updateUsers();
        $this->manager->flush();
    }

    private function handleCategory(string $type, ?TranslationCategory $parent, string $label, array $content)
    {
        $categoryRepository = $this->manager->getRepository(TranslationCategory::class);
        $translationSourceRepository = $this->manager->getRepository(TranslationSource::class);

        $category = $categoryRepository->findOneBy(["parent" => $parent, "label" => $label]);
        if (!$category) {
            $category = (new TranslationCategory())
                ->setParent($parent)
                ->setType($type)
                ->setLabel($label);

            $this->manager->persist($category);

            $parentLabel = $parent?->getLabel() ?: $parent?->getParent()?->getLabel();
            $this->console->writeln(($label ? "Created $type \"$label\"" : "Created single $type") . ($parentLabel ? " in \"$parentLabel\"" : ""));
        }

        if (!isset($content["content"])) {
            foreach ($content as $childLabel => $childContent) {
                $this->handleCategory(self::CHILD[$type], $category, $childLabel, $childContent);
            }

            $this->deleteUnusedCategories($category, $content);
        } else {
            $category->setSubtitle($content["subtitle"] ?? null);

            foreach ($content["content"] as $translation) {
                $transSource = $category->getId() ? $translationSourceRepository->findOneByDefaultFrenchTranslation($category, $translation["fr"]) : null;
                if (!$transSource) {
                    $transSource = new TranslationSource();
                    $transSource->setCategory($category);

                    $this->manager->persist($transSource);
                }

                $transSource->setTooltip($translation["tooltip"] ?? null);

                $french = $transSource->getTranslationIn("french-default");
                if (!$french) {
                    $french = (new Translation())
                        ->setLanguage($this->getLanguage("french-default"))
                        ->setSource($transSource)
                        ->setTranslation($translation["fr"]);

                    $this->manager->persist($french);

                    $this->console->writeln("Created default french source translation \"" . str_replace("\n", "\\n ", $translation["fr"]) . "\"");
                } else if($french->getTranslation() != $translation["fr"]) {
                    $french->setTranslation($translation["fr"]);

                    $this->console->writeln("Updated default french source translation \"" . str_replace("\n", "\\n ", $translation["fr"]) . "\"");
                }

                if(isset($translation["en"])) {
                    $english = $transSource->getTranslationIn("english-default");
                    if (!$english) {
                        $english = (new Translation())
                            ->setLanguage($this->getLanguage("english-default"))
                            ->setSource($transSource)
                            ->setTranslation($translation["en"]);

                        $this->manager->persist($english);

                        $this->console->writeln("Created default english source translation \"" . str_replace("\n", "\\n ", $translation["en"]) . "\"");
                    } else if ($english->getTranslation() != $translation["en"]) {
                        $english->setTranslation($translation["en"]);

                        $this->console->writeln("Updated default english source translation \"" . str_replace("\n", "\\n ", $translation["en"]) . "\"");
                    }

                } else {
                    $english = $transSource->getTranslationIn("english-default");
                    if (!$english) {
                        $english = (new Translation())
                            ->setLanguage($this->getLanguage("english-default"))
                            ->setSource($transSource)
                            ->setTranslation($translation["fr"]);

                        $this->manager->persist($english);

                        $this->console->writeln("Created default english source translation \"" . str_replace("\n", "\\n ", $translation["fr"]) . "\"");
                    }
                }
            }

            $this->deleteUnusedTranslations($category, $content["content"]);
        }
    }

    private function deleteUnusedCategories(TranslationCategory|null $parent, array $categories)
    {
        $categoryRepository = $this->manager->getRepository(TranslationCategory::class);

         if (!$parent || $parent->getId()) {
            $fixtureCategories = array_keys($categories);
            $unusedCategories = $categoryRepository->findUnusedCategories($parent, $fixtureCategories);
            foreach ($unusedCategories as $category) {
                $this->deleteCategory($category, true);
                $this->manager->remove($category);
            }
        }
    }

    private function deleteCategory(?TranslationCategory $category, bool $root = false)
    {
        $this->manager->remove($category);
        if ($root) {
            $this->console->writeln("Deleting unused category \"{$category->getLabel()}\"");
        } else {
            $this->console->writeln("Cascade deleting unused category \"{$category->getLabel()}\", child of \"{$category->getParent()->getLabel()}\"");
        }

        foreach ($category->getChildren() as $child) {
            $this->deleteCategory($child);
        }

        foreach ($category->getTranslationSources() as $source) {
            $this->manager->remove($source);

            $translation = $source->getTranslationIn(Language::FRENCH_DEFAULT_SLUG);
            if ($translation) {
                $this->console->writeln("Cascade deleting unused source \"{$translation->getTranslation()}\" child of category \"{$category->getParent()->getLabel()}\"");
            } else {
                $this->console->writeln("Cascade deleting unknown unused source child of category \"{$category->getParent()->getLabel()}\"");
            }
        }
    }

    private function deleteUnusedTranslations(TranslationCategory $category, array $translations)
    {
        $translationSourceRepository = $this->manager->getRepository(TranslationSource::class);

        if ($category->getId()) {
            $fixtureTranslations = array_map(fn(array $item) => $item["fr"], $translations);
            $unusedTranslations = $translationSourceRepository->findUnusedTranslations($category, $fixtureTranslations);
            foreach ($unusedTranslations as $source) {
                $this->manager->remove($source);
                $this->console->writeln("Deleting unused source \"{$source->getTranslationIn("french-default")->getTranslation()}\" and all associated translations");
            }
        }
    }

    private function getLanguage(string $slug)
    {
        if (!isset($this->languages[$slug])) {
            $this->languages[$slug] = $this->manager->getRepository(Language::class)->findOneBy(["slug" => $slug]);
        }

        return $this->languages[$slug];
    }

    private function updateUsers()
    {
        $users = $this->manager->getRepository(Utilisateur::class)->iterateUserWithNullLanguageOrDateFormat();
        $french = $this->manager->getRepository(Language::class)->findOneBy(["slug" => "french"]);

        foreach ($users as $user) {
            if (!$user->getLanguage() || !$user->getDateFormat()) {
                $user
                    ->setLanguage($french)
                    ->setDateFormat(Utilisateur::DEFAULT_DATE_FORMAT);
            }
        }
    }

    public static function getGroups(): array
    {
        return ["fixtures", "language"];
    }
}
