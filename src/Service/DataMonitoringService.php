<?php

namespace App\Service;

use App\Entity\Article;
use App\Entity\Emplacement;
use App\Entity\IOT\Pairing;
use App\Entity\IOT\Sensor;
use App\Entity\LocationGroup;
use App\Entity\OrdreCollecte;
use App\Entity\Pack;
use App\Entity\Preparation;
use App\Helper\FormatHelper;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;

class DataMonitoringService
{

    /** @Required */
    public Environment $templating;

    /** @Required */
    public RouterInterface $router;

    public function render($config): Response
    {
        foreach ($config["entities"] as $entity) {
            if ($entity instanceof Pairing) {
                $this->createPairing($config, $entity);
            } else if ($entity instanceof Pack) {
                $this->createPack($config, $entity);
            } else if ($entity instanceof Emplacement) {
                $this->createLocation($config, $entity);
            } else if ($entity instanceof LocationGroup) {
                $this->createLocationGroup($config, $entity);
            } else if ($entity instanceof Preparation) {
                $this->createPreparation($config, $entity);
            } else if ($entity instanceof OrdreCollecte) {
                $this->createCollectOrder($config, $entity);
            } else if ($entity instanceof Article) {
                $this->createArticle($config, $entity);
            } else {
                throw new \RuntimeException("Unsupported class " . get_class($entity));
            }
        }

        unset($config["entities"]);
        return new Response($this->templating->render("iot/data_monitoring/page.html.twig", $config));
    }

    public function createPairing(array &$config, Pairing $pairing)
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

        $type = $pairing->getSensorWrapper()->getSensor()->getType();
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

    public function createPack(array &$config, Pack $pack)
    {
        $config["left_pane"][] = [
            "type" => "entity",
            "icon" => "iot-pack",
            "title" => $pack->getCode(),
            "color" => "#F5B642",
            "pack" => $pack,
        ];
    }

    public function createLocation(array &$config, Emplacement $location)
    {
        $config["left_pane"][] = [
            "type" => "entity",
            "icon" => "iot-location",
            "title" => $location->getLabel(),
            "color" => "#34C9EB",
        ];
    }

    public function createLocationGroup(array &$config, LocationGroup $location)
    {
        $config["left_pane"][] = [
            "type" => "entity",
            "icon" => "iot-location",
            "title" => $location->getName(),
            "color" => "#34C9EB",
        ];
    }

    public function createPreparation(array &$config, Preparation $preparation)
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
            "type" => "entity",
            "items" => $items
        ];
    }

    public function createCollectOrder(array &$config, OrdreCollecte $collect)
    {
        $config["left_pane"][] = [
            "type" => "entity",
            "icon" => "iot-collect",
            "title" => $collect->getNumero(),
            "color" => "#F5BC14",
        ];
    }

    public function createArticle(array &$config, Article $article)
    {
        $config["left_pane"][] = [
            "type" => "entity",
            "icon" => "iot-article",
            "title" => $article->getLabel(),
            "color" => "#B92BED",
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

}
