<?php

namespace App\Service;

use App\Entity\CategorieCL;
use App\Entity\Dispatch;
use App\Entity\DispatchPack;
use App\Entity\Fields\FixedFieldByType;
use App\Entity\Fields\FixedFieldStandard;
use App\Entity\FreeField;
use App\Entity\Printer;
use App\Entity\Setting;
use App\Entity\Type;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment;
use WiiCommon\Helper\Stream;
use ZplGenerator\Client\SocketClient;
use ZplGenerator\Elements\Codes\QrCode;
use ZplGenerator\Elements\Common\Align;
use ZplGenerator\Elements\Element;
use ZplGenerator\Elements\Image;
use ZplGenerator\Elements\Text\Text;
use ZplGenerator\Elements\Text\TextConfig;
use ZplGenerator\Printer\Printer as ZebraPrinter;

class PrinterService
{
    private const DEFAULT_PRINTER_PORT = 9100;
    private const DEFAULT_PRINTER_TIMEOUT = 5;

    private const MAX_QR_CODE_LENGTH = 18;

    #[Required]
    public EntityManagerInterface $entityManager;

    #[Required]
    public KernelInterface $kernel;

    #[Required]
    public Environment $twig;

    #[Required]
    public FormatService $formatService;

    private ?array $printers = null;

    public function printDispatchPacks(ZebraPrinter $zebraPrinter, Printer $printer, Dispatch $dispatch, array $packs, bool $isSeparation): void
    {
        $settingsRepository = $this->entityManager->getRepository(Setting::class);
        $fixedFieldByTypeRepository = $this->entityManager->getRepository(FixedFieldByType::class);
        $freeFieldRepository = $this->entityManager->getRepository(FreeField::class);

        $logo = $settingsRepository->getOneParamByLabel(Setting::LABEL_LOGO);
        $fixedFields = $fixedFieldByTypeRepository->findBy(["entityCode" => FixedFieldStandard::ENTITY_CODE_DISPATCH]);
        $freeFields = $freeFieldRepository->findByTypeAndCategorieCLLabel($dispatch->getType(), CategorieCL::DEMANDE_DISPATCH);
        $freeFieldValues = $dispatch->getFreeFields();

        $getValueByFieldCode = function (string $fieldCode) use ($dispatch): ?string {
            return match ($fieldCode) {
                FixedFieldStandard::FIELD_CODE_TYPE_DISPATCH => $this->formatService->type($dispatch->getType()),
                FixedFieldStandard::FIELD_CODE_STATUS_DISPATCH => $this->formatService->status($dispatch->getStatut()),
                FixedFieldStandard::FIELD_CODE_START_DATE_DISPATCH => $this->formatService->datetime($dispatch->getStartDate()),
                FixedFieldStandard::FIELD_CODE_END_DATE_DISPATCH => $this->formatService->datetime($dispatch->getEndDate()),
                FixedFieldStandard::FIELD_CODE_REQUESTER_DISPATCH => $this->formatService->user($dispatch->getRequester()),
                FixedFieldStandard::FIELD_CODE_CARRIER_DISPATCH => $this->formatService->carrier($dispatch->getCarrier()),
                FixedFieldStandard::FIELD_CODE_CARRIER_TRACKING_NUMBER_DISPATCH => $dispatch->getCarrierTrackingNumber(),
                FixedFieldStandard::FIELD_CODE_RECEIVER_DISPATCH => $this->formatService->users($dispatch->getReceivers()),
                FixedFieldStandard::FIELD_CODE_EMAILS => implode(",", $dispatch->getEmails()),
                FixedFieldStandard::FIELD_CODE_CUSTOMER_PHONE_DISPATCH => $dispatch->getCustomerPhone(),
                FixedFieldStandard::FIELD_CODE_EMERGENCY => $dispatch->getEmergency(),
                FixedFieldStandard::FIELD_CODE_PROJECT_NUMBER => $dispatch->getProjectNumber(),
                FixedFieldStandard::FIELD_CODE_COMMAND_NUMBER_DISPATCH => $dispatch->getCommandNumber(),
                FixedFieldStandard::FIELD_CODE_COMMENT_DISPATCH => strip_tags($dispatch->getCommentaire()),
                FixedFieldStandard::FIELD_CODE_CUSTOMER_NAME_DISPATCH => $dispatch->getCustomerName(),
                FixedFieldStandard::FIELD_CODE_CUSTOMER_RECIPIENT_DISPATCH => $dispatch->getCustomerRecipient(),
                FixedFieldStandard::FIELD_CODE_CUSTOMER_ADDRESS_DISPATCH => $dispatch->getCustomerAddress(),
                FixedFieldStandard::FIELD_CODE_LOCATION_PICK => $this->formatService->location($dispatch->getLocationFrom()),
                FixedFieldStandard::FIELD_CODE_LOCATION_DROP => $this->formatService->location($dispatch->getLocationTo()),
                FixedFieldStandard::FIELD_CODE_DESTINATION => $dispatch->getDestination(),
                FixedFieldStandard::FIELD_CODE_BUSINESS_UNIT => $dispatch->getBusinessUnit(),
            };
        };

        $labels = Stream::from($packs)
            ->reindex()
            ->map(function (DispatchPack $dispatchPack, int $i) use ($zebraPrinter, $dispatch, $logo, $packs, $isSeparation, $fixedFieldByTypeRepository, $freeFieldRepository, $fixedFields, $freeFieldValues, $freeFields, $getValueByFieldCode) {
                $code = $dispatch->getType()->getDispatchLabelField() === Type::DISPATCH_NUMBER
                    ? $dispatch->getNumber()
                    : $dispatchPack->getPack()->getCode();

                $labelStyle = new TextConfig(null, 5);
                $paginationStyle = new TextConfig(null, 5);

                $qr = QrCode::create(0, 20)
                    ->setSize(7)
                    ->setAlignment(Align::CENTER)
                    ->setContent($code)
                    ->setDisplayContent(strlen($code) <= self::MAX_QR_CODE_LENGTH);

                $label = $zebraPrinter->createLabel();
                $label->with(
                    Image::fromPath(3, 3, "{$this->kernel->getProjectDir()}/public/$logo")
                        ->setWidth(30)
                        ->setHeight(15)
                )
                    ->with($qr);

                if(strlen($code) > self::MAX_QR_CODE_LENGTH) {
                    $label->with(Text::create(0, 45)
                        ->setAlignment(Align::CENTER)
                        ->setText($code)
                        ->setConfig(new TextConfig(null, 4, null))
                    );
                }

                if ($isSeparation) {
                    $label->with(Text::create(30, 8)
                        ->setAlignment(Align::RIGHT)
                        ->setText("Nb UL: ".($i + 1) . "/" . count($packs))
                        ->setConfig($paginationStyle)
                        ->setMaxLines(1));
                }

                if($dispatch->getType()->isDisplayLogisticUnitsCountOnDispatchLabel()) {
                    $reindexed = Stream::from($dispatchPack->getDispatch()->getDispatchPacks()->getValues())
                        ->reindex();

                    $label->with(Text::create(40, 8)
                        ->setAlignment(Align::CENTER)
                        ->setText("Nb UL: ".($reindexed->indexOf($dispatchPack) + 1) . "/" . $reindexed->count())
                        ->setConfig($paginationStyle)
                        ->setMaxLines(1));
                }

                [, $height] = $labelStyle->dimensions();

                $fixedFieldsToDisplay = Stream::from($fixedFields)
                    ->filter(static fn(FixedFieldByType $fixedField) => $fixedField->isOnLabel($dispatch->getType()))
                    ->map(static fn(FixedFieldByType $fixedField) => [
                        strip_tags($fixedField->getFieldLabel()), $getValueByFieldCode($fixedField->getFieldCode())
                    ])
                    ->reindex()
                    ->toArray();

                $freeFieldsToDisplay = Stream::from($freeFields)
                    ->filter(static fn(FreeField $freeField) => $freeField->isDisplayedOnLabel())
                    ->map(static fn(FreeField $freeField) => [
                        $freeField->getLabel(), $freeFieldValues[$freeField->getId()] ?? null
                    ])
                    ->reindex()
                    ->toArray();

                Stream::from($fixedFieldsToDisplay, $freeFieldsToDisplay)
                    ->flatMap(function (array $field, int $i) use ($dispatch, $labelStyle, $height) {
                        [$key, $value] = $field;
                        $elements = [];

                        if (trim($value)) {
                            $elements[] = Text::create(0, 55 + $i * $height)
                                ->setText($value)
                                ->setConfig($labelStyle)
                                ->setAlignment(Align::CENTER)
                                ->setSpacing(7)
                                ->setMaxLines(8);
                        }

                        if ($key === FixedFieldStandard::FIELD_CODE_TYPE_DISPATCH && $dispatch->getType()->getLogo()?->getFullPath()) {
                            $path = "{$this->kernel->getProjectDir()}/public/{$dispatch->getType()->getLogo()->getFullPath()}";
                            $x = 58 - $labelStyle->dimensionsOf($value)[0] / 2;
                            $y = 57 + $i * $height - 1;

                            $elements[] = Image::fromPath($x, $y, $path)
                                ->setWidth(5)
                                ->setHeight(5);
                        }

                        return $elements;
                    })
                    ->each(static fn(Element $element) => $label->with($element));

                dump($label->toZPL());

                return $label;
            })
            ->toArray();

        /*dump($e);*/
        $client = SocketClient::create($printer->getAddress(), self::DEFAULT_PRINTER_PORT, self::DEFAULT_PRINTER_TIMEOUT);
        $zebraPrinter->print($client, ...$labels);
    }

    public function getPrinter(Printer $printer): ZebraPrinter
    {
        if (!isset($this->printers[$printer->getId()])) {
            $this->printers[$printer->getId()] = ZebraPrinter::create()
                ->setDimension($printer->getWidth(), $printer->getHeight())
                ->setDPI($printer->getDPI());
        }

        return $this->printers[$printer->getId()];
    }

}
