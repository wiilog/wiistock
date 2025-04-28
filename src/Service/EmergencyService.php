<?php

namespace App\Service;


use App\Entity\CategoryType;
use App\Entity\Emergency\Emergency;
use App\Entity\Emergency\EmergencyTriggerEnum;
use App\Entity\Emergency\EndEmergencyCriteriaEnum;
use App\Entity\Emergency\StockEmergency;
use App\Entity\Emergency\TrackingEmergency;
use App\Entity\Fields\FixedFieldByType;
use App\Entity\Fields\FixedFieldEnum;
use App\Entity\Fields\FixedFieldStandard;
use App\Entity\Fournisseur;
use App\Entity\ReferenceArticle;
use App\Entity\Transporteur;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Exceptions\FormException;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;
use WiiCommon\Helper\Stream;

class EmergencyService
{

    public function __construct(private AttachmentService $attachmentService,
                                private FixedFieldService $fieldsParamService,
                                private FormatService     $formatService) {}

    public function getEmergencyConfig(EntityManagerInterface $entityManager,
                                       Emergency              $emergency = null): array {
        $fixedFieldByTypeRepository = $entityManager->getRepository(FixedFieldByType::class);
        $typeRepository = $entityManager->getRepository(Type::class);

        $trackingEmergencyFieldsParam = $fixedFieldByTypeRepository->getByEntity(FixedFieldStandard::ENTITY_CODE_TRACKING_EMERGENCY, [
            FixedFieldByType::ATTRIBUTE_REQUIRED_CREATE,
            FixedFieldByType::ATTRIBUTE_DISPLAYED_CREATE,
            FixedFieldByType::ATTRIBUTE_REQUIRED_EDIT,
            FixedFieldByType::ATTRIBUTE_DISPLAYED_EDIT,
        ]);
        $stockEmergencyFieldsParam = $fixedFieldByTypeRepository->getByEntity(FixedFieldStandard::ENTITY_CODE_STOCK_EMERGENCY, [
            FixedFieldByType::ATTRIBUTE_REQUIRED_CREATE,
            FixedFieldByType::ATTRIBUTE_DISPLAYED_CREATE,
            FixedFieldByType::ATTRIBUTE_REQUIRED_EDIT,
            FixedFieldByType::ATTRIBUTE_DISPLAYED_EDIT,
        ]);
        $fieldsParam = Stream::from($trackingEmergencyFieldsParam)
            ->map(static function(?array $fieldsParamValue, string $fieldsParamKey) use (&$stockEmergencyFieldsParam) {
                if(isset($stockEmergencyFieldsParam[$fieldsParamKey]) && $fieldsParamValue) {
                    $returnValue = Stream::from($fieldsParamValue)
                        ->map(static function(?string $value, string $key) use ($stockEmergencyFieldsParam, $fieldsParamKey) {
                            if(isset($stockEmergencyFieldsParam[$fieldsParamKey][$key]) && $value) {
                                return $stockEmergencyFieldsParam[$fieldsParamKey][$key] ." $value";
                            } else {
                                return $value;
                            }
                        })
                        ->toArray();
                    unset($stockEmergencyFieldsParam[$fieldsParamKey]);
                    return $returnValue;
                } else {
                    return $fieldsParamValue;
                }
            })
            ->concat($stockEmergencyFieldsParam)
            ->toArray();

        $emergencyTypes = $typeRepository->findByCategoryLabels([CategoryType::TRACKING_EMERGENCY, CategoryType::STOCK_EMERGENCY], null, [
            'onlyActive' => true,
        ]);

        $types = Stream::from($emergencyTypes)
            ->map(static fn(Type $type) => [
                'label' => $type->getLabel(),
                'value' => $type->getId(),
                'selected' => !$emergency && $type->isDefault() || $type->getId() === $emergency?->getType()->getId(),
                'category-type' => $type->getCategory()->getLabel(),
            ])
            ->toArray();

        return [
            'types' => $types,
            'fieldsParam' => $fieldsParam,
            'emergency' => $emergency ?? null,
        ];
    }

    public function updateEmergency(EntityManagerInterface $entityManager,
                                    Emergency              $emergency,
                                    Request                $request): Emergency {
        $supplierRepository = $entityManager->getRepository(Fournisseur::class);
        $typeRepository = $entityManager->getRepository(Type::class);

        $type = $typeRepository->find($request->request->get(FixedFieldEnum::type->name));

        $isStockEmergency = $type->getCategory()->getLabel() === CategoryType::STOCK_EMERGENCY;

        $data = $this->fieldsParamService->checkForErrors(
            $entityManager,
            $request->request,
            $isStockEmergency
                ? FixedFieldStandard::ENTITY_CODE_STOCK_EMERGENCY
                : FixedFieldStandard::ENTITY_CODE_TRACKING_EMERGENCY,
            true);

        $emergency
            ->setType($type)
            ->setCreatedAt(new DateTime());

        if ($request->request->getBoolean("isAttachmentForm")) {
            $this->attachmentService->persistAttachments($entityManager, $request->files, ["attachmentContainer" => $emergency]);
        }

        if($data->get(FixedFieldEnum::dateStart->name) || $data->get(EndEmergencyCriteriaEnum::MANUAL->value)) {
            $dateStart = $this->formatService->parseDatetime($data->get(FixedFieldEnum::dateStart->name) ?? $data->get(EndEmergencyCriteriaEnum::MANUAL->value));
            $emergency->setDateStart($dateStart);
        }

        if($data->get(FixedFieldEnum::dateEnd->name)) {
            $dateEnd = $this->formatService->parseDatetime($data->get(FixedFieldEnum::dateEnd->name));
            $emergency->setDateEnd($dateEnd);
        }

        $emergency->setEndEmergencyCriteria($data->get("endEmergencyCriteria")
            ? EndEmergencyCriteriaEnum::from($data->get("endEmergencyCriteria"))
            : EndEmergencyCriteriaEnum::REMAINING_QUANTITY);

        if ($data->has(FixedFieldEnum::supplier->name)) {
            $provider = $data->get(FixedFieldEnum::supplier->name)
                ? $supplierRepository->find($data->get(FixedFieldEnum::supplier->name))
                : null;
            $emergency->setSupplier($provider);
        }

        if ($data->has(FixedFieldEnum::orderNumber->name)) {
            $emergency->setCommand($data->get(FixedFieldEnum::orderNumber->name));
        }

        if ($data->has(FixedFieldEnum::carrierTrackingNumber->name)) {
            $emergency->setCarrierTrackingNumber($data->get(FixedFieldEnum::carrierTrackingNumber->name));
        }

        if($emergency instanceof TrackingEmergency) {
            $this->updateTrackingEmergency($entityManager, $emergency, $data);
        } else if($emergency instanceof StockEmergency) {
            $this->updateStockEmergency($entityManager, $emergency, $data);
        }

        $this->checkForDuplicates($entityManager, $emergency);

        return $emergency;
    }

    private function updateTrackingEmergency(EntityManagerInterface $entityManager,
                                             TrackingEmergency      $emergency,
                                             InputBag               $data): void {
        $userRepository = $entityManager->getRepository(Utilisateur::class);
        $carrierRepository = $entityManager->getRepository(Transporteur::class);

        if ($data->has(FixedFieldEnum::buyer->name)) {
            $buyer = $data->get(FixedFieldEnum::buyer->name)
                ? $userRepository->find($data->get(FixedFieldEnum::buyer->name))
                : null;
            $emergency->setBuyer($buyer);
        }

        if ($data->has(FixedFieldEnum::carrier->name)) {
            $carrier = $data->get(FixedFieldEnum::carrier->name)
                ? $carrierRepository->find($data->get(FixedFieldEnum::carrier->name))
                : null;
            $emergency->setCarrier($carrier);
        }

        if ($data->has(FixedFieldEnum::postNumber->name)) {
            $emergency->setPostNumber($data->get(FixedFieldEnum::postNumber->name));
        }

        if ($data->has(FixedFieldEnum::internalArticleCode->name)) {
            $emergency->setInternalArticleCode($data->get(FixedFieldEnum::internalArticleCode->name));
        }

        if ($data->has(FixedFieldEnum::supplierArticleCode->name)) {
            $emergency->setSupplierArticleCode($data->get(FixedFieldEnum::supplierArticleCode->name));
        }
    }

    private function updateStockEmergency(EntityManagerInterface $entityManager,
                                          StockEmergency         $emergency,
                                          InputBag               $data): void {
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);

        $emergency->setEmergencyTrigger(EmergencyTriggerEnum::from($data->get("emergencyTrigger")));
        if ($data->has(EndEmergencyCriteriaEnum::REMAINING_QUANTITY->value)) {
            $emergency->setExpectedQuantity($data->get(EndEmergencyCriteriaEnum::REMAINING_QUANTITY->value));
        }

        if ($data->has(FixedFieldEnum::reference->name)) {
            $referenceArticle = $data->get(FixedFieldEnum::reference->name)
                ? $referenceArticleRepository->find($data->get(FixedFieldEnum::reference->name))
                : null;

            $emergency->setReferenceArticle($referenceArticle);
        }

        if ($data->has(FixedFieldEnum::comment->name)) {
            $emergency->setComment($data->get(FixedFieldEnum::comment->name));
        }
    }

    private function checkForDuplicates(EntityManagerInterface $entityManager,
                                        Emergency              $emergency): void {

        if ($emergency instanceof TrackingEmergency) {
            $trackingEmergencyRepository = $entityManager->getRepository(TrackingEmergency::class);

            $duplicateEmergency = $trackingEmergencyRepository->countMatching(
                $emergency->getDateStart(),
                $emergency->getDateEnd(),
                $emergency->getSupplier(),
                $emergency->getCommand(),
                $emergency->getPostNumber(),
            );

            if($duplicateEmergency) {
                throw new FormException("Vous ne pouvez pas créer 2 urgences identiques");
            }
        }
        else if ($emergency instanceof StockEmergency) {
            $stockEmergencyRepository = $entityManager->getRepository(StockEmergency::class);

            $duplicateEmergency = $stockEmergencyRepository->findOneBy([
                'emergencyTrigger' => EmergencyTriggerEnum::REFERENCE,
                'endEmergencyCriteria' => EndEmergencyCriteriaEnum::REMAINING_QUANTITY,
                'referenceArticle' => $emergency->getReferenceArticle(),
            ]);

            if($duplicateEmergency) {
                throw new FormException("Vous ne pouvez pas créer 2 urgences avec la même référence et le critère de fin d'urgence quantité");
            }
        }
    }
}
