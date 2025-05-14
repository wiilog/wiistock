<?php


namespace App\Service;

use App\Entity\DeliveryStationLine;
use App\Entity\Emplacement;
use App\Entity\Type\Type;
use App\Entity\Utilisateur;
use App\Entity\VisibilityGroup;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment as Twig_Environment;
use WiiCommon\Helper\Stream;

class DeliveryStationLineService {

    #[Required]
    public FormatService $formatService;

    #[Required]
    public Twig_Environment $templating;

    #[Required]
    public RouterInterface $router;

    public function getDeliveryStationLineForDatatable(EntityManagerInterface $manager): array
    {
        $deliveryStationLineRepository = $manager->getRepository(DeliveryStationLine::class);
        $deliveryStationLines = $deliveryStationLineRepository->findAll();


        $rows = [];
        foreach ($deliveryStationLines as $deliveryStationLine) {
            $rows[] = $this->dataRowDeliveryStationLine($deliveryStationLine);
        }

        return [
            'data' => $rows,
        ];
    }

    public function createOrUpdateDeliveryStationLine(EntityManagerInterface $manager, InputBag $data, DeliveryStationLine|null $deliveryStationLine): DeliveryStationLine
    {
        $deliveryStationLine = $deliveryStationLine ?: (new DeliveryStationLine());

        $typeRepository = $manager->getRepository(Type::class);
        $visibilityGroupRepository = $manager->getRepository(VisibilityGroup::class);
        $locationRepository = $manager->getRepository(Emplacement::class);
        $userRepository = $manager->getRepository(Utilisateur::class);

        $deliveryType = $typeRepository->find($data->get('deliveryType'));
        $visibilityGroup = $visibilityGroupRepository->find($data->get('visibilityGroup'));
        $destinationLocation = $locationRepository->find($data->get('destinationLocation'));
        $receivers = $data->has('receivers') ? $userRepository->findBy(['id' => explode(',', $data->get('receivers'))]) : null;
        $filterFields = $data->has('filterFields') ? explode(',', $data->get('filterFields')) : null;

        $token = $deliveryStationLine->getToken() ?: bin2hex(random_bytes(30));

        $deliveryStationLine
            ->setWelcomeMessage($data->get('welcomeMessage'))
            ->setDeliveryType($deliveryType)
            ->setVisibilityGroup($visibilityGroup)
            ->setDestinationLocation($destinationLocation)
            ->setReceivers($receivers)
            ->setFilters($filterFields)
            ->setToken($token);

        return $deliveryStationLine;
    }

    public function dataRowDeliveryStationLine(DeliveryStationLine $deliveryStationLine): array
    {
        return [
            'id' => $deliveryStationLine->getId(),
            'deliveryType' => $this->formatService->type($deliveryStationLine->getDeliveryType()),
            'visibilityGroup' => $this->formatService->visibilityGroup($deliveryStationLine->getVisibilityGroup()),
            'destination' => $this->formatService->location($deliveryStationLine->getDestinationLocation()),
            'receivers' => Stream::from($deliveryStationLine->getReceivers())
                ->map(fn(Utilisateur $receiver) => $this->formatService->user($receiver, "", true))
                ->join(', '),
            'generatedExternalLink' => "<div><a target='_blank' href='{$this->router->generate('delivery_station_form', ['token' => $deliveryStationLine->getToken()])}'><i class='fas fa-external-link-alt mr-2'></i> Lien externe</a></div>",
            'actions' => $this->templating->render('utils/action-buttons/dropdown.html.twig', [
                'actions' => [
                    [
                        'title' => 'Modifier',
                        'icon' => 'fa fa-edit',
                        'attributes' => [
                            'onclick' => "openModalEditDeliveryStationLine($('#modalEditDeliveryStationLine'), ".$deliveryStationLine->getId().")",
                        ]
                    ],
                    [
                        'title' => 'Supprimer',
                        'class' => 'delivery-station-line-delete',
                        'icon' => 'wii-icon wii-icon-trash',
                        'attributes' => [
                            "data-id" => $deliveryStationLine->getId(),
                            "onclick" => "deleteDeliveryStationLine($(this))"
                        ]
                    ],
                ],
            ]),
        ];
    }
}
