<?php

namespace App\Service;


use App\Entity\Reserve;
use App\Entity\ReserveType;
use App\Entity\TruckArrival;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment;
use WiiCommon\Helper\Stream;

class ReserveService
{

    #[Required]
    public FieldModesService $fieldModesService;

    #[Required]
    public FormatService $formatService;

    #[Required]
    public UserService $userService;

    #[Required]
    public Environment $templating;

    #[Required]
    public RouterInterface $router;

    #[Required]
    public MailerService $mailerService;

    #[Required]
    public TranslationService $translation;

    public function getDataForDatatable(EntityManagerInterface $entityManager,
                                        Request                $request): array {
        $reserveRepository = $entityManager->getRepository(Reserve::class);
        $queryResult = $reserveRepository->findByParamsAndFilters($request->query, Reserve::KIND_LINE);

        $reserves = Stream::from($queryResult['data'])
            ->map(function (Reserve $reserve) {
                return $this->dataRowQualityReserve($reserve);
            })
            ->toArray();

        return [
            'data' => $reserves,
            'recordsFiltered' => $queryResult['count'] ?? null,
            'recordsTotal' => $queryResult['total'] ?? null,
        ];
    }

    public function dataRowQualityReserve(Reserve $reserve): array {
        return [
            'actions' => $this->templating->render('utils/action-buttons/dropdown.html.twig', [
                'actions' => [
                    [
                        'title' => 'Modifier',
                        'icon' => 'fa fa-eye',
                        "actionOnClick" => true,
                        'attributes' => [
                            'onclick' => "openModalQualityReserveContent($('#modalReserveQuality'), ".$reserve->getId().")",
                        ]
                    ],
                    [
                        'title' => 'Supprimer',
                        'icon' => 'wii-icon wii-icon-trash',
                        'class' => 'truck-arrival-lines-delete',
                        'attributes' => [
                            "data-id" => $reserve->getId(),
                            "onclick" => "deleteTruckArrivalLineReserve($(this))"
                        ]
                    ],
                ],
            ]),
            'id' => $reserve->getId(),
            'reserveLineNumber' => $reserve->getLine()->getNumber(),
            'reserveType' => $this->formatService->reserveType($reserve->getReserveType()),
            'attachment' => $this->templating->render('attachment/attachment.html.twig', [
                'isNew' => false,
                'editAttachments'=> false,
                'noLabel' => true,
                'attachments' => array_merge($reserve->getAttachments()->toArray(), $reserve->getLine()->getAttachments()->toArray()),
            ]),
            'comment' => $reserve->getComment(),
        ];
    }

    public function sendTruckArrivalMail(EntityManagerInterface $entityManager,
                                         TruckArrival           $truckArrival,
                                         ReserveType            $reserveType,
                                         array                  $reserves,
                                         array                  $attachments): void {
        $this->mailerService->sendMail(
            $entityManager,
            $this->translation->translate('Général', null, 'Header', 'Wiilog', false) . MailerService::OBJECT_SEPARATOR . 'Réserve arrivage camion',
            $this->templating->render('mails/contents/mailTruckArrival.html.twig', [
                "truckArrival" => $truckArrival,
                "reserves" => $reserves,
                "urlSuffix" => $this->router->generate("truck_arrival_show", [
                    "id" => $truckArrival->getId(),
                ]),
            ]),
            $reserveType->getNotifiedUsers()->toArray(),
            $attachments
        );
    }
}
