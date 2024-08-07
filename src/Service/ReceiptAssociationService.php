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
use App\Exceptions\FormException;
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
    public FormatService $formatService;

    #[Required]
    public TrackingMovementService $trackingMovementService;

    #[Required]
    public CSVExportService $CSVExportService;

    #[Required]
    public PackService $packService;

    public function __construct(private readonly SettingsService $settingsService) {}

    public function getDataForDatatable(EntityManagerInterface $entityManager,
                                                               $params = null): array {
        $filtreSupRepository = $entityManager->getRepository(FiltreSup::class);
        $receiptAssociationRepository = $entityManager->getRepository(ReceiptAssociation::class);

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

    /**
     * @param string[] $receptions
     * @param Pack[] $packs
     */
    private function persistTrackingMovements(EntityManagerInterface $entityManager,
                                              array                  $receptions,
                                              array                  $packs,
                                              Utilisateur            $user,
                                              DateTime               $now): void {
        $settingRepository = $entityManager->getRepository(Setting::class);
        $locationRepository = $entityManager->getRepository(Emplacement::class);

        $defaultLocationUL = $locationRepository->find($settingRepository->getOneParamByLabel(Setting::BR_ASSOCIATION_DEFAULT_MVT_LOCATION_UL));
        $defaultLocationReception = $locationRepository->find($settingRepository->getOneParamByLabel(Setting::BR_ASSOCIATION_DEFAULT_MVT_LOCATION_RECEPTION_NUM));

        foreach ($packs as $pack) {
            //prise UL
            $pickMvt = $this->trackingMovementService->createTrackingMovement(
                $pack,
                $pack->getLastTracking()?->getEmplacement(),
                $user,
                $now,
                false,
                true,
                TrackingMovement::TYPE_PRISE
            );
            $entityManager->persist($pickMvt);

            //dépose UL
            $dropMvtLU = $this->trackingMovementService->createTrackingMovement(
                $pack,
                $defaultLocationUL,
                $user,
                $now,
                false,
                true,
                TrackingMovement::TYPE_DEPOSE
            );
            $entityManager->persist($dropMvtLU);
        }

        /** @var Reception $reception */
        foreach ($receptions as $reception) {
            //dépose
            $dropMvt = $this->trackingMovementService->createTrackingMovement($reception,
                $defaultLocationReception,
                $user,
                $now,
                false,
                true,
                TrackingMovement::TYPE_DEPOSE);
            $entityManager->persist($dropMvt);
        }
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

    /**
     * @param string[] $receptionNumbers
     * @param string[] $logisticUnitCodes
     * @return ReceiptAssociation[]
     */
    public function persistReceiptAssociation(EntityManagerInterface $entityManager,
                                              array                  $receptionNumbers,
                                              array                  $logisticUnitCodes,
                                              Utilisateur            $user): array
    {
        $now = new DateTime();
        $settingRepository = $entityManager->getRepository(Setting::class);
        $packRepository = $entityManager->getRepository(Pack::class);
        $locationRepository = $entityManager->getRepository(Emplacement::class);

        $logisticUnits = $packRepository->findBy(['code' => $logisticUnitCodes]);
        $logisticUnitsStream = Stream::from($logisticUnits);

        $defaultUlLocationId = $this->settingsService->getValue($entityManager, Setting::BR_ASSOCIATION_DEFAULT_MVT_LOCATION_UL);

        // get not found logistic units
        $notFoundLogisticUnits = Stream::from($logisticUnitCodes)
            ->filter(static fn(string $code) => (
                !$logisticUnitsStream->some(static fn(Pack $pack) => $pack->getCode() === $code)
            ));
        if (!$notFoundLogisticUnits->isEmpty()) {
            $notFoundLogisticUnitsStr = $notFoundLogisticUnits->join(', ');
            if ($notFoundLogisticUnits->count() > 1) {
                throw new FormException("Les unités logistiques {$notFoundLogisticUnitsStr} n'existent pas, impossible d'enregistrer");
            }
            else {
                throw new FormException("L'unité logistique {$notFoundLogisticUnitsStr} n'existe pas, impossible d'enregistrer");
            }
        }

        $receiptAssociations = [];
        foreach ($receptionNumbers as $receptionNumber) {
            $receiptAssociation = (new ReceiptAssociation())
                ->setReceptionNumber($receptionNumber)
                ->setUser($user)
                ->setCreationDate($now);

            if (!empty($logisticUnits)) {
                $receiptAssociation->setLogisticUnits($logisticUnits);
            }

            $entityManager->persist($receiptAssociation);
            $receiptAssociations[] = $receiptAssociation;
        }

        if(!empty($logisticUnits)){
            $defaultUlLocation = $defaultUlLocationId ? $locationRepository->find($defaultUlLocationId) : null;
            foreach ($logisticUnits as $logisticUnit) {
                $message = $this->buildCustomLogisticUnitHistoryRecord($receptionNumbers);
                $this->packService->persistLogisticUnitHistoryRecord($entityManager, $logisticUnit, $message, $now, $user, "Association BR", $defaultUlLocation);
            }
        }

        if ($defaultUlLocationId
            && $settingRepository->getOneParamByLabel(Setting::BR_ASSOCIATION_DEFAULT_MVT_LOCATION_RECEPTION_NUM)) {
            $this->persistTrackingMovements($entityManager, $receptionNumbers, $logisticUnits, $user, $now);
        }

        return $receiptAssociations;
    }

    public function buildCustomLogisticUnitHistoryRecord(array $receptionNumbers): string {
        $receptionNumbersList = Stream::from($receptionNumbers)->join(', ');
        $message = "Associé à : $receptionNumbersList";

        return $message;
    }
}
