<?php

namespace App\Service\IOT;

use App\Entity\Article;
use App\Entity\Collecte;
use App\Entity\DeliveryRequest\Demande;
use App\Entity\Emplacement;
use App\Entity\IOT\PairedEntity;
use App\Entity\IOT\Pairing;
use App\Entity\IOT\Sensor;
use App\Entity\LocationGroup;
use App\Entity\OrdreCollecte;
use App\Entity\Pack;
use App\Entity\PreparationOrder\Preparation;
use App\Entity\Transport\Vehicle;
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

    public const PAIRING = 1;
    public const TIMELINE = 2;

    /** @Required */
    public Environment $templating;

    /** @Required */
    public RouterInterface $router;

    /** @Required */
    public IOTService $IOTService;

    public function render($config): Response
    {
        if ($config["type"] === self::PAIRING) {
            /** @var Pairing $pairing */
            $pairing = $config["entity"];
            $entity = $pairing->getEntity();
            $this->fillPairingConfig($config, $pairing);
            $this->fillEntityConfig($entity, $config, false);
        } else if ($config["type"] === self::TIMELINE) {
            $date = new DateTime('-1 month');
            $entity = $config['entity'];
            $this->fillEntityConfig($entity, $config, true);
            $config['start'] = $date;
            $config['end'] = new DateTime('now');
            $config["center_pane"][] = [
                "type" => "chart",
                "fetch_url" => $this->router->generate("chart_data_history", [
                    "type" => IOTService::getEntityCodeFromEntity($entity),
                    "id" => $entity->getId()
                ], UrlGeneratorInterface::ABSOLUTE_URL)
            ];
            $config["center_pane"][] = [
                "type" => "map",
                "fetch_url" => $this->router->generate("map_data_history", [
                    "type" => IOTService::getEntityCodeFromEntity($entity),
                    "id" => $entity->getId()
                ], UrlGeneratorInterface::ABSOLUTE_URL)
            ];
        }
        return new Response($this->templating->render("IOT/data_monitoring/page.html.twig", $config));
    }

    public function fillPairingConfig(array &$config, Pairing $pairing)
    {
        $start = FormatHelper::datetime($pairing->getStart(), null, true);
        $end = FormatHelper::datetime($pairing->getEnd(), null, true);

        $config["start"] = (new DateTime())->modify('-1 day');
        $config["end"] = new DateTime();
        $config["left_pane"][] = [
            "type" => "sensor",
            "icon" => Sensor::SENSOR_ICONS[$pairing->getSensorWrapper()->getSensor()->getType()->getLabel()],
            "title" => $pairing->getSensorWrapper()->getName(),
            "pairing" => $pairing,
            "header" => true
        ];

        $config["left_pane"][] = [
            "type" => "pairingInfo",
            "pairing" => $pairing,
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
            "hideActions" => $header,
            "entity_info" => [
                "id" => $pack->getId(),
                "type" => IOTService::getEntityCodeFromEntity($pack),
            ],
        ];
    }

    private function fillLocationConfig(array &$config, Emplacement $location, bool $header)
    {
        $config["left_pane"][] = [
            "type" => "entity",
            "icon" => "iot-location",
            "title" => $location->getLabel(),
            "header" => $header,
            "hideActions" => $header,
            "entity_info" => [
                "id" => $location->getId(),
                "type" => IOTService::getEntityCodeFromEntity($location),
            ],
        ];
    }

    private function fillLocationGroupConfig(array &$config, LocationGroup $locationGroup, bool $header)
    {
        $config["left_pane"][] = [
            "type" => "entity",
            "icon" => "iot-location",
            "title" => $locationGroup->getLabel(),
            "header" => $header,
            "hideActions" => $header,
            "entity_info" => [
                "id" => $locationGroup->getId(),
                "type" => IOTService::getEntityCodeFromEntity($locationGroup),
            ],
        ];
    }

    private function fillPreparationConfig(array &$config, Preparation $preparation, bool $header)
    {
        $items = [];
        if ($preparation->getLivraison()) {
            $items[] = [
                "icon" => "iot-delivery-request",
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
            "hideActions" => $header,
            "entity_info" => [
                "id" => $preparation->getId(),
                "type" => IOTService::getEntityCodeFromEntity($preparation),
            ],
        ];
    }

    private function fillDeliveryRequestConfig(array &$config, Demande $deliveryRequest, bool $header)
    {
        $config["left_pane"][] = [
            "type" => "entity",
            "icon" => "iot-delivery-request",
            "title" => $deliveryRequest->getNumero(),
            "header" => $header,
            "hideActions" => $header,
            "entity_info" => [
                "id" => $deliveryRequest->getId(),
                "type" => IOTService::getEntityCodeFromEntity($deliveryRequest),
            ],
        ];
    }

    private function fillCollectOrderConfig(array &$config, OrdreCollecte $collect, bool $header)
    {
        $config["left_pane"][] = [
            "type" => "entity",
            "icon" => "iot-collect-request",
            "title" => $collect->getNumero(),
            "header" => $header,
            "hideActions" => $header,
            "entity_info" => [
                "id" => $collect->getId(),
                "type" => IOTService::getEntityCodeFromEntity($collect),
            ],
        ];
    }

    private function fillCollectRequestConfig(array &$config, Collecte $collect, bool $header)
    {
        $config["left_pane"][] = [
            "type" => "entity",
            "icon" => "iot-collect-request",
            "title" => $collect->getNumero(),
            "header" => $header,
            "hideActions" => $header,
            "entity_info" => [
                "id" => $collect->getId(),
                "type" => IOTService::getEntityCodeFromEntity($collect),
            ],
        ];
    }

    private function fillArticleConfig(array &$config, Article $article, bool $header)
    {
        $config["left_pane"][] = [
            "type" => "entity",
            "icon" => "iot-article",
            "title" => $article->__toString(),
            "header" => $header,
            "hideActions" => $header,
            "entity_info" => [
                "id" => $article->getId(),
                "type" => IOTService::getEntityCodeFromEntity($article),
            ],
        ];
    }

    private function fillVehicleConfig(array &$config, Vehicle $article, bool $header)
    {
        $config["left_pane"][] = [
            "type" => "entity",
            "icon" => "iot-vehicle",
            "title" => $article->__toString(),
            "header" => $header,
            "hideActions" => $header,
            "entity_info" => [
                "id" => $article->getId(),
                "type" => IOTService::getEntityCodeFromEntity($article),
            ],
        ];
    }

    private function fillTimelineConfig(array &$config, PairedEntity $pairedEntity)
    {
        $entityCode = $this->IOTService->getEntityCodeFromEntity($pairedEntity);

        if (!isset($entityCode)) {
            throw new RuntimeException("Unsupported class " . get_class($pairedEntity));
        }

        $config["left_pane"][] = [
            "type" => "timeline",
            "timelineDataPath" => $this->router->generate('get_data_history_timeline_api', [
                "type" => $entityCode,
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

    public function fillEntityConfig(?PairedEntity $entity, array &$config, bool $isHistoric)
    {
        if ($entity instanceof Pack) {
            $this->fillPackConfig($config, $entity, $isHistoric);
        } else if ($entity instanceof Emplacement) {
            $this->fillLocationConfig($config, $entity, $isHistoric);
        } else if ($entity instanceof LocationGroup) {
            $this->fillLocationGroupConfig($config, $entity, $isHistoric);
        } else if ($entity instanceof Preparation) {
            $this->fillPreparationConfig($config, $entity, $isHistoric);
        } else if ($entity instanceof Demande) {
            $this->fillDeliveryRequestConfig($config, $entity, $isHistoric);
        } else if ($entity instanceof OrdreCollecte) {
            $this->fillCollectOrderConfig($config, $entity, $isHistoric);
        } else if ($entity instanceof Collecte) {
            $this->fillCollectRequestConfig($config, $entity, $isHistoric);
        } else if ($entity instanceof Article) {
            $this->fillArticleConfig($config, $entity, $isHistoric);
        } else if ($entity instanceof Vehicle) {
            $this->fillVehicleConfig($config, $entity, $isHistoric);
        } else {
            throw new RuntimeException("Unsupported class " . get_class($entity));
        }

        if ($isHistoric) {
            $this->fillTimelineConfig($config, $entity);
        }
    }

    public function getTimelineData(EntityManagerInterface $entityManager,
                                    RouterInterface $router,
                                    string $type,
                                    string $id,
                                    int $start,
                                    int $count): ?array
    {
        $className = $this->IOTService->getEntityClassFromCode($type);

        if ($className) {
            $repository = $entityManager->getRepository($className);
            $entity = $this->getEntity($entityManager, $type, $id);
            if (method_exists($repository, 'getSensorPairingData')
                && method_exists($repository, 'countSensorPairingData')
                && $entity) {
                $pairingData = $repository->getSensorPairingData($entity, $start, $count);
                $pairingDataCount = $repository->countSensorPairingData($entity);
            }
        }

        if (!isset($pairingData) || !isset($pairingDataCount)) {
            throw new \Exception('Unsupported type');
        }

        return [
            'data' => Stream::from($pairingData)
                ->filterMap(fn(array $dataRow) => $this->getTimelineDataRow($dataRow, $entity, $router))
                ->reverse()
                ->values(),
            'isEnd' => $pairingDataCount <= ($start + $count),
            'isGrouped' => (
                ($entity instanceof Demande)
                || ($entity instanceof Emplacement)
                || ($entity instanceof Article)
                || ($entity instanceof Collecte)
                || ($entity instanceof Pack)
            )
        ];
    }


    public function getEntity(EntityManagerInterface $entityManager,
                              string $type,
                              int $id): ?PairedEntity
    {

        $className = $this->IOTService->getEntityClassFromCode($type);
        $entity = $className
            ? $entityManager->find($className, $id)
            : null;
        if ($entity instanceof Preparation) {
            $entity = $entity->getDemande();
        }
        else if ($entity instanceof OrdreCollecte) {
            $entity = $entity->getDemandeCollecte();
        }
        return $entity;
    }

    public function getTimelineDataRow(array $dataRow,
                                       PairedEntity $entity,
                                       RouterInterface $routerInterface)
    {
        $dateStr = $dataRow['date'] ?? null;
        $type = $dataRow['type'] ?? null;
        $pairingId = $dataRow['pairingId'] ?? null;
        $date = $dateStr
            ? DateTime::createFromFormat('Y-m-d H:i:s', $dateStr)
            : null;

        $subtitlePrefix = [
            'start' => 'Associé le : ',
            'end' => ($date && $date > new DateTime()) ? "Fin le : " : "Dissocié le : ",
        ];

        $row = [
            'titleHref' => $pairingId
                ? $routerInterface->generate('pairing_show', ['pairing' => $pairingId])
                : null,
            'title' => $dataRow['name'] ?? null,
            'datePrefix' => $subtitlePrefix[$type] ?? null,
            'date' => $date ? $date->format('d/m/Y à H:i') : null,
            'active' => ($dataRow['active'] ?? '0') === '1'
        ];

        if ($entity instanceof Demande) {
            $row['group'] = ($type === 'startOrder' || ($type === 'end' && !empty($dataRow['deliveryNumber'])))
                ? $dataRow['deliveryNumber']
                : $dataRow['preparationNumber'];
        } else if ($entity instanceof Emplacement
            || $entity instanceof Pack) {
            $row['group'] = $dataRow['entity'];
        } else if ($entity instanceof Collecte) {
            $row['group'] = $dataRow['orderNumber'];
        }

        if (isset($dataRow['entityType'])
            && isset($dataRow['entityId'])
            && $dataRow['entityType'] !== IOTService::getEntityCodeFromEntity($entity)) {
            $row['groupHref'] = $this->router->generate('show_data_history', [
                'id' => $dataRow['entityId'],
                'type' => $dataRow['entityType']
            ]);
        }

        return $row;
    }
}
