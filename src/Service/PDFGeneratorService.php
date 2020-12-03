<?php

namespace App\Service;

use App\Entity\Dispatch;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Twig\Environment as Twig_Environment;
use Knp\Snappy\PDF as PDFGenerator;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class PDFGeneratorService {

    public const PREFIX_BARCODE_FILENAME = 'ETQ';

    /** @var GlobalParamService */
    private $globalParamService;

    /** @var Twig_Environment */
    private $templating;

    /** @var $PDFGenerator */
    private $PDFGenerator;

    private $kernel;

    private $entityManager;

    public function __construct(GlobalParamService $globalParamService,
                                PDFGenerator $PDFGenerator,
                                KernelInterface $kernel,
                                Twig_Environment $templating,
                                EntityManagerInterface $entityManager) {
        $this->globalParamService = $globalParamService;
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
    public function generatePDFBarCodes(string $title, array $barcodeConfigs): string {
        $barcodeConfig = $this->globalParamService->getDimensionAndTypeBarcodeArray(true);

        $height = $barcodeConfig['height'];
        $width = $barcodeConfig['width'];
        $isCode128 = $barcodeConfig['isCode128'];

        $barcodeConfigsToTwig = array_map(function($config) use ($isCode128, $width) {
            $code = $config['code'];
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
                'firstCustomIcon' => $config['firstCustomIcon'] ?? null,
                'secondCustomIcon' => $config['secondCustomIcon'] ?? null
            ];
        }, $barcodeConfigs);

        $logo = ($barcodeConfig['logo'] && file_exists(getcwd() . "/uploads/attachements/" . $barcodeConfig['logo'])
            ? $barcodeConfig['logo']
            : null);

        return $this->PDFGenerator->getOutputFromHtml(
            $this->templating->render('prints/barcode-template.html.twig', [
                'logo' => $logo,
                'title' => $title,
                'height' => $height,
                'width' => $width,
                'barcodeConfigs' => $barcodeConfigsToTwig
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

    /**
     * @param string $title
     * @param array $sheetConfigs Array of ['title' => string, 'code' => string, 'content' => assoc_array]
     * @return string
     * @throws LoaderError
     * @throws NonUniqueResultException
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function generatePDFStateSheet(string $title, array $sheetConfigs): string {
        $barcodeConfig = $this->globalParamService->getDimensionAndTypeBarcodeArray(true);

        $isCode128 = $barcodeConfig['isCode128'];

        return $this->PDFGenerator->getOutputFromHtml(
            $this->templating->render('prints/state-sheet-template.html.twig', [
                'title' => $title,
                'sheetConfigs' => $sheetConfigs,

                'barcodeType' => $isCode128 ? 'c128' : 'qrcode',
                'barcodeWidth' => $isCode128 ? 1 : 48,
                'barcodeHeight' => 48
            ]),
            [
                'page-size' => "A4",
                'encoding' => 'UTF-8'
            ]
        );
    }

    /**
     * @param string $title
     * @param string|null $logo
     * @param Dispatch $dispatch
     * @return PdfResponse The PDF response
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function generatePDFWaybill(string $title, ?string $logo, Dispatch $dispatch): string {
        $fileName = uniqid() . '.pdf';

        $this->PDFGenerator->generateFromHtml(
            $this->templating->render('prints/waybill-template.html.twig', [
                'title' => $title,
                'dispatch' => $dispatch,
                'logo' => $logo
            ]),
            ($this->kernel->getProjectDir() . '/public/uploads/attachements/' . $fileName),
            [
                'page-size' => "A4",
                'encoding' => 'UTF-8'
            ]
        );

        return $fileName;
    }

    /**
     * @param string $title
     * @param string|null $logo
     * @param Dispatch $dispatch
     * @return Response The PDF response
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function generatePDFDeliveryNote(string $title,
                                            ?string $logo,
                                            Dispatch $dispatch): string {
        $fileName = uniqid() . '.pdf';

        $this->PDFGenerator->generateFromHtml(
            $this->templating->render('prints/delivery-note-template.html.twig', [
                'title' => $title,
                'dispatch' => $dispatch,
                'logo' => $logo
            ]),
            ($this->kernel->getProjectDir() . '/public/uploads/attachements/' . $fileName),
            [
                'page-size' => "A4",
                'encoding' => 'UTF-8'
            ]
        );

        return $fileName;
    }

    /**
     * @param Dispatch $dispatch
     * @param string|null $appLogo
     * @param string|null $overconsumptionLogo
     * @return Response The PDF response
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function generatePDFOverconsumption(Dispatch $dispatch, ?string $appLogo, ?string $overconsumptionLogo): string {
        $content = $this->templating->render("prints/overconsumption-template.html.twig", [
            "dispatch" => $dispatch
        ]);

        $header = $this->templating->render("prints/overconsumption-template-header.html.twig", [
            "app_logo" => $appLogo ? $appLogo : '',
            "overconsumption_logo" => $overconsumptionLogo ? $overconsumptionLogo : '',
        ]);

        $footer = $this->templating->render("prints/overconsumption-template-footer.html.twig");

        return $this->PDFGenerator->getOutputFromHtml($content, [
            "page-size" => "A4",
            "orientation" => "landscape",
            "enable-local-file-access" => true,
            "encoding" => "UTF-8",
            "header-html" => $header,
            "footer-html" => $footer
        ]);
    }

    /**
     * @param array $barcodeConfigs ['code' => string][]
     * @param string $name
     * @return string
     */
    public function getBarcodeFileName(array $barcodeConfigs, string $name): string {
        $barcodeCounter = count($barcodeConfigs);
        // remove / and \ in filename
        $smartBarcodeLabel = $barcodeCounter === 1
            ? str_replace(['/', '\\'], '', $barcodeConfigs[0]['code'] ?: '')
            : '';

        return (
            PDFGeneratorService::PREFIX_BARCODE_FILENAME . '_' .
            $name .
            (($barcodeCounter === 1 && !empty($smartBarcodeLabel)) ? ('_' . $smartBarcodeLabel) : '') .
            '.pdf'
        );
    }

}
