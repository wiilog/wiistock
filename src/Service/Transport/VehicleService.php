<?php


namespace App\Service\Transport;

use App\Entity\Emplacement;
use App\Entity\Transport\Vehicle;
use App\Helper\FormatHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment as Twig_Environment;
use WiiCommon\Helper\Stream;


class VehicleService {

    #[Required]
    public EntityManagerInterface $manager;

    #[Required]
    public Twig_Environment $templating;

    public function getDataForDatatable(InputBag $params): array {
        $queryResult = $this->manager->getRepository(Vehicle::class)->findByParams($params);

        $vehicles = $queryResult['data'];

        $rows = [];
        foreach ($vehicles as $vehicle) {
            $rows[] = $this->dataRowVehicle($vehicle);
        }

        return [
            'data' => $rows,
            'recordsTotal' => $queryResult['total'],
            'recordsFiltered' => $queryResult['count'],
        ];
    }

    public function dataRowVehicle(Vehicle $vehicle): array {
        return [
            'registration' => $vehicle->getRegistration(),
            'deliverer' => FormatHelper::user($vehicle->getDeliverer()),
            'locations' => Stream::from($vehicle->getLocations())->map(fn(Emplacement $location) => $location->getLabel())->join(', '),
            'actions' => $this->templating->render('vehicle/actions.html.twig', [
                'id' => $vehicle->getId(),
            ]),
        ];
    }
}
