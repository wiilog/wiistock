<?php


namespace App\Service;

use App\Entity\Emplacement;
use App\Entity\FiltreSup;

use App\Entity\Pack;
use App\Entity\ReceiptAssociation;
use App\Entity\Reception;
use App\Entity\Setting;
use App\Entity\TrackingMovement;
use App\Entity\Utilisateur;
use DateTime;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\RouterInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment as Twig_Environment;
use WiiCommon\Helper\Stream;

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

    #[Required]
    public CSVExportService $CSVExportService;

    public function getDataForDatatable($params = null): array {
        $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);
        $receiptAssociationRepository = $this->entityManager->getRepository(ReceiptAssociation::class);

        $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_RECEIPT_ASSOCIATION, $this->security->getUser());
        $queryResult = $receiptAssociationRepository->findByParamsAndFilters($params, $filters);

        $receiptAssocations = $queryResult['data'];
        $user = $this->userService->getUser();

        $rows = [];
        foreach ($receiptAssocations as $receiptAssocation) {
            $rows[] = $this->dataRowReceiptAssociation($receiptAssocation, $user);
        }

        return [
            'data' => $rows,
            'recordsFiltered' => $queryResult['count'],
            'recordsTotal' => $queryResult['total'],
        ];
    }

    public function dataRowReceiptAssociation(array $receiptAssocation, Utilisateur $user): array {
        return [
            'id' => $receiptAssocation["id"],
            'creationDate' => $this->formatService->datetime($receiptAssocation["creationDate"], "", false, $user),
            'logisticUnit' => $receiptAssocation["logisticUnit"] ?? "",
            'lastTrackingDate' => $this->formatService->datetime($receiptAssocation["lastTrackingDate"]),
            'lastTrackingLocation' => $receiptAssocation["lastTrackingLocation"] ?? "",
            'receptionNumber' => $receiptAssocation["receptionNumber"],
            'user' => $receiptAssocation["user"],
            'Actions' => $this->templating->render('receipt_association/datatableRowActions.html.twig', [
                'receipt_association' => $receiptAssocation,
            ]),
        ];
    }

    public function createMovements(array $receptions, array $packs = []): void {
        $settingRepository = $this->entityManager->getRepository(Setting::class);

        $now = new DateTime('now');
        $defaultLocationUL = $this->entityManager->getRepository(Emplacement::class)->find($settingRepository->getOneParamByLabel(Setting::BR_ASSOCIATION_DEFAULT_MVT_LOCATION_UL));
        $defaultLocationReception = $this->entityManager->getRepository(Emplacement::class)->find($settingRepository->getOneParamByLabel(Setting::BR_ASSOCIATION_DEFAULT_MVT_LOCATION_RECEPTION_NUM));

        /** @var Pack $pack */
        foreach ($packs as $pack) {
            //prise UL
            $pickMvt = $this->trackingMovementService->createTrackingMovement(
                $pack,
                $pack->getLastTracking()?->getEmplacement(),
                $this->userService->getUser(),
                $now,
                false,
                true,
                TrackingMovement::TYPE_PRISE
            );
            $this->entityManager->persist($pickMvt);

            //dépose UL
            $dropMvtLU = $this->trackingMovementService->createTrackingMovement(
                $pack,
                $defaultLocationUL,
                $this->userService->getUser(),
                $now,
                false,
                true,
                TrackingMovement::TYPE_DEPOSE
            );
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

    public function receiptAssociationPutLine($output ,array $receiptAssociation): void {
        $row = [
            $receiptAssociation['creationDate'],
            $receiptAssociation['logisticUnit'],
            $receiptAssociation['receptionNumber'],
            $receiptAssociation['user'],
            $receiptAssociation['lastTrackingDate'],
            $receiptAssociation['lastTrackingLocation'],
        ];

        $this->CSVExportService->putLine($output, $row);
    }
}
