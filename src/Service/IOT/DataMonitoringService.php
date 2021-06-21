<?php

namespace App\Service\IOT;

use App\Entity\Article;
use App\Entity\Emplacement;
use App\Entity\IOT\PairedEntity;
use App\Entity\IOT\Pairing;
use App\Entity\IOT\Sensor;
use App\Entity\LocationGroup;
use App\Entity\OrdreCollecte;
use App\Entity\Pack;
use App\Entity\Preparation;
use App\Helper\FormatHelper;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;
use WiiCommon\Helper\Stream;

class DataMonitoringService
{

    public const ASSOCIATED_CLASSES = [
        Sensor::TEMPERATURE => [
            Pack::class,
            Article::class,
            Emplacement::class
        ],
        Sensor::GPS => [
            Pack::class,
            Article::class,
        ]
    ];

    public const PAIRING = 1;
    public const TIMELINE = 2;

    /** @Required */
    public Environment $templating;

    /** @Required */
    public RouterInterface $router;

    public function render($config): Response
    {
        if($config["type"] === self::PAIRING) {
            /** @var Pairing $pairing */
            $pairing = $config["entity"];
            $entity = $pairing->getEntity();
            $this->fillPairingConfig($config, $pairing);
            $this->fillEntityConfig($entity, $config, false);
        } else if($config["type"] === self::TIMELINE) {
            $date = new DateTime('-1 month');
            $entity = $config['entity'];

            $this->fillEntityConfig($entity, $config, true);
            $config['start'] = $date;
            $config['end'] = new DateTime('now');
            if (in_array(get_class($entity), self::ASSOCIATED_CLASSES[Sensor::TEMPERATURE])) {
                $config["center_pane"][] = [
                    "type" => "chart",
                    "fetch_url" => $this->router->generate("chart_data_history", [
                        "type" => $config['entity_type'],
                        "id" => $entity->getId()
                    ], UrlGeneratorInterface::ABSOLUTE_URL)
                ];
            }

            if (in_array(get_class($entity), self::ASSOCIATED_CLASSES[Sensor::GPS])) {
                $config["center_pane"][] = [
                    "type" => "map",
                    "fetch_url" => $this->router->generate("map_data_history", [
                        "type" => $config['entity_type'],
                        "id" => $entity->getId()
                    ], UrlGeneratorInterface::ABSOLUTE_URL)
                ];
            }
        }

        return new Response($this->templating->render("iot/data_monitoring/page.html.twig", $config));
    }

    public function fillPairingConfig(array &$config, Pairing $pairing)
    {
        $start = FormatHelper::datetime($pairing->getStart(), null, true);
        $end = FormatHelper::datetime($pairing->getEnd(), null, true);

        $config["start"] = $pairing->getStart();
        $config["end"] = $pairing->getEnd();
        $config["left_pane"][] = [
            "type" => "sensor",
            "icon" => "wifi",
            "title" => $pairing->getSensorWrapper()->getName(),
            "pairing" => $pairing,
            "header" => true
        ];

        $config["left_pane"][] = [
            "type" => "pairingInfo",
            "start" => $start,
            "end" => $end
        ];

        $type = FormatHelper::type($pairing->getSensorWrapper()->getSensor()->getType());
        if ($type === Sensor::TEMPERATURE) {
            $config["center_pane"][] = [
                "type" => "chart",
                "fetch_url" => $this->router->generate("pairing_chart_data", [
                    "pairing" => $pairing->getId()
                ], UrlGeneratorInterface::ABSOLUTE_URL)
            ];
        } else if ($type === Sensor::GPS) {
            $config["center_pane"][] = [
                "type" => "map",
                "fetch_url" => $this->router->generate("pairing_map_data", [
                    "pairing" => $pairing->getId()
                ], UrlGeneratorInterface::ABSOLUTE_URL)
            ];
        }
    }

    private function fillPackConfig(array &$config, Pack $pack, bool $header)
    {
        $config["left_pane"][] = [
            "type" => "entity",
            "icon" => "iot-pack",
            "title" => $pack->getCode(),
            "pack" => $pack,
            "header" => $header,
            "hideActions" => $header
        ];
    }

    private function fillLocationConfig(array &$config, Emplacement $location, bool $header)
    {
        $config["left_pane"][] = [
            "type" => "entity",
            "icon" => "iot-location",
            "title" => $location->getLabel(),
            "header" => $header,
            "hideActions" => $header
        ];
    }

    private function fillLocationGroupConfig(array &$config, LocationGroup $location, bool $header)
    {
        $config["left_pane"][] = [
            "type" => "entity",
            "icon" => "iot-location",
            "title" => $location->getName(),
            "header" => $header,
            "hideActions" => $header
        ];
    }

    private function fillPreparationConfig(array &$config, Preparation $preparation, bool $header)
    {
        $items = [];
        if($preparation->getLivraison()) {
            $items[] = [
                "icon" => "iot-delivery",
                "title" => $preparation->getLivraison()->getNumero(),
            ];
        }

        $items[] = [
            "icon" => "iot-preparation",
            "title" => $preparation->getNumero(),
        ];

        $config["left_pane"][] = [
            "type" => "entity",
            "items" => $items,
            "header" => $header,
            "hideActions" => $header
        ];
    }

    private function fillCollectOrderConfig(array &$config, OrdreCollecte $collect, bool $header)
    {
        $config["left_pane"][] = [
            "type" => "entity",
            "icon" => "iot-collect",
            "title" => $collect->getNumero(),
            "header" => $header,
            "hideActions" => $header
        ];
    }

    private function fillArticleConfig(array &$config, Article $article, bool $header)
    {
        $config["left_pane"][] = [
            "type" => "entity",
            "icon" => "iot-article",
            "title" => $article->getLabel(),
            "header" => $header,
            "hideActions" => $header
        ];
    }

    private function fillTimelineConfig(array &$config, PairedEntity $pairedEntity)
    {
        $type = $pairedEntity instanceof Pack
            ? Sensor::PACK
            : null; // TODO

        $config["left_pane"][] = [
            "type" => "timeline",
            "timelineDataPath" => $this->router->generate('get_data_history_timeline_api', [
                "type" => $type,
                "id" => $pairedEntity->getId()
            ])
        ];
    }

    public function generateContent(): array
    {
        //TODO: generate chart or map data
        return [
            ["type" => "map"],
            ["type" => "chart"],
        ];
    }

    public function fillEntityConfig(?PairedEntity $entity, array &$config, bool $isHistoric) {
        if ($entity instanceof Pack) {
            $this->fillPackConfig($config, $entity, $isHistoric);
        } else if ($entity instanceof Emplacement) {
            $this->fillLocationConfig($config, $entity, $isHistoric);
        } else if ($entity instanceof LocationGroup) {
            $this->fillLocationGroupConfig($config, $entity, $isHistoric);
        } else if ($entity instanceof Preparation) {
            $this->fillPreparationConfig($config, $entity, $isHistoric);
        } else if ($entity instanceof OrdreCollecte) {
            $this->fillCollectOrderConfig($config, $entity, $isHistoric);
        } else if ($entity instanceof Article) {
            $this->fillArticleConfig($config, $entity, $isHistoric);
        } else {
            throw new RuntimeException("Unsupported class " . get_class($entity));
        }

        if ($isHistoric) {
            $this->fillTimelineConfig($config, $entity);
        }
    }

    public function getTimelineData(EntityManagerInterface $entityManager,
                                    RouterInterface $router,
                                    PairedEntity $entity,
                                    int $start,
                                    int $count): ?array {

        if ($entity instanceof Pack) {
            return $this->getPackTimelineData($entityManager, $router, $entity, $start, $count);
        }

        return null;
    }

    private function getPackTimelineData(EntityManagerInterface $entityManager,
                                         RouterInterface $routerInterface,
                                         Pack $pack,
                                         int $start,
                                         int $count): array {
        $packRepository = $entityManager->getRepository(Pack::class);
        $pairingData = $packRepository->getSensorPairingData($pack, $start, $count);
        $pairingDataCount = $packRepository->countSensorPairingData($pack);
        $subtitlePrefix = [
            'start' => 'Associé le : ',
            'end' => 'Dissocié le : '
        ];

        return [
            'data' => Stream::from($pairingData)
                ->filterMap(function ($data) use ($subtitlePrefix, $routerInterface) {
                    $dateStr = $data['date'] ?? null;
                    $type = $data['type'] ?? null;
                    $date = $dateStr
                        ? DateTime::createFromFormat('Y-m-d H:i:s', $dateStr)
                        : null;
                    return $date && $subtitlePrefix[$type]
                        ? [
                            'titleHref' => $routerInterface->generate('pairing_show', ['pairing' => $data['pairingId']]),
                            'title' => $data['name'] ?? '',
                            'datePrefix' => $subtitlePrefix[$type],
                            'date' => $date->format('d/m/Y à H:i'),
                            'active' => ($data['active'] ?? '0') === '1'
                        ]
                        : null;
                })
                ->toArray(),
            'isEnd' => $pairingDataCount <= ($start + $count)
        ];
    }

}
