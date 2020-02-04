<?php


namespace App\Service;

use Twig\Environment as Twig_Environment;
use Knp\Snappy\PDF as PDFGenerator;

Class PDFBarcodeGeneratorService
{
    /** @var GlobalParamService  */
	private $globalParamService;

	/** @var Twig_Environment */
	private $templating;

	/** @var $PDFGenerator */
	private $PDFGenerator;

	public function __construct(GlobalParamService $globalParamService,
                                PDFGenerator $PDFGenerator,
                                Twig_Environment $templating) {
	    $this->globalParamService = $globalParamService;
	    $this->templating = $templating;
	    $this->PDFGenerator = $PDFGenerator;
	}

    // TODO throw error if dimension do not exists
	public function generatePDFBarcode(string $code, array $labels = []): string {
        $barcodeConfig = $this->globalParamService->getDimensionAndTypeBarcodeArray(true);
        $labels = array_merge([$code], $labels);

        $longestLabels = array_reduce($labels, function ($carry, $label) {
            $currentLen = strlen($label);
            return strlen($label) > $carry ? $currentLen : $carry;
        }, 0);

        $labelsFontSize = ($barcodeConfig['width'] < 28)
            ? ($longestLabels < 25 ? 50 : ($longestLabels < 35 ? 40 : 35))
            : ( $longestLabels <= 45 ? 65 : ($longestLabels <= 85 ? 55 : 50)
        );

        $height = $barcodeConfig['height'];
        $width = $barcodeConfig['width'];
        $isCode128 = $barcodeConfig['isCode128'];

        return $this->PDFGenerator->getOutputFromHtml(
             $this->templating->render('barcodes/print.html.twig', [
                'height' => $height,
                'width' => $width,
                'barcode' => [
                    'code' => $code,
                    'type' => $isCode128 ? 'c128' : 'qrcode',
                    'width' => $isCode128 ? 1 : 48,
                    'height' => $isCode128 ? 1 : 48,
                    'sizeProperty' => $isCode128 ? 'width' : 'height'
                ],
                'labelsFontSize' => $labelsFontSize . '%',
                'labels' => array_filter($labels, function ($label) {
                    return !empty($label);
                })
            ]),
            [
                'page-height' => "${height}mm",
                'page-width' => "${width}mm",
                'margin-top' => '0in',
                'margin-right' => '0in',
                'margin-bottom' => '0in',
                'margin-left' => '0in',
                'encoding' => 'UTF-8',
                'no-outline' => true,
                'disable-smart-shrinking' => true,
            ]
        );
    }

}
