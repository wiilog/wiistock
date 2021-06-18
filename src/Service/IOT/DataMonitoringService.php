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
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;

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
            $this->fillEntityConfig($entity, $config, false);

            $this->fillPairingConfig($config, $pairing);
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
        $start = FormatHelper::datetime($pairing->getStart());
        $end = FormatHelper::datetime($pairing->getEnd());

        $config["start"] = $pairing->getStart();
        $config["end"] = $pairing->getEnd();
        $config["left_pane"][] = [
            "type" => "sensor",
            "icon" => "wifi",
            "title" => $pairing->getSensorWrapper()->getName(),
            "subtitle" => [
                "Associé le : $start",
                $end ? "Fin le : <span class=\"pairing-end-date-{$pairing->getId()}\">$end</span>" : null,
            ],
            "color" => "#2A72B0",
            "pairing" => $pairing,
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

    private function fillPackConfig(array &$config, Pack $pack, bool $isTimeline)
    {
        $config["left_pane"][] = [
            "type" => $isTimeline ? self::TIMELINE : "entity",
            "icon" => "iot-pack",
            "title" => $pack->getCode(),
            "color" => "#F5B642",
            "pack" => $pack,
            "activeAssociation" => $isTimeline ? $pack->getActivePairing() : null
        ];
    }

    private function fillLocationConfig(array &$config, Emplacement $location, bool $isTimeline)
    {
        $config["left_pane"][] = [
            "type" => $isTimeline ? self::TIMELINE : "entity",
            "icon" => "iot-location",
            "title" => $location->getLabel(),
            "color" => "#34C9EB",
            "activeAssociation" => $isTimeline ? $location->getActivePairing() : null
        ];
    }

    private function fillLocationGroupConfig(array &$config, LocationGroup $location, bool $isTimeline)
    {
        $config["left_pane"][] = [
            "type" => $isTimeline ? self::TIMELINE : "entity",
            "icon" => "iot-location",
            "title" => $location->getName(),
            "color" => "#34C9EB",
            "activeAssociation" => $isTimeline ? $location->getActivePairing() : null
        ];
    }

    private function fillPreparationConfig(array &$config, Preparation $preparation, bool $isTimeline)
    {
        $items = [];
        if($preparation->getLivraison()) {
            $items[] = [
                "icon" => "iot-delivery",
                "title" => $preparation->getLivraison()->getNumero(),
                "color" => "#F5E342",
            ];
        }

        $items[] = [
            "icon" => "iot-preparation",
            "title" => $preparation->getNumero(),
            "color" => "#135FC2",
        ];

        $config["left_pane"][] = [
            "type" => $isTimeline ? self::TIMELINE : "entity",
            "items" => $items,
            "activeAssociation" => $isTimeline ? $preparation->getActivePairing() : null
        ];
    }

    private function fillCollectOrderConfig(array &$config, OrdreCollecte $collect, bool $isTimeline)
    {
        $config["left_pane"][] = [
            "type" => $isTimeline ? self::TIMELINE : "entity",
            "icon" => "iot-collect",
            "title" => $collect->getNumero(),
            "color" => "#F5BC14",
            "activeAssociation" => $isTimeline ? $collect->getActivePairing() : null
        ];
    }

    private function fillArticleConfig(array &$config, Article $article, bool $isTimeline)
    {
        $config["left_pane"][] = [
            "type" => $isTimeline ? self::TIMELINE : "entity",
            "icon" => "iot-article",
            "title" => $article->getLabel(),
            "color" => "#B92BED",
            "activeAssociation" => $isTimeline ? $article->getActivePairing() : null
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

    public function fillEntityConfig(?PairedEntity $entity, array &$config, bool $isTimeline) {
        if ($entity instanceof Pack) {
            $this->fillPackConfig($config, $entity, $isTimeline);
        } else if ($entity instanceof Emplacement) {
            $this->fillLocationConfig($config, $entity, $isTimeline);
        } else if ($entity instanceof LocationGroup) {
            $this->fillLocationGroupConfig($config, $entity, $isTimeline);
        } else if ($entity instanceof Preparation) {
            $this->fillPreparationConfig($config, $entity, $isTimeline);
        } else if ($entity instanceof OrdreCollecte) {
            $this->fillCollectOrderConfig($config, $entity, $isTimeline);
        } else if ($entity instanceof Article) {
            $this->fillArticleConfig($config, $entity, $isTimeline);
        } else {
            throw new RuntimeException("Unsupported class " . get_class($entity));
        }
    }

}
