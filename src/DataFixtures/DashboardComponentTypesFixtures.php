<?php

namespace App\DataFixtures;

use App\Entity\Dashboard;
use App\Service\SpecificService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Console\Output\ConsoleOutput;

class DashboardComponentTypesFixtures extends Fixture implements FixtureGroupInterface {

    private $specificService;
    private $output;

    public const COMPONENT_TYPES = [
        'Image externe' => [
            'hint' => 'Image statique',
            'exampleValues' => [
                'url' => '/img/mobile_logo_header.svg',
            ],
            'category' => Dashboard\ComponentType::CATEGORY_OTHER,
            'template' => Dashboard\ComponentType::EXTERNAL_IMAGE,
            'meterKey' => Dashboard\ComponentType::EXTERNAL_IMAGE,
        ],
        'Quantité en cours sur n emplacement(s)' => [
            'hint' => 'Nombre de colis en encours sur les emplacements sélectionnés',
            'exampleValues' => [
                'title' => 'Litige en cours',
                'count' => 5,
                'subtitle' => 'Litige',
                'delay' => 20634860,

                'textColor-1' => "#000000",
                'textBold-1' => false,
                'textItalic-1' => false,
                'textUnderline-1' => false,

                'textColor-2' => "#D73353",
                'textColor-3' => "#3353D7",
                'textColor-4' => "#000000",
                'textColor-5' => "#3353d7",

                'textBold-2' => false,
                'textBold-3' => false,
                'textBold-4' => false,
                'textBold-5' => false,

                'textItalic-2' => false,
                'textItalic-3' => false,
                'textItalic-4' => false,
                'textItalic-5' => false,

                'textUnderline-2' => false,
                'textUnderline-3' => false,
                'textUnderline-4' => false,
                'textUnderline-5' => false,
            ],
            'category' => Dashboard\ComponentType::CATEGORY_TRACKING,
            'template' => Dashboard\ComponentType::ONGOING_PACKS,
            'meterKey' => Dashboard\ComponentType::ONGOING_PACKS
        ],
        'Nombre d\'arrivages quotidiens' => [
            'hint' => 'Nombre d\'arrivages créés par jour',
            'exampleValues' => [
                'chartColors' => [
                    Dashboard\ComponentType::DEFAULT_CHART_COLOR
                ],
                'chartColorsLabels' => [
                    'Arrivages'
                ],
                'chartData' => [
                    '04' => 5,
                    '05' => 12,
                    '06' => 8,
                    '07' => 1,
                    '08' => 0,
                    '09' => 9,
                    '10' => 7,
                ],
                'textColor-1' => "#000000",
                'textBold-1' => false,
                'textItalic-1' => false,
                'textUnderline-1' => false,
            ],
            'category' => Dashboard\ComponentType::CATEGORY_TRACKING,
            'template' => Dashboard\ComponentType::GENERIC_TEMPLATE,
            'meterKey' => Dashboard\ComponentType::DAILY_ARRIVALS,
        ],
        'UL en retard' => [
            'hint' => 'Les 100 colis les plus anciens ayant dépassé le délai de présence sur leur emplacement',
            'inSplitCell' => false,
            'exampleValues' => [
                'tableData' => [
                    ['pack' => 'COLIS1', 'date' => '06/04/2020 10:27:09', 'delay' => '10000', 'location' => "EMP1"],
                    ['pack' => 'COLIS2', 'date' => '06/08/2020 20:57:29', 'delay' => '10000', 'location' => "EMP2"]
                ],
                'textColor-1' => "#000000",
                'textBold-1' => false,
                'textItalic-1' => false,
                'textUnderline-1' => false,

                'textColor-2' => "#000000",
                'textBold-2' => false,
                'textItalic-2' => false,
                'textUnderline-2' => false,

                'textColor-3' => "#000000",
                'textBold-3' => false,
                'textItalic-3' => false,
                'textUnderline-3' => false,
            ],
            'category' => Dashboard\ComponentType::CATEGORY_TRACKING,
            'template' => Dashboard\ComponentType::GENERIC_TEMPLATE,
            'meterKey' => Dashboard\ComponentType::LATE_PACKS,
        ],
        'Nombre d\'arrivages et d\'UL quotidiens' => [
            'hint' => 'Nombre d\'arrivages et d\'UL créés par jour',
            'exampleValues' => [
                'stack' => true,
                'label' => 'Arrivages',
                'chartColors' => [
                    '#77933C',
                    '#003871'
                ],
                'chartColorsLabels' => [
                    'Arrivages',
                    'Unité logistique'
                ],
                'chartData' => [
                    '04/01' => 5,
                    '05/01' => 12,
                    '06/01' => 8,
                    '07/01' => 1,
                    '08/01' => 0,
                    '11/01' => 9,
                    '12/01' => 7,
                    'stack' => [
                        [
                            'label' => 'Standard',
                            'backgroundColor' => '#a0c',
                            'stack' => 'stack',
                            'data' => [15, 0, 5, 4, 7, 12, 4]
                        ],
                        [
                            'label' => 'Consommable',
                            'backgroundColor' => '#888',
                            'stack' => 'stack',
                            'data' => [15, 0, 5, 4, 7, 12, 4]
                        ],
                        [
                            'label' => 'Congelé',
                            'backgroundColor' => '#e8b',
                            'stack' => 'stack',
                            'data' => [15, 0, 5, 4, 7, 12, 4]
                        ]
                    ]
                ],
                'textColor-1' => "#000000",
                'textBold-1' => false,
                'textItalic-1' => false,
                'textUnderline-1' => false,
            ],
            'category' => Dashboard\ComponentType::CATEGORY_TRACKING,
            'meterKey' => Dashboard\ComponentType::DAILY_ARRIVALS_AND_PACKS,
            'template' => Dashboard\ComponentType::DAILY_ARRIVALS_AND_PACKS,
        ],
        'Suivi des transporteurs' => [
            'hint' => 'Transporteurs ayant effectué un arrivage dans la journée',
            'exampleValues' => [
                'carriers' => [
                    'TRANS1',
                    'TRANS2',
                    'TRANS3',
                ],
                'textColor-1' => "#000000",
                'textBold-1' => false,
                'textItalic-1' => false,
                'textUnderline-1' => false,
                'textColor-2' => "#000000",
                'textBold-2' => false,
                'textItalic-2' => false,
                'textUnderline-2' => false,
            ],
            'category' => Dashboard\ComponentType::CATEGORY_TRACKING,
            'template' => Dashboard\ComponentType::CARRIER_TRACKING,
            'meterKey' => Dashboard\ComponentType::CARRIER_TRACKING,
        ],
        'Nombre d\'associations Arrivages - Réceptions' => [
            'hint' => 'Nombre de réceptions de traçabilité par jour',
            'exampleValues' => [
                'chartColors' => [
                    Dashboard\ComponentType::DEFAULT_CHART_COLOR
                ],
                'chartColorsLabels' => [
                    'Association A-R'
                ],
                'chartData' => [
                    '04' => 4,
                    '05' => 8,
                    '06' => 6,
                    '07' => 2,
                    '08' => 8,
                    '09' => 0,
                    '10' => 13
                ],
                'textColor-1' => "#000000",
                'textBold-1' => false,
                'textItalic-1' => false,
                'textUnderline-1' => false,
            ],
            'category' => Dashboard\ComponentType::CATEGORY_TRACKING,
            'template' => Dashboard\ComponentType::GENERIC_TEMPLATE,
            'meterKey' => Dashboard\ComponentType::RECEIPT_ASSOCIATION,
        ],
        'Nombre d\'arrivages et d\'UL hebdomadaires' => [
            'hint' => 'Nombre d\'arrivage et d\'UL créés par semaine',
            'exampleValues' => [
                'stack' => true,
                'label' => 'Arrivages',
                'chartColors' => [
                    '#77933C',
                    '#003871'
                ],
                'chartColorsLabels' => [
                    'Arrivages',
                    'Unité logistique'
                ],
                'chartData' => [
                    'S01' => 102,
                    'S02' => 60,
                    'S03' => 80,
                    'S04' => 12,
                    'S05' => 15,
                    'stack' => [
                        [
                            'label' => 'Standard',
                            'backgroundColor' => '#a0c',
                            'stack' => 'stack',
                            'data' => [45, 12, 10, 15, 22]
                        ],
                        [
                            'label' => 'Consommable',
                            'backgroundColor' => '#888',
                            'stack' => 'stack',
                            'data' => [15, 6, 5, 4, 7]
                        ],
                        [
                            'label' => 'Congelé',
                            'backgroundColor' => '#e8b',
                            'stack' => 'stack',
                            'data' => [10, 9, 36, 23, 12]
                        ]
                    ]
                ],
                'textColor-1' => "#000000",
                'textBold-1' => false,
                'textItalic-1' => false,
                'textUnderline-1' => false,
            ],
            'category' => Dashboard\ComponentType::CATEGORY_TRACKING,
            'template' => Dashboard\ComponentType::WEEKLY_ARRIVALS_AND_PACKS,
            'meterKey' => Dashboard\ComponentType::WEEKLY_ARRIVALS_AND_PACKS,
        ],
        'Colis à traiter en provenance' => [
            'hint' => 'Nombre de colis à traiter en fonction des emplacements d\'origine et de destination paramétrés',
            'exampleValues' => [
                'chartColors' => [
                    'Legende1' => '#77933C',
                    'Legende2' => '#003871'
                ],
                'chartData' => [
                    '04/01' => [
                        'Legende1' => 25,
                        'Legende2' => 12,
                    ],
                    '05/01' => [
                        'Legende1' => 50,
                        'Legende2' => 5,
                    ],
                    '06/01' => [
                        'Legende1' => 45,
                        'Legende2' => 36,
                    ],
                    '07/01' => [
                        'Legende1' => 89,
                        'Legende2' => 102,
                    ],
                    '08/01' => [
                        'Legende1' => 70,
                        'Legende2' => 74,
                    ],
                    '09/01' => [
                        'Legende1' => 65,
                        'Legende2' => 52,
                    ],
                    '10/01' => [
                        'Legende1' => 23,
                        'Legende2' => 47,
                    ]
                ],
                'textColor-1' => "#000000",
                'textBold-1' => false,
                'textItalic-1' => false,
                'textUnderline-1' => false,
            ],
            'category' => Dashboard\ComponentType::CATEGORY_ORDERS,
            'meterKey' => Dashboard\ComponentType::PACK_TO_TREAT_FROM,
            'template' => Dashboard\ComponentType::PACK_TO_TREAT_FROM,
        ],
        'Nombre de colis distribués en dépose' => [
            'hint' => 'Nombre de colis présents sur les emplacements de dépose paramétrés',
            'exampleValues' => [
                'chartColors' => [
                    Dashboard\ComponentType::DEFAULT_CHART_COLOR
                ],
                'chartColorsLabels' => [
                    'Colis'
                ],
                'chartData' => [
                    '04/01' => 6,
                    '05/01' => 5,
                    '06/01' => 2,
                    '07/01' => 9,
                    '08/01' => 11,
                    '09/01' => 12,
                    '10/01' => 3,
                ],
                'textColor-1' => "#000000",
                'textBold-1' => false,
                'textItalic-1' => false,
                'textUnderline-1' => false,
            ],
            'category' => Dashboard\ComponentType::CATEGORY_TRACKING,
            'template' => Dashboard\ComponentType::DROP_OFF_DISTRIBUTED_PACKS,
            'meterKey' => Dashboard\ComponentType::DROP_OFF_DISTRIBUTED_PACKS,
        ],
        'Demandes en cours' => [
            'hint' => "Liste des demandes de l'entité sélectionnée en cours",
            'exampleValues' => [
                'requests' => [
                    [
                        'estimatedFinishTime' => '16:54',
                        'estimatedFinishTimeLabel' => 'Heure de livraison estimée',
                        'requestStatus' => 'À traiter',
                        'requestBodyTitle' => '0 article - LIV - BCO',
                        'requestLocation' => 'MAG 003 - EXPEDITION',
                        'requestNumber' => 'DL21010005',
                        'requestDate' => '18 Janv. (12h01)',
                        'requestUser' => 'mbenoukaiss',
                        'cardColor' => 'white',
                        'bodyColor' => 'light-grey',
                        'topRightIcon' => 'livreur.svg',
                        'progress' => 0,
                        'progressBarColor' => '#2ec2ab',
                        'emergencyText' => '',
                        'progressBarBGColor' => 'light-grey',
                    ], [
                        'estimatedFinishTime' => 'Non estimée',
                        'estimatedFinishTimeLabel' => 'Date de livraison non estimée',
                        'requestStatus' => 'Brouillon',
                        'requestBodyTitle' => '1 article - standard',
                        'requestLocation' => 'MAG 003 - EXPEDITION',
                        'requestNumber' => 'DL21010001',
                        'requestDate' => '07 Janv. (12h55)',
                        'requestUser' => 'mbenoukaiss',
                        'cardColor' => 'light-grey',
                        'bodyColor' => 'white',
                        'topRightIcon' => 'livreur.svg',
                        'progress' => 0,
                        'progressBarColor' => '#2ec2ab',
                        'emergencyText' => '',
                        'progressBarBGColor' => 'white',
                    ],
                ],
                'textColor-1' => "#000000",
                'textBold-1' => false,
                'textItalic-1' => false,
                'textUnderline-1' => false,

                'textColor-2' => "#000000",
                'textBold-2' => false,
                'textItalic-2' => false,
                'textUnderline-2' => false,

                'textColor-3' => "#3353D7",
                'textBold-3' => false,
                'textItalic-3' => false,
                'textUnderline-3' => false,

                'textColor-4' => "#000000",
                'textBold-4' => false,
                'textItalic-4' => false,
                'textUnderline-4' => false,

                'textColor-5' => "#5867DD",
                'textBold-5' => false,
                'textItalic-5' => false,
                'textUnderline-5' => false,

                'textColor-6' => "#000000",
                'textBold-6' => false,
                'textItalic-6' => false,
                'textUnderline-6' => false,

                'textColor-7' => "#000000",
                'textBold-7' => false,
                'textItalic-7' => false,
                'textUnderline-7' => false,

                'textColor-8' => "#EEEEEE",
                'textBold-8' => false,
                'textItalic-8' => false,
                'textUnderline-8' => false,

                'textColor-9' => "#000000",
                'textBold-9' => false,
                'textItalic-9' => false,
                'textUnderline-9' => false,
            ],
            'category' => Dashboard\ComponentType::CATEGORY_REQUESTS,
            'template' => Dashboard\ComponentType::PENDING_REQUESTS,
            'meterKey' => Dashboard\ComponentType::PENDING_REQUESTS,
        ],
        'Entrées à effectuer' => [
            'hint' => "Nombre de colis par natures paramétrées présents sur la durée paramétrée sur l'ensemble des emplacements paramétrés",
            'exampleValues' => [
                'count' => 72,
                'segments' => ['4', '8', '12', '16'],
                'nextLocation' => 'EMP1',
                'chartColors' => [
                    'Standard' => '#a3d1ff',
                    'Congelé' => '#a3efdf',
                    'Consommable' => '#aaafdf',
                ],
                'chartData' => [
                    'Retard' => [
                        'Standard' => 25,
                        'Congelé' => 25,
                        'Consommable' => 12,
                    ],
                    'Moins d\'1h' => [
                        'Standard' => 15,
                        'Congelé' => 2,
                        'Consommable' => 12,
                    ],
                    '1h-4h' => [
                        'Standard' => 15,
                        'Congelé' => 2,
                        'Consommable' => 12,
                    ],
                    '4h-12h' => [
                        'Standard' => 15,
                        'Congelé' => 2,
                        'Consommable' => 12,
                    ],
                    '12h-24h' => [
                        'Standard' => 15,
                        'Congelé' => 2,
                        'Consommable' => 12,
                    ],
                    '24h-36h' => [
                        'Standard' => 0,
                        'Congelé' => 0,
                        'Consommable' => 0,
                    ],
                    '36h-48h' => [
                        'Standard' => 0,
                        'Congelé' => 0,
                        'Consommable' => 0,
                    ],
                ],
                'textColor-1' => "#000000",
                'textBold-1' => false,
                'textItalic-1' => false,
                'textUnderline-1' => false,

                'textColor-2' => "#000000",
                'textBold-2' => false,
                'textItalic-2' => false,
                'textUnderline-2' => false,

                'textColor-3' => "#3353D7",
                'textBold-3' => false,
                'textItalic-3' => false,
                'textUnderline-3' => false,

                'textColor-4' => "#000000",
                'textBold-4' => false,
                'textItalic-4' => false,
                'textUnderline-4' => false,

                'textColor-5' => "#3353d7",
                'textBold-5' => false,
                'textItalic-5' => false,
                'textUnderline-5' => false,
            ],
            'category' => Dashboard\ComponentType::CATEGORY_ORDERS,
            'template' => Dashboard\ComponentType::ENTRIES_TO_HANDLE,
            'meterKey' => Dashboard\ComponentType::ENTRIES_TO_HANDLE,
        ],
        'Urgences à recevoir' => [
            'hint' => 'Nombre d\'urgences sur arrivage encore non réceptionnées',
            'category' => Dashboard\ComponentType::CATEGORY_TRACKING,
            'template' => Dashboard\ComponentType::GENERIC_TEMPLATE,
            'meterKey' => Dashboard\ComponentType::ARRIVALS_EMERGENCIES_TO_RECEIVE,
            'exampleValues' => [
                'count' => 7,
                'textColor-1' => "#000000",
                'textBold-1' => false,
                'textItalic-1' => false,
                'textUnderline-1' => false,
                'textColor-3' => "#3353D7",
                'textBold-3' => false,
                'textItalic-3' => false,
                'textUnderline-3' => false,
            ],
        ],
        'Urgences du jour' => [
            'hint' => 'Nombre d\'urgences sur arrivage devant être réceptionnées dans la journée',
            'category' => Dashboard\ComponentType::CATEGORY_TRACKING,
            'template' => Dashboard\ComponentType::GENERIC_TEMPLATE,
            'meterKey' => Dashboard\ComponentType::DAILY_ARRIVALS_EMERGENCIES,
            'exampleValues' => [
                'count' => 3,
                'textColor-1' => "#000000",
                'textBold-1' => false,
                'textItalic-1' => false,
                'textUnderline-1' => false,
                'textColor-3' => "#3353D7",
                'textBold-3' => false,
                'textItalic-3' => false,
                'textUnderline-3' => false,
            ],
        ],
        'Fiabilité monétaire (graphique)' => [
            'hint' => 'Somme des quantités corrigées suite à un inventaire',
            'category' => Dashboard\ComponentType::CATEGORY_STOCK,
            'template' => Dashboard\ComponentType::GENERIC_TEMPLATE,
            'meterKey' => Dashboard\ComponentType::MONETARY_RELIABILITY_GRAPH,
            'exampleValues' => [
                'chartColors' => [
                    Dashboard\ComponentType::DEFAULT_CHART_COLOR
                ],
                'chartColorsLabels' => [
                    'Fiabilité monétaire'
                ],
                'chartData' => [
                    'Août' => 243,
                    'Septembre' => 145,
                    'Octobre' => 312,
                    'Novembre' => -177,
                    'Décembre' => -67,
                    'Janvier' => 198
                ],
                'textColor-1' => "#000000",
                'textBold-1' => false,
                'textItalic-1' => false,
                'textUnderline-1' => false,
            ],
        ],
        'Alertes de stock' => [
            'hint' => 'Nombre d\'alertes de péremption, seuil de sécurité et alerte en cours',
            'category' => Dashboard\ComponentType::CATEGORY_STOCK,
            'template' => Dashboard\ComponentType::ACTIVE_REFERENCE_ALERTS,
            'meterKey' => Dashboard\ComponentType::ACTIVE_REFERENCE_ALERTS,
            'exampleValues' => [
                'count' => 11,
                'textColor-1' => "#000000",
                'textBold-1' => false,
                'textItalic-1' => false,
                'textUnderline-1' => false,
                'textColor-3' => "#3353D7",
                'textBold-3' => false,
                'textItalic-3' => false,
                'textUnderline-3' => false,
            ],
        ],
        'Nombre de services quotidiens' => [
            'hint' => 'Nombre de services ayant leur date attendue sur les jours présentés',
            'exampleValues' => [
                'chartColors' => [
                    Dashboard\ComponentType::DEFAULT_CHART_COLOR,
                ],
                'chartColorsLabels' => [
                    'Services'
                ],
                'chartData' => [
                    '04/01' => 6,
                    '05/01' => 8,
                    '06/01' => 4,
                    '07/01' => 5,
                    '08/01' => 1,
                    '09/01' => 3,
                    '10/01' => 2,
                ],
                'chartDataMultiple' => [
                    '04/01' => [
                        'Type1' => 25,
                        'Type2' => 12,
                    ],
                    '05/01' => [
                        'Type1' => 10,
                        'Type2' => 12,
                    ],
                    '06/01' => [
                        'Type1' => 4,
                        'Type2' => 12,
                    ],
                    '07/01' => [
                        'Type1' => 25,
                        'Type2' => 9,
                    ],
                    '08/01' => [
                        'Type1' => 15,
                        'Type2' => 12,
                    ],
                    '09/01' => [
                        'Type1' => 2,
                        'Type2' => 12,
                    ],
                    '10/01' => [
                        'Type1' => 23,
                        'Type2' => 8,
                    ]
                ],
                'textColor-1' => "#000000",
                'textBold-1' => false,
                'textItalic-1' => false,
                'textUnderline-1' => false,
            ],
            'category' => Dashboard\ComponentType::CATEGORY_REQUESTS,
            'template' => Dashboard\ComponentType::DAILY_HANDLING,
            'meterKey' => Dashboard\ComponentType::DAILY_HANDLING,
        ],
        'Nombre d\'opérations quotidiennes (services)' => [
            'hint' => 'Nombre d\'opérations quotidiennes sur les services ayant leur date attendue sur les jours présentés',
            'exampleValues' => [
                'chartColors' => [
                    Dashboard\ComponentType::DEFAULT_CHART_COLOR,
                ],
                'chartColorsLabels' => [
                    'Opérations'
                ],
                'chartData' => [
                    '04/01' => 6,
                    '05/01' => 8,
                    '06/01' => 4,
                    '07/01' => 5,
                    '08/01' => 1,
                    '09/01' => 3,
                    '10/01' => 2,
                ],
                'chartDataMultiple' => [
                    '04/01' => [
                        'Type1' => 25,
                        'Type2' => 12,
                    ],
                    '05/01' => [
                        'Type1' => 10,
                        'Type2' => 12,
                    ],
                    '06/01' => [
                        'Type1' => 4,
                        'Type2' => 12,
                    ],
                    '07/01' => [
                        'Type1' => 25,
                        'Type2' => 9,
                    ],
                    '08/01' => [
                        'Type1' => 15,
                        'Type2' => 12,
                    ],
                    '09/01' => [
                        'Type1' => 2,
                        'Type2' => 12,
                    ],
                    '10/01' => [
                        'Type1' => 23,
                        'Type2' => 8,
                    ]
                ],
                'textColor-1' => "#000000",
                'textBold-1' => false,
                'textItalic-1' => false,
                'textUnderline-1' => false,
            ],
            'category' => Dashboard\ComponentType::CATEGORY_REQUESTS,
            'template' => Dashboard\ComponentType::DAILY_HANDLING,
            'meterKey' => Dashboard\ComponentType::DAILY_OPERATIONS,
        ],
        'Fiabilité monétaire (indicateur)' => [
            'hint' => 'Quantité corrigée sur une référence ou article * prix unitaire de la référence ou référence liée',
            'category' => Dashboard\ComponentType::CATEGORY_STOCK,
            'template' => Dashboard\ComponentType::GENERIC_TEMPLATE,
            'meterKey' => Dashboard\ComponentType::MONETARY_RELIABILITY_INDICATOR,
            'exampleValues' => [
                'count' => 84,
                'textColor-1' => "#000000",
                'textBold-1' => false,
                'textItalic-1' => false,
                'textUnderline-1' => false,
                'textColor-3' => "#3353D7",
                'textBold-3' => false,
                'textItalic-3' => false,
                'textUnderline-3' => false,
            ],
        ],
        'Nombre d\'acheminements quotidiens' => [
            'hint' => 'Nombre d\'acheminements avec date de référence paramétrable sur les jours présentés',
            'exampleValues' => [
                'chartColors' => [
                    Dashboard\ComponentType::DEFAULT_CHART_COLOR
                ],
                'chartColorsLabels' => [
                    'Acheminements'
                ],
                'chartData' => [
                    '04/01' => 2,
                    '05/01' => 6,
                    '06/01' => 4,
                    '07/01' => 0,
                    '08/01' => 10,
                    '09/01' => 8,
                    '10/01' => 5,
                ],
                'chartDataMultiple' => [
                    '04/01' => [
                        'Type1' => 25,
                        'Type2' => 12,
                    ],
                    '05/01' => [
                        'Type1' => 10,
                        'Type2' => 12,
                    ],
                    '06/01' => [
                        'Type1' => 4,
                        'Type2' => 12,
                    ],
                    '07/01' => [
                        'Type1' => 25,
                        'Type2' => 9,
                    ],
                    '08/01' => [
                        'Type1' => 15,
                        'Type2' => 12,
                    ],
                    '09/01' => [
                        'Type1' => 2,
                        'Type2' => 12,
                    ],
                    '10/01' => [
                        'Type1' => 23,
                        'Type2' => 8,
                    ]
                ],
                'textColor-1' => "#000000",
                'textBold-1' => false,
                'textItalic-1' => false,
                'textUnderline-1' => false,
            ],
            'category' => Dashboard\ComponentType::CATEGORY_REQUESTS,
            'template' => Dashboard\ComponentType::DAILY_DISPATCHES,
            'meterKey' => Dashboard\ComponentType::DAILY_DISPATCHES,
        ],
        'Suivi des demandes de services' => [
            'hint' => 'Suivi des demandes de services sur les 7 derniers jours',
            'exampleValues' => [
                'chartColors' => [
                    'Date de création' => '#FF763D',
                    'Date attendue' => '#A5D733',
                    'Date de traitement' => '#3353D7'
                ],
                'chartData' => [
                    '04/01' => [
                        'creationDate' => 25,
                        'desiredDate' => 12,
                        'validationDate' => 12,
                    ],
                    '05/01' => [
                        'creationDate' => 10,
                        'desiredDate' => 12,
                        'validationDate' => 12,
                    ],
                    '06/01' => [
                        'creationDate' => 4,
                        'desiredDate' => 12,
                        'validationDate' => 12,
                    ],
                    '07/01' => [
                        'creationDate' => 25,
                        'desiredDate' => 9,
                        'validationDate' => 9,
                    ],
                    '08/01' => [
                        'creationDate' => 15,
                        'desiredDate' => 15,
                        'validationDate' => 12,
                    ],
                    '09/01' => [
                        'creationDate' => 2,
                        'desiredDate' => 12,
                        'validationDate' => 12,
                    ],
                    '10/01' => [
                        'creationDate' => 23,
                        'desiredDate' => 8,
                        'validationDate' => 8,
                    ]
                ],
                'textColor-1' => "#000000",
                'textBold-1' => false,
                'textItalic-1' => false,
                'textUnderline-1' => false,
            ],
            'category' => Dashboard\ComponentType::CATEGORY_REQUESTS,
            'template' => Dashboard\ComponentType::HANDLING_TRACKING,
            'meterKey' => Dashboard\ComponentType::HANDLING_TRACKING,
        ],
        'Fiabilité par référence' => [
            'hint' => 'Nombre de mouvements de correction d’inventaire / le nombre d’articles de référence ou articles du stock',
            'category' => Dashboard\ComponentType::CATEGORY_STOCK,
            'template' => Dashboard\ComponentType::GENERIC_TEMPLATE,
            'meterKey' => Dashboard\ComponentType::REFERENCE_RELIABILITY,
            'exampleValues' => [
                'count' => 25,
                'textColor-1' => "#000000",
                'textBold-1' => false,
                'textItalic-1' => false,
                'textUnderline-1' => false,
                'textColor-3' => "#3353D7",
                'textBold-3' => false,
                'textItalic-3' => false,
                'textUnderline-3' => false,
            ]
        ],
        "Nombre d'ordres de livraisons quotidiens" => [
            'hint' => "Nombre d'ordres de livraison ayant leur date attendue sur les jours présentés",
            'exampleValues' => [
                'stack' => true,
                'label' => 'Arrivages',
                'chartColors' => [
                    '#77933C',
                    '#003871'
                ],
                'chartColorsLabels' => [
                    'Livraison',
                    'Unité logistique'
                ],
                'chartData' => [
                    '04/01' => 5,
                    '05/01' => 12,
                    '06/01' => 8,
                    '07/01' => 1,
                    '08/01' => 3,
                    '11/01' => 9,
                    '12/01' => 7,
                    'stack' => [
                        [
                            'label' => 'Unité logistique',
                            'backgroundColor' => '#a0c',
                            'stack' => 'stack',
                            'data' => [15, 18, 13, 4, 7, 12, 10]
                        ],
                    ]
                ],
                'textColor-1' => "#000000",
                'textBold-1' => false,
                'textItalic-1' => false,
                'textUnderline-1' => false,
            ],
            'category' => Dashboard\ComponentType::CATEGORY_ORDERS,
            'template' => Dashboard\ComponentType::DAILY_DELIVERY_ORDERS,
            'meterKey' => Dashboard\ComponentType::DAILY_DELIVERY_ORDERS,
        ],
        'Demandes à traiter' => [
            'hint' => 'Nombre de demandes pour l\'entité, le(s) type(s) et statut(s) sélectionnés',
            'category' => Dashboard\ComponentType::CATEGORY_REQUESTS,
            'template' => 'entities_to_treat',
            'meterKey' => Dashboard\ComponentType::REQUESTS_TO_TREAT,
            'exampleValues' => [
                'title' => 'Services à traiter',
                'count' => 5,
                'delay' => 51025698,
                'textColor-1' => "#000000",
                'textBold-1' => false,
                'textItalic-1' => false,
                'textUnderline-1' => false,

                'textColor-3' => "#3353D7",
                'textBold-3' => false,
                'textItalic-3' => false,
                'textUnderline-3' => false,

                'textColor-4' => "#000000",
                'textBold-4' => false,
                'textItalic-4' => false,
                'textUnderline-4' => false,

                'textColor-5' => "#3353d7",
                'textBold-5' => false,
                'textItalic-5' => false,
                'textUnderline-5' => false,
            ]
        ],
        'Nombre de services du jour' => [
            'hint' => 'Nombre de services du jour',
            'category' => Dashboard\ComponentType::CATEGORY_REQUESTS,
            'template' => Dashboard\ComponentType::DAILY_HANDLING_INDICATOR,
            'meterKey' => Dashboard\ComponentType::DAILY_HANDLING_INDICATOR,
            'exampleValues' => [
                'title' => 'Services',
                'hint' => 'Services du jour',
                'count' => 5,

                'textColor-1' => "#000000",
                'textBold-1' => false,
                'textItalic-1' => false,
                'textUnderline-1' => false,

                'textColor-3' => "#3353D7",
                'textBold-3' => false,
                'textItalic-3' => false,
                'textUnderline-3' => false,

                'textColor-9' => "#3353D7",
                'textBold-9' => false,
                'textItalic-9' => false,
                'textUnderline-9' => false,

                'textColor-10' => "#D73353",
                'textBold-10' => false,
                'textItalic-10' => false,
                'textUnderline-10' => false,

                'subCounts' => [
                    '<span>150</span> <span class="text-wii-black">lignes</span>',
                    '<span class="text-wii-black">Dont</span> <span class="font-">3</span> <span class="text-wii-black">urgences</span>'
                ]
            ]
        ],
        'Ordres à traiter' => [
            'hint' => 'Nombre d\'ordres pour l\'entité, le(s) type(s) et statut(s) sélectionnés',
            'category' => Dashboard\ComponentType::CATEGORY_ORDERS,
            'template' => 'entities_to_treat',
            'meterKey' => Dashboard\ComponentType::ORDERS_TO_TREAT,
            'exampleValues' => [
                'title' => 'Ordre de collecte à traiter',
                'count' => 3,
                'textColor-1' => "#000000",
                'textBold-1' => false,
                'textItalic-1' => false,
                'textUnderline-1' => false,

                'delay' => 42622697,
                'textColor-3' => "#3353D7",
                'textBold-3' => false,
                'textItalic-3' => false,
                'textUnderline-3' => false,

                'textColor-4' => "#000000",
                'textBold-4' => false,
                'textItalic-4' => false,
                'textUnderline-4' => false,

                'textColor-5' => "#000000",
                'textBold-5' => false,
                'textItalic-5' => false,
                'textUnderline-5' => false,

                'textColor-6' => "#000000",
                'textBold-6' => false,
                'textItalic-6' => false,
                'textUnderline-6' => false,

                'subCounts' => [
                    '<span>Nombre d\'unités logistiques</span>',
                    '<span class="text-wii-black dashboard-stats dashboard-stats-counter">3</span>'
                ]
            ]
        ]
    ];

    public function __construct(SpecificService $specificService) {
        $this->specificService = $specificService;
        $this->output = new ConsoleOutput();
    }

    public function load(ObjectManager $manager) {
        $componentTypeRepository = $manager->getRepository(Dashboard\ComponentType::class);
        $alreadyExisting = $componentTypeRepository->findAll();
        $alreadyExistingName = [];

        // remove unused ComponentType
        foreach ($alreadyExisting as $componentType) {
            $name = $componentType->getName();
            if (!isset(self::COMPONENT_TYPES[$name])) {
                $manager->remove($componentType);
                $this->output->writeln("Component Type \"$name\" removed");
            } else {
                $alreadyExistingName[$name] = $componentType;
            }
        }

        // we persist new ComponentType
        foreach (self::COMPONENT_TYPES as $name => $config) {
            $componentType = $alreadyExistingName[$name] ?? null;
            $componentTypeExisted = isset($componentType);
            if (!$componentTypeExisted) {
                $componentType = new Dashboard\ComponentType();
                $componentType->setName($name);
                $manager->persist($componentType);

                $this->output->writeln("Component type \"$name\" created");
            }

            $componentType
                ->setHint($config['hint'] ?? null)
                ->setInSplitCell($config['inSplitCell'] ?? true)
                ->setExampleValues($config['exampleValues'] ?? [])
                ->setCategory($config['category'] ?? null)
                ->setMeterKey($config['meterKey'] ?? null)
                ->setTemplate($config['template'] ?? null);
        }

        $manager->flush();
    }

    public static function getGroups(): array {
        return ['fixtures'];
    }

}
