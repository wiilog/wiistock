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
                    'j1' => 5,
                    'j2' => 12,
                    'j3' => 8,
                    'j4' => 1,
                    'j5' => 0,
                    'j6' => 9,
                    'j7' => 7,
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
                    'j1' => 5,
                    'j2' => 12,
                    'j3' => 8,
                    'j4' => 1,
                    'j5' => 0,
                    'j6' => 9,
                    'j7' => 7,
                    'stack' => [
                        [
                            'label' => 'Nature1',
                            'backgroundColor' => '#a0c',
                            'stack' => 'stack',
                            'data' => [15, 0, 5, 4, 7, 12, 4]
                        ],
                        [
                            'label' => 'Nature2',
                            'backgroundColor' => '#888',
                            'stack' => 'stack',
                            'data' => [15, 0, 5, 4, 7, 12, 4]
                        ],
                        [
                            'label' => 'Nature3',
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
        'Suivi des transporteur' => [
            'hint' => 'Transporteur ayant effectué un arrivage dans la journée',
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
                    'j1' => 4,
                    'j2' => 8,
                    'j3' => 6,
                    'j4' => 2,
                    'j5' => 8,
                    'j6' => 0,
                    'j7' => 13
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
                    's1' => 102,
                    's2' => 60,
                    's3' => 80,
                    's4' => 12,
                    's5' => 15,
                    's6' => 89,
                    's7' => 45,
                    'stack' => [
                        [
                            'label' => 'Nature1',
                            'backgroundColor' => '#a0c',
                            'stack' => 'stack',
                            'data' => [45, 12, 10, 15, 22, 35, 4]
                        ],
                        [
                            'label' => 'Nature2',
                            'backgroundColor' => '#888',
                            'stack' => 'stack',
                            'data' => [15, 6, 5, 4, 7, 12, 4]
                        ],
                        [
                            'label' => 'Nature3',
                            'backgroundColor' => '#e8b',
                            'stack' => 'stack',
                            'data' => [10, 9, 36, 23, 12, 45, 6]
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
                    'j1' => [
                        'Legende1' => 25,
                        'Legende2' => 12,
                    ],
                    'j2' => [
                        'Legende1' => 50,
                        'Legende2' => 5,
                    ],
                    'j3' => [
                        'Legende1' => 45,
                        'Legende2' => 36,
                    ],
                    'j4' => [
                        'Legende1' => 89,
                        'Legende2' => 102,
                    ],
                    'j5' => [
                        'Legende1' => 70,
                        'Legende2' => 74,
                    ],
                    'j6' => [
                        'Legende1' => 65,
                        'Legende2' => 52,
                    ],
                    'j7' => [
                        'Legende1' => 23,
                        'Legende2' => 47,
                    ]
                ],
            ],
            'category' => Dashboard\ComponentType::ORDERS,
            'meterKey' => Dashboard\ComponentType::PACK_TO_TREAT_FROM,
            'template' => Dashboard\ComponentType::PACK_TO_TREAT_FROM,
        ],
        'Nombre de colis distribués en Drop zone' => [
            'hint' => 'Nombre de colis présents sur les emplacements Drop zone paramétrés',
            'exampleValues' => [
                'chartData' => [
                    'j1' => 6,
                    'j2' => 5,
                    'j3' => 2,
                    'j4' => 9,
                    'j5' => 11,
                    'j6' => 12,
                    'j7' => 3,
                ],
            ],
            'category' => Dashboard\ComponentType::TRACKING,
            'template' => Dashboard\ComponentType::DROPPED_PACKS_DROPZONE,
            'meterKey' => Dashboard\ComponentType::DROPPED_PACKS_DROPZONE,
        ],
        'Entrées à effectuer' => [
            'hint' => 'Nombre de colis par natures paramétrées présents sur la durée paramétrée sur l\'ensemble des emplacements paramétrés',
            'exampleValues' => [
                'count' => 72,
                'nextLocation' => 'EMP1',
                'chartColors' => [
                    'Nature 1' => '#a3d1ff',
                    'Nature 2' => '#a3efdf',
                    'Nature 3' => '#aaafdf',
                ],
                'chartData' => [
                    ['Retard' => [
                        'Nature 1' => 25,
                        'Nature 2' => 25,
                        'Nature 3' => 12,
                    ]],
                    ['Moins d\'1h' => [
                        'Nature 1' => 15,
                        'Nature 2' => 2,
                        'Nature 3' => 12,
                    ]],
                    ['1h-4h' => [
                        'Nature 1' => 15,
                        'Nature 2' => 2,
                        'Nature 3' => 12,
                    ]],
                    ['4h-12h' => [
                        'Nature 1' => 15,
                        'Nature 2' => 2,
                        'Nature 3' => 12,
                    ]],
                    ['12h-24h' => [
                        'Nature 1' => 15,
                        'Nature 2' => 2,
                        'Nature 3' => 12,
                    ]],
                    ['24h-48h' => [
                        'Nature 1' => 0,
                        'Nature 2' => 0,
                        'Nature 3' => 0,
                    ]],
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
