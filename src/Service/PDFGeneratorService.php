<?php

namespace App\Service;

use App\Entity\Dispatch;
use App\Entity\Livraison;
use App\Entity\Setting;
use App\Entity\TagTemplate;
use App\Entity\Transport\TransportDeliveryRequest;
use App\Entity\Transport\TransportRequest;
use App\Entity\Transport\TransportRound;
use App\Entity\Transport\TransportRoundLine;
use Doctrine\ORM\EntityManagerInterface;
use JetBrains\PhpStorm\Deprecated;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment as Twig_Environment;
use Knp\Snappy\PDF as PDFGenerator;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class PDFGeneratorService {

    public const PREFIX_BARCODE_FILENAME = 'ETQ';

    /** @var Twig_Environment */
    private $templating;

    /** @var $PDFGenerator */
    private $PDFGenerator;

    private $kernel;

    private $entityManager;

    #[Required]
    public SettingsService $settingsService;

    public function __construct(PDFGenerator           $PDFGenerator,
                                KernelInterface        $kernel,
                                Twig_Environment       $templating,
                                EntityManagerInterface $entityManager)
    {
        $this->templating = $templating;
        $this->PDFGenerator = $PDFGenerator;
        $this->kernel = $kernel;
        $this->entityManager = $entityManager;
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
        $barcodeConfig = $this->settingsService->getDimensionAndTypeBarcodeArray();

        $height = $tagTemplate ? $tagTemplate->getHeight() : $barcodeConfig['height'];
        $width = $tagTemplate ? $tagTemplate->getWidth() : $barcodeConfig['width'];
        $isCode128 = $tagTemplate ? $tagTemplate->isBarcode() : $barcodeConfig['isCode128'];

        $barcodeConfigsToTwig = array_map(function($config) use ($isCode128, $width) {
            $code = $config['code'];
            $separated = $config['separated'] ?? false;
            $labels = array_filter($config['labels'] ?? [], function($label) {
                return !empty($label);
            });

            $longestLabel = array_reduce($labels, function($carry, $label) {
                $currentLen = strlen($label);
                return strlen($label) > $carry ? $currentLen : $carry;
            }, 0);

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
                'businessUnit' => $config['businessUnit'] ?? false,
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
                'page-height' => "${height}mm",
                'page-width' => "${width}mm",
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

    // TODO WIIS-8753
    #[Deprecated]
    public function generatePDFWaybill(string $title, ?string $logo, Dispatch|Livraison $entity, array $packs): string {
        $fileName = uniqid() . '.pdf';

        $this->PDFGenerator->generateFromHtml(
            $this->templating->render('prints/waybillTemplate.html.twig', [
                'title' => $title,
                'entity' => $entity,
                'logo' => $logo,
                'packs' => $packs,
                'number' => $entity instanceof Dispatch ? $entity->getNumber() : ($entity instanceof Livraison ? $entity->getNumero() : ''),
                'fromDelivery' => $entity instanceof Livraison
            ]),
            ($this->kernel->getProjectDir() . '/public/uploads/attachements/' . $fileName),
            [
                'page-size' => "A4",
                'encoding' => 'UTF-8'
            ]
        );

        return $fileName;
    }
    /*public function generatePDFDeliveryNote(string $title,
                                            ?string $logo,
                                            Dispatch|Livraison $entity): string {
        $fileName = uniqid() . '.pdf';

        $this->PDFGenerator->generateFromHtml(
            $this->templating->render('prints/deliveryNoteTemplate.html.twig', [
                'title' => $title,
                'entity' => $entity,
                'logo' => $logo
            ]),
            ($this->kernel->getProjectDir() . '/public/uploads/attachements/' . $fileName),
            [
                'page-size' => "A4",
                'encoding' => 'UTF-8'
            ]
        );

        return $fileName;
    }*/

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
        $settingRepository = $this->entityManager->getRepository(Setting::class);
        $appLogo = $settingRepository->getOneParamByLabel(Setting::LABEL_LOGO);

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
        // remove / and \ in filename
        $smartBarcodeLabel = $barcodeCounter === 1
            ? str_replace(['/', '\\'], '', $barcodeConfigs[0]['code'] ?: '')
            : '';

        return (
            $prefix . '_' .
            $name .
            (($barcodeCounter === 1 && !empty($smartBarcodeLabel)) ? ('_' . $smartBarcodeLabel) : '') .
            '.pdf'
        );
    }

    public function generatePDFTransport(TransportRequest $transportRequest): string {
        $settingRepository = $this->entityManager->getRepository(Setting::class);
        $appLogo = $settingRepository->getOneParamByLabel(Setting::FILE_SHIPMENT_NOTE_LOGO);
        $society = $settingRepository->getOneParamByLabel(Setting::SHIPMENT_NOTE_COMPANY_DETAILS);
        $originator = $settingRepository->getOneParamByLabel(Setting::SHIPMENT_NOTE_ORIGINATOR);
        $sender = $settingRepository->getOneParamByLabel(Setting::SHIPMENT_NOTE_SENDER_DETAILS);

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
        $settingRepository = $this->entityManager->getRepository(Setting::class);
        $appLogo = $settingRepository->getOneParamByLabel(Setting::FILE_SHIPMENT_NOTE_LOGO);
        $society = $settingRepository->getOneParamByLabel(Setting::SHIPMENT_NOTE_COMPANY_DETAILS);
        $originator = $settingRepository->getOneParamByLabel(Setting::SHIPMENT_NOTE_ORIGINATOR);
        $sender = $settingRepository->getOneParamByLabel(Setting::SHIPMENT_NOTE_SENDER_DETAILS);
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

    public function generatePDFromDoc(string $docx) {

        $command = '"' . ($_SERVER["LIBREOFFICE_EXEC"] ?? 'libreoffice') . '"' . " --headless --convert-to pdf " . $docx;

        exec($command);
    }
}
