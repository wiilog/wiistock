<?php

namespace App\Service;

use App\Entity\IOT\Pairing;
use App\Helper\FormatHelper;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

class DataMonitoringService {

    /** @Required */
    public Environment $templating;

    public function render($config): Response {
        $config["left_pane"] = $this->generatePane($config["entities"]);
        $config["items"] = $this->generateContent();

        unset($config["entities"]);

        return new Response($this->templating->render("iot/data_monitoring/page.html.twig", $config));
    }

    public function generatePane(array $entities): array {
        $pane = [];
        foreach ($entities as $entity) {
            if($entity instanceof Pairing) {
                $start = FormatHelper::datetime($entity->getStart());
                $end = FormatHelper::datetime($entity->getEnd());

                $pane[] = [
                    "type" => "sensor",
                    "icon" => "traca",
                    "title" => $entity->getSensorWrapper()->getName(),
                    "subtitle" => ["Associé le : $start", "Fin le : $end"],
                    "color" => "#2A72B0",
                ];
            }
        }

        return $pane;
    }

    public function generateContent(): array {
        //TODO: generate chart or map data
        return [
            ["type" => "map"],
            ["type" => "chart"],
        ];
    }

}
