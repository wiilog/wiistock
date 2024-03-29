<?php

namespace App\Service;

use App\Entity\Zone;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment as Twig_Environment;

class ZoneService {

    #[Required]
    public EntityManagerInterface $manager;

    #[Required]
    public Twig_Environment $template;

    #[Required]
    public Security $security;

    #[Required]
    public FormatService $formatService;

    public function getDataForDatatable($params = null): array {
        $zoneRepository = $this->manager->getRepository(Zone::class);
        $queryResult = $zoneRepository->findByParamsAndFilters($params);

        $zones = $queryResult['data'];

        $rows = [];
        foreach ($zones as $zone) {
            $rows[] = $this->dataRowZone($zone);
        }

        return [
            'data' => $rows,
            'recordsFiltered' => $queryResult['count'],
            'recordsTotal' => $queryResult['total'],
        ];
    }

    public function dataRowZone(Zone $zone): array {
        return [
            "actions" => $this->template->render('zone/actions.html.twig', [
                "zone" => $zone,
            ]),
            "name" => $zone->getName(),
            "description" => $zone->getDescription(),
            "active" => $this->formatService->bool($zone->isActive()),
        ];
    }
}
