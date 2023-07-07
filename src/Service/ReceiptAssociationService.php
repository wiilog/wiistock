<?php


namespace App\Service;

use App\Entity\Emplacement;
use App\Entity\FiltreSup;

use App\Entity\Pack;
use App\Entity\ReceiptAssociation;
use App\Entity\Reception;
use App\Entity\Setting;
use App\Entity\TrackingMovement;
use DateTime;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Routing\RouterInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment as Twig_Environment;

class ReceiptAssociationService
{
    #[Required]
    public Twig_Environment $templating;

    #[Required]
    public RouterInterface $router;

    #[Required]
    public UserService $userService;

    #[Required]
    public Security $security;

    #[Required]
    public EntityManagerInterface $entityManager;

    #[Required]
    public FormatService $formatService;

    #[Required]
    public TrackingMovementService $trackingMovementService;


    public function getDataForDatatable($params = null)
    {
        $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);
        $receiptAssociationRepository = $this->entityManager->getRepository(ReceiptAssociation::class);

        $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_RECEIPT_ASSOCIATION, $this->security->getUser());
        $queryResult = $receiptAssociationRepository->findByParamsAndFilters($params, $filters);

        $receiptAssocations = $queryResult['data'];

        $rows = [];
        foreach ($receiptAssocations as $receiptAssocation) {
            $rows[] = $this->dataRowReceiptAssociation($receiptAssocation);
        }

        return [
            'data' => $rows,
            'recordsFiltered' => $queryResult['count'],
            'recordsTotal' => $queryResult['total'],
        ];
    }

    public function dataRowReceiptAssociation(ReceiptAssociation $receiptAssocation)
    {
        return [
            'id' => $receiptAssocation->getId(),
            'creationDate' => $this->formatService->datetime($receiptAssocation->getCreationDate(), "", false, $this->security->getUser()),
            'packCode' => $receiptAssocation->getPackCode() ?? '',
            'receptionNumber' => $receiptAssocation->getReceptionNumber() ?? '',
            'user' => $this->formatService->user($receiptAssocation->getUser()),
            'Actions' => $this->templating->render('receipt_association/datatableRowActions.html.twig', [
                'receipt_association' => $receiptAssocation,
            ])
        ];
    }

    public function createMovements(array $receptions, array $packs = [])
    {
        $settingRepository = $this->entityManager->getRepository(Setting::class);

        $now = new DateTime('now');
        $defaultLocationUL = $this->entityManager->getRepository(Emplacement::class)->find($settingRepository->getOneParamByLabel(Setting::BR_ASSOCIATION_DEFAULT_MVT_LOCATION_UL));
        $defaultLocationReception = $this->entityManager->getRepository(Emplacement::class)->find($settingRepository->getOneParamByLabel(Setting::BR_ASSOCIATION_DEFAULT_MVT_LOCATION_RECEPTION_NUM));

        /** @var Pack $pack */
        foreach ($packs as $pack) {
            //prise UL
            $pickMvt = $this->trackingMovementService->createTrackingMovement($pack,
                $pack->getLastTracking()->getEmplacement(),
                $this->userService->getUser(),
                $now,
                false,
                true,
                TrackingMovement::TYPE_PRISE);
            $this->entityManager->persist($pickMvt);

            //dépose UL
            $dropMvtLU = $this->trackingMovementService->createTrackingMovement($pack,
                $defaultLocationUL,
                $this->userService->getUser(),
                $now,
                false,
                true,
                TrackingMovement::TYPE_DEPOSE);
            $this->entityManager->persist($dropMvtLU);
        }

        /** @var Reception $reception */
        foreach ($receptions as $reception) {
            //dépose
            $dropMvt = $this->trackingMovementService->createTrackingMovement($reception,
                $defaultLocationReception,
                $this->userService->getUser(),
                $now,
                false,
                true,
                TrackingMovement::TYPE_DEPOSE);
            $this->entityManager->persist($dropMvt);
        }
        $this->entityManager->flush();
    }
}
