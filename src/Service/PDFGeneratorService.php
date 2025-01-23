<?php

namespace App\Service;

use App\Entity\Dispatch;
use App\Entity\DispatchPack;
use App\Entity\Livraison;
use App\Entity\Setting;
use App\Entity\TagTemplate;
use App\Entity\Transport\TransportDeliveryRequest;
use App\Entity\Transport\TransportRequest;
use App\Entity\Transport\TransportRound;
use App\Entity\Transport\TransportRoundLine;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Snappy\PDF as PDFGenerator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Environment as Twig_Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use WiiCommon\Helper\Stream;

class PDFGeneratorService {

    public const PREFIX_BARCODE_FILENAME = 'ETQ';

    public const MAX_LINE_LENGHT_WRAP = 30;

    public function __construct(
        #[Autowire("@knp_snappy.pdf")] private PDFGenerator $PDFGenerator,
        private Twig_Environment                            $templating,
        private EntityManagerInterface                      $entityManager,
        private SettingsService                             $settingsService,
        private FormatService                               $formatService,
    ) {
    }

    // TODO throw error if dimension do not exists

    /**
     * @param string $title
     * @param array $barcodeConfigs Array of ['code' => string, 'labels' => array]. labels optional
     * @return string
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function generatePDFBarCodes(string $title, array $barcodeConfigs, bool $landscape = false, ?TagTemplate $tagTemplate = null): string {
        $barcodeConfig = $this->settingsService->getDimensionAndTypeBarcodeArray($this->entityManager);
        $height = $tagTemplate ? $tagTemplate->getHeight() : $barcodeConfig['height'];
        $width = $tagTemplate ? $tagTemplate->getWidth() : $barcodeConfig['width'];
        $isCode128 = $tagTemplate ? $tagTemplate->isBarcode() : $barcodeConfig['isCode128'];

        $barcodeConfigsToTwig = array_map(function($config) use ($isCode128, $width) {
            $code = $config['code'];
            $separated = $config['separated'] ?? false;
            $labelForSecondBarcode = null;
            $labels = array_filter($config['labels'] ?? [], function($label) {
                return !empty($label);
            });

            $longestLabel = array_reduce($labels, function($carry, $label) {
                if(is_array($label)) {
                    return $carry;
                }
                $currentLen = strlen($label);
                return strlen($label) > $carry ? $currentLen : $carry;
            }, 0);

            // use to wrap long label
            foreach ($labels as $key=>$label){
                $largeLabel = !is_array($label) && strlen($label) >= self::MAX_LINE_LENGHT_WRAP;
                if(is_array($label)){
                    $labelForSecondBarcode = $label;
                    unset($labels[$key]);
                }

                if($largeLabel){
                    $lineBreakKey = strpos($label, ' ', self::MAX_LINE_LENGHT_WRAP);
                    if($lineBreakKey){
                        // first part
                        $labels[$key] = substr($label, 0, $lineBreakKey);
                        // second part
                        $newLabel[] = substr($label, $lineBreakKey,strlen($label)-1);
                        // add second part to the array
                        array_splice($labels, $key+1, 0, $newLabel);
                    }
                }
            }

            return [
                'barcode' => [
                    'code' => $code,
                    'type' => $isCode128 ? 'c128' : 'qrcode',
                    'width' => $isCode128 ? 1 : 48,
                    'height' => 48,
                    'longestLabel' => $longestLabel
                ],
                'labels' => $labels,
                'separated' => $separated,
                'firstCustomIcon' => $config['firstCustomIcon'] ?? null,
                'secondCustomIcon' => $config['secondCustomIcon'] ?? null,
                'typeLogoArrivalUl' => $config['typeLogoArrivalUl'] ?? null,
                'businessUnit' => $config['businessUnit'] ?? false,
                'labelForSecondBarcode' => $labelForSecondBarcode,
            ];
        }, $barcodeConfigs);

        $logo = ($barcodeConfig['logo'] && file_exists(getcwd() . "/" . $barcodeConfig['logo'])
            ? $barcodeConfig['logo']
            : null);

        return $this->PDFGenerator->getOutputFromHtml(
            $this->templating->render('prints/barcodeTemplate.html.twig', [
                'logo' => $logo,
                'title' => $title,
                'height' => $height,
                'width' => $width,
                'barcodeConfigs' => $barcodeConfigsToTwig,
                'landscape' => $landscape
            ]),
            [
                'page-height' => "{$height}mm",
                'page-width' => "{$width}mm",
                'margin-top' => 0,
                'margin-right' => 0,
                'margin-bottom' => 0,
                'margin-left' => 0,
                'encoding' => 'UTF-8',
                'no-outline' => true,
                'disable-smart-shrinking' => true,
            ]
        );
    }

    public function generatePDFDeliveryNote(string $title,
                                            ?string $logo,
                                            Dispatch|Livraison $entity): string {


        $content = $this->templating->render('prints/deliveryNoteTemplate.html.twig', [
            'title' => $title,
            'entity' => $entity,
            'logo' => $logo
        ]);

        $header = $this->templating->render("prints/deliveryNoteTemplateHeader.html.twig", [
            "logo" => $logo,
            'entity' => $entity,
        ]);

        $footer = $this->templating->render("prints/deliveryNoteTemplateFooter.html.twig", [
            'entity' => $entity,
        ]);

        return $this->PDFGenerator->getOutputFromHtml($content, [
            "page-size" => "A4",
            "enable-local-file-access" => true,
            "encoding" => "UTF-8",
            "header-html" => $header,
            "footer-html" => $footer
        ]);
    }

    public function generatePDFOverconsumption(Dispatch $dispatch, ?string $appLogo, ?string $overconsumptionLogo, array $additionalFields = []): string {
        $content = $this->templating->render("prints/overconsumptionTemplate.html.twig", [
            "dispatch" => $dispatch,
            "additionalFields" => $additionalFields
        ]);

        $header = $this->templating->render("prints/overconsumptionTemplateHeader.html.twig", [
            "app_logo" => $appLogo ?? "",
            "overconsumption_logo" => $overconsumptionLogo ?? "",
            "commandNumber" => $dispatch->getCommandNumber() ?? ""
        ]);

        $footer = $this->templating->render("prints/overconsumptionTemplateFooter.html.twig");

        return $this->PDFGenerator->getOutputFromHtml($content, [
            "page-size" => "A4",
            "orientation" => "landscape",
            "enable-local-file-access" => true,
            "encoding" => "UTF-8",
            "header-html" => $header,
            "footer-html" => $footer
        ]);
    }

    public function generatePDFDispatchNote(Dispatch $dispatch): string {
        $appLogo = $this->settingsService->getValue($this->entityManager, Setting::LABEL_LOGO);

        $content = $this->templating->render("prints/dispatchNoteTemplate.html.twig", [
            "app_logo" => $appLogo ?? "",
            "dispatch" => $dispatch,
        ]);

        return $this->PDFGenerator->getOutputFromHtml($content, [
            "page-size" => "A4",
            "orientation" => "portrait",
            "enable-local-file-access" => true,
            "encoding" => "UTF-8",
        ]);
    }

    /**
     * @param array $barcodeConfigs ['code' => string][]
     * @param string $name
     * @return string
     */
    public function getBarcodeFileName(array $barcodeConfigs, string $name, string $prefix = PDFGeneratorService::PREFIX_BARCODE_FILENAME): string {
        $barcodeCounter = count($barcodeConfigs);
        $smartBarcodeLabel = $barcodeCounter === 1
            ? $barcodeConfigs[0]['code'] ?: ''
            : '';

        $fileName = ($prefix . '_' .
            $name .
            (($barcodeCounter === 1 && !empty($smartBarcodeLabel)) ? ('_' . $smartBarcodeLabel) : '') .
            '.pdf'
        );

        // remove / and \ in filename
        return str_replace(['/', '\\'], '', $fileName);
    }

    public function generatePDFTransport(TransportRequest $transportRequest): string {
        $appLogo = $this->settingsService->getValue($this->entityManager,Setting::FILE_SHIPMENT_NOTE_LOGO);
        $society = $this->settingsService->getValue($this->entityManager,Setting::SHIPMENT_NOTE_COMPANY_DETAILS);
        $originator = $this->settingsService->getValue($this->entityManager,Setting::SHIPMENT_NOTE_ORIGINATOR);
        $sender = $this->settingsService->getValue($this->entityManager,Setting::SHIPMENT_NOTE_SENDER_DETAILS);

        $content = $this->templating->render("prints/transportTemplate.html.twig", [
            "app_logo" => $appLogo ?? "",
            "society" => $society,
            "requestNumber" => TransportRequest::NUMBER_PREFIX . $transportRequest->getNumber(),
            "originator" => $originator,
            "sender" => $sender,
            "round" => $transportRequest->getOrder()->getTransportRoundLines()->last(),
            "request" => $transportRequest,
        ]);

        return $this->PDFGenerator->getOutputFromHtml($content, [
            "page-size" => "A4",
            "orientation" => "landscape",
            "enable-local-file-access" => true,
            "encoding" => "UTF-8",
        ]);
    }

    public function generatePDFTransportRound(TransportRound $transportRound): string
    {
        $appLogo = $this->settingsService->getValue($this->entityManager,Setting::FILE_SHIPMENT_NOTE_LOGO);
        $society = $this->settingsService->getValue($this->entityManager,Setting::SHIPMENT_NOTE_COMPANY_DETAILS);
        $originator = $this->settingsService->getValue($this->entityManager,Setting::SHIPMENT_NOTE_ORIGINATOR);
        $sender = $this->settingsService->getValue($this->entityManager,Setting::SHIPMENT_NOTE_SENDER_DETAILS);
        $content = "";

        /** @var TransportRoundLine $line */
        foreach ($transportRound->getTransportRoundLines() as $line) {
            $request = $line->getOrder()?->getRequest();
            if ($request instanceof TransportDeliveryRequest) {
                $requestNumber = TransportRequest::NUMBER_PREFIX . $request?->getNumber();
                $content .= $this->templating->render("prints/transportTemplate.html.twig", [
                        "app_logo" => $appLogo ?? "",
                        "society" => $society,
                        "requestNumber" => $requestNumber,
                        "originator" => $originator,
                        "sender" => $sender,
                        "round" => $transportRound,
                        "request" => $request,
                    ]);
            }
        }

        return $this->PDFGenerator->getOutputFromHtml($content, [
            "page-size" => "A4",
            "orientation" => "landscape",
            "enable-local-file-access" => true,
            "encoding" => "UTF-8",
        ]);
    }

    public function generateFromDocx(string $docx, string $outdir): void {
        $command = !empty($_SERVER["LIBREOFFICE_EXEC"]) ? $_SERVER["LIBREOFFICE_EXEC"] : 'libreoffice';
        exec("\"{$command}\" --headless --convert-to pdf \"{$docx}\" --outdir \"{$outdir}\"");
    }

    public function generateDispatchLabel(Dispatch                  $dispatch,
                                          string                    $title,
                                          EntityManagerInterface    $entityManager): string {
        $barcodeConfig = $this->settingsService->getDimensionAndTypeBarcodeArray($entityManager);
        $height = $barcodeConfig['height'];
        $width = $barcodeConfig['width'];
        $isCode128 = $barcodeConfig['isCode128'];

        $barcodeConfigsToTwig = Stream::from($dispatch->getDispatchPacks()->toArray())
            ->map(function(DispatchPack $dispatchPack) use ($dispatch, $isCode128) {
                $pack = $dispatchPack->getPack();
                return [
                    'barcode' => [
                        'code' => $pack->getCode(),
                        'type' => $isCode128 ? 'c128' : 'qrcode',
                        'width' => $isCode128 ? 1 : 48,
                        'height' => 40,
                    ],
                    'dispatch' => [
                        'number' => $dispatch->getNumber(),
                        'businessUnit' => $dispatch->getBusinessUnit(),
                        'orderNumber' => $dispatch->getCommandNumber(),
                        'customerName' => $dispatch->getCustomerName(),
                        'customerAddress' => $dispatch->getCustomerAddress(),
                        'customerRecipient' => $dispatch->getCustomerRecipient(),
                        'customerPhone' => $dispatch->getCustomerPhone(),
                        'dispatchPack' => [
                            'code' => $pack->getCode(),
                            'nature' => $this->formatService->nature($pack->getNature()),
                            'height' => $dispatchPack->getHeight(),
                            'width' => $dispatchPack->getWidth(),
                            'length' => $dispatchPack->getLength(),
                            'weight' => $pack->getWeight(),
                            'volume' => $pack->getVolume(),
                        ],
                    ],
                    'requester' => $this->formatService->user($dispatch->getRequester()),
                ];
            })
            ->toArray();

        return $this->PDFGenerator->getOutputFromHtml(
            $this->templating->render('prints/dispatchLabelTemplate.html.twig', [
                'title' => $title,
                'height' => $height,
                'width' => $width,
                'barcodeConfigs' => $barcodeConfigsToTwig,
            ]),
            [
                'page-height' => "{$height}mm",
                'page-width' => "{$width}mm",
                'margin-top' => 0,
                'margin-right' => 0,
                'margin-bottom' => 0,
                'margin-left' => 0,
                'encoding' => 'UTF-8',
                'no-outline' => true,
                'disable-smart-shrinking' => true,
            ]
        );
    }
}
