<?php


namespace App\Service;

use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\ReceiptAssociation;
use App\Entity\Reception;
use App\Entity\Setting;
use App\Entity\Tracking\Pack;
use App\Entity\Tracking\TrackingMovement;
use App\Entity\Utilisateur;
use App\Exceptions\FormException;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\RouterInterface;
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
    public TrackingMovementService $trackingMovementService;

    #[Required]
    public CSVExportService $CSVExportService;

    #[Required]
    public PackService $packService;

    #[Required]
    public FormatService $formatService;

    #[Required]
    public SettingsService $settingsService;

    #[Required]
    public TranslationService $translationService;

    public function getDataForDatatable(EntityManagerInterface $entityManager,
                                                               $params = null): array
    {
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

    public function dataRowReceiptAssociation(array $receiptAssocation, Utilisateur $user): array
    {
        return [
            'id' => $receiptAssocation["id"],
            'creationDate' => $this->formatService->datetime($receiptAssocation["creationDate"], "", false, $user),
            'logisticUnit' => $receiptAssocation["logisticUnit"] ?? "",
            'lastActionDate' => $this->formatService->datetime($receiptAssocation["lastActionDate"]),
            'lastActionLocation' => $receiptAssocation["lastActionLocation"] ?? "",
            'receptionNumber' => $receiptAssocation["receptionNumber"],
            'user' => $receiptAssocation["user"],
            'Actions' => $this->templating->render('receipt_association/datatableRowActions.html.twig', [
                'receipt_association' => $receiptAssocation,
            ]),
        ];
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
            } else {
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

        if (!empty($logisticUnits)) {
            $defaultUlLocation = $defaultUlLocationId ? $locationRepository->find($defaultUlLocationId) : null;
            foreach ($logisticUnits as $logisticUnit) {
                $this->packService->persistLogisticUnitHistoryRecord($entityManager, $logisticUnit, [
                    "message" => $this->formatService->list([
                        "Associé à" => Stream::from($receptionNumbers)->join(', '),
                    ]),
                    "historyDate" => $now,
                    "user" => $user,
                    "type" => "Association BR",
                    "location" => $defaultUlLocation,
                ]);
            }
        }

        if ($defaultUlLocationId
            && $settingRepository->getOneParamByLabel(Setting::BR_ASSOCIATION_DEFAULT_MVT_LOCATION_RECEPTION_NUM)) {
            $this->persistTrackingMovements($entityManager, $receptionNumbers, $logisticUnits, $user, $now);
        }

        return $receiptAssociations;
    }

    /**
     * @param string[] $receptions
     * @param Pack[] $packs
     */
    private function persistTrackingMovements(EntityManagerInterface $entityManager,
                                              array                  $receptions,
                                              array                  $packs,
                                              Utilisateur            $user,
                                              DateTime               $now): void
    {
        $settingRepository = $entityManager->getRepository(Setting::class);
        $locationRepository = $entityManager->getRepository(Emplacement::class);

        $defaultLocationUL = $locationRepository->find($settingRepository->getOneParamByLabel(Setting::BR_ASSOCIATION_DEFAULT_MVT_LOCATION_UL));
        $defaultLocationReception = $locationRepository->find($settingRepository->getOneParamByLabel(Setting::BR_ASSOCIATION_DEFAULT_MVT_LOCATION_RECEPTION_NUM));

        foreach ($packs as $pack) {
            //prise UL
            $pickMvt = $this->trackingMovementService->createTrackingMovement(
                $pack,
                $pack->getLastAction()?->getEmplacement(),
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

        foreach ($receptions as $reception) {
            //dépose
            $dropMvt = $this->trackingMovementService->createTrackingMovement(
                $reception,
                $defaultLocationReception,
                $user,
                $now,
                false,
                true,
                TrackingMovement::TYPE_DEPOSE);
            $entityManager->persist($dropMvt);
        }
    }

    public function getCsvHeader(): array {
        $translationService = $this->translationService;
        return  [
            $translationService->translate('Traçabilité', 'Général', 'Date',false),
            $translationService->translate('Traçabilité', 'Général', 'Unité logistique',false),
            $translationService->translate('Traçabilité', 'Association BR', 'Réception',false),
            $translationService->translate('Traçabilité', 'Général', 'Utilisateur',false),
            $translationService->translate('Traçabilité', 'Général', 'Date dernier mouvement',false),
            $translationService->translate('Traçabilité', 'Général', 'Dernier emplacement',false),
        ];
    }

    public function getExportReceiptAssociationFunction(DateTime               $dateTimeMin,
                                                        DateTime               $dateTimeMax,
                                                        EntityManagerInterface $entityManager): callable {
        $receiptAssociationRepository = $entityManager->getRepository(ReceiptAssociation::class);
        $receiptAssociations = $receiptAssociationRepository->getByDates($dateTimeMin, $dateTimeMax, $this->userService->getUser()?->getDateFormat());

        return function ($handle) use ($entityManager, $receiptAssociations) {
            foreach ($receiptAssociations as $receiptAssociation) {
                $this->putReceiptAssociationLine($handle, $receiptAssociation);
            }
        };
    }

    public function putReceiptAssociationLine($output, array $receiptAssociation): void {
        $row = [
            $receiptAssociation['creationDate'],
            $receiptAssociation['logisticUnit'] ?? null,
            $receiptAssociation['receptionNumber'],
            $receiptAssociation['user'],
            $receiptAssociation['lastActionDate'] ?? null,
            $receiptAssociation['lastActionLocation'] ?? null,
        ];

        $this->CSVExportService->putLine($output, $row);
    }

}
