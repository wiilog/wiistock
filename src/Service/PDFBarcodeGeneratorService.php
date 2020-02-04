<?php


namespace App\Service;

use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Twig\Environment as Twig_Environment;
use Knp\Snappy\PDF as PDFGenerator;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

Class PDFBarcodeGeneratorService
{

    public const PREFIX_BARCODE_FILENAME = 'ETQ';

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

    /**
     * @param string $title
     * @param array $barcodeConfigs Array of ['code' => string, 'labels' => array]. labels optional
     * @return string
     * @throws LoaderError
     * @throws NoResultException
     * @throws NonUniqueResultException
     * @throws RuntimeError
     * @throws SyntaxError
     */
	public function generatePDFBarCodes(string $title, array $barcodeConfigs): string {
        $barcodeConfig = $this->globalParamService->getDimensionAndTypeBarcodeArray(true);

        $height = $barcodeConfig['height'];
        $width = $barcodeConfig['width'];
        $isCode128 = $barcodeConfig['isCode128'];

	    $barcodeConfigsToTwig = array_map(function ($config) use ($isCode128, $width) {
            $code = $config['code'];
            $labels = array_merge([$code], $config['labels'] ?? []);

            $longestLabels = array_reduce($labels, function ($carry, $label) {
                $currentLen = strlen($label);
                return strlen($label) > $carry ? $currentLen : $carry;
            }, 0);

            $labelsFontSize = ($width < 28)
                ? ($longestLabels < 25 ? 50 : ($longestLabels < 35 ? 40 : 35))
                : ( $longestLabels <= 45 ? 65 : ($longestLabels <= 85 ? 55 : 50)
            );
	        return [
                'barcode' => [
                    'code' => $code,
                    'type' => $isCode128 ? 'c128' : 'qrcode',
                    'width' => $isCode128 ? 1 : 48,
                    'height' => 48
                ],
                'labelsFontSize' => $labelsFontSize . '%',
                'labels' => array_filter($labels, function ($label) {
                    return !empty($label);
                })
            ];
        }, $barcodeConfigs);

        return $this->PDFGenerator->getOutputFromHtml(
             $this->templating->render('barcodes/print.html.twig', [
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
}
