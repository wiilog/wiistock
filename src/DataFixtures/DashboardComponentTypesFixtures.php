<?php

namespace App\DataFixtures;

use App\Entity\Dashboard;
use App\Service\SpecificService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class DashboardComponentTypesFixtures extends Fixture implements FixtureGroupInterface
{

    private $encoder;
    private $specificService;
    private $output;

    private const COMPONENT_TYPES = [
        'Quantité en cours sur n emplacement(s)' => [
            'hint' => 'Nombre de colis en encours sur les emplacements sélectionnés',
            'exampleValues' => [
                'title' => 'Litige en cours',
                'count' => 5,
                'subtitle' => 'Litige',
                'delay' => 20634860
            ],
            'category' => Dashboard\ComponentType::STOCK,
            'template' => Dashboard\ComponentType::ONGOING_PACKS,
            'meterKey' => Dashboard\ComponentType::ONGOING_PACKS
        ],
        'Nombre d\'arrivages quotidiens' => [
            'hint' => 'Nombre d\'arrivages créés par jour',
            'exampleValues' => [
                'chartData' => [
                    '04' => 5,
                    '05' => 12,
                    '06' => 8,
                    '07' => 1,
                    '08' => 0,
                    '09' => 9,
                    '10' => 7,
                ],
            ],
            'category' => Dashboard\ComponentType::TRACKING,
            'template' => null,
            'meterKey' => Dashboard\ComponentType::DAILY_ARRIVALS,
        ],
        'Colis en retard' => [
            'hint' => 'Les 100 colis les plus anciens ayant dépassé le délai de présence sur leur emplacement',
            'exampleValues' => [
                'tableData' => [
                    ['pack' => 'COLIS1', 'date' => '06/04/2020 10:27:09', 'delay' => '10000', 'location' => "EMP1"],
                    ['pack' => 'COLIS2', 'date' => '06/08/2020 20:57:89', 'delay' => '10000', 'location' => "EMP2"],
                ],
            ],
            'category' => Dashboard\ComponentType::TRACKING,
            'template' => null,
            'meterKey' => Dashboard\ComponentType::LATE_PACKS,
        ],
        'Nombre d\'arrivages et de colis quotidiens' => [
            'hint' => 'Nombre d\'arrivages et de colis créés par jour',
            'exampleValues' => [
                'stack' => true,
                'label' => 'Arrivages',
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
                ]
            ],
            'category' => Dashboard\ComponentType::TRACKING,
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
                ]
            ],
            'category' => Dashboard\ComponentType::TRACKING,
            'template' => Dashboard\ComponentType::CARRIER_TRACKING,
            'meterKey' => Dashboard\ComponentType::CARRIER_TRACKING,
        ],
        'Nombre d\'associations Arrivages - Réceptions' => [
            'hint' => 'Nombre de réceptions de traçabilité par jour',
            'exampleValues' => [
                'chartData' => [
                    '04' => 4,
                    '05' => 8,
                    '06' => 6,
                    '07' => 2,
                    '08' => 8,
                    '09' => 0,
                    '10' => 13
                ]
            ],
            'category' => Dashboard\ComponentType::TRACKING,
            'template' => null,
            'meterKey' => Dashboard\ComponentType::RECEIPT_ASSOCIATION,
        ],
        'Nombre d\'arrivages et de colis hebdomadaires' => [
            'hint' => 'Nombre d\'arrivage et de colis créés par semaine',
            'exampleValues' => [
                'stack' => true,
                'label' => 'Arrivages',
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
                ]
            ],
            'category' => Dashboard\ComponentType::TRACKING,
            'template' => Dashboard\ComponentType::WEEKLY_ARRIVALS_AND_PACKS,
            'meterKey' => Dashboard\ComponentType::WEEKLY_ARRIVALS_AND_PACKS,
        ],
        'Colis à traiter en provenance' => [
            'hint' => 'A définir',
            'exampleValues' => [
                'chartColors' => [
                    'Legende1' => '#a3d1ff',
                    'Legende2' => '#a3efdf'
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
            ],
            'category' => Dashboard\ComponentType::ORDERS,
            'meterKey' => Dashboard\ComponentType::PACK_TO_TREAT_FROM,
            'template' => Dashboard\ComponentType::PACK_TO_TREAT_FROM,
        ],
        'Nombre de colis distribués en dépose' => [
            'hint' => 'Nombre de colis présents sur les emplacements de dépose paramétrés',
            'exampleValues' => [
                'chartData' => [
                    '04/01' => 6,
                    '05/01' => 5,
                    '06/01' => 2,
                    '07/01' => 9,
                    '08/01' => 11,
                    '09/01' => 12,
                    '10/01' => 3,
                ],
            ],
            'category' => Dashboard\ComponentType::TRACKING,
            'template' => Dashboard\ComponentType::DROP_OFF_DISTRIBUTED_PACKS,
            'meterKey' => Dashboard\ComponentType::DROP_OFF_DISTRIBUTED_PACKS,
        ],
        'Entrées à effectuer' => [
            'hint' => 'Nombre de colis par natures paramétrées présents sur la durée paramétrée sur l\'ensemble des emplacements paramétrés',
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
                    '24h-48h' => [
                        'Standard' => 0,
                        'Congelé' => 0,
                        'Consommable' => 0,
                    ],
                ],
            ],
            'category' => Dashboard\ComponentType::ORDERS,
            'template' => Dashboard\ComponentType::ENTRIES_TO_HANDLE,
            'meterKey' => Dashboard\ComponentType::ENTRIES_TO_HANDLE,
        ],
    ];

    public function __construct(UserPasswordEncoderInterface $encoder,
                                SpecificService $specificService)
    {
        $this->encoder = $encoder;
        $this->specificService = $specificService;
        $this->output = new ConsoleOutput();
    }

    public function load(ObjectManager $manager)
    {
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
                ->setExampleValues($config['exampleValues'] ?? [])
                ->setCategory($config['category'] ?? null)
                ->setMeterKey($config['meterKey'] ?? null)
                ->setTemplate($config['template'] ?? null);
        }

        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['fixtures'];
    }

}
