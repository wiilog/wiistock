<?php


namespace App\Service;

use App\Entity\Dispatch;
use App\Entity\ParametrageGlobal;
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

Class PDFGeneratorService
{

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
                                EntityManagerInterface $entityManager)
    {
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
     * @param null $arrival
     * @return string
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function generatePDFBarCodes(string $title, array $barcodeConfigs, $arrival = null): string
    {
        $barcodeConfig = $this->globalParamService->getDimensionAndTypeBarcodeArray(true);
        $parametrageGlobalRepository = $this->entityManager->getRepository(ParametrageGlobal::class);

        $height = $barcodeConfig['height'];
        $width = $barcodeConfig['width'];
        $isCode128 = $barcodeConfig['isCode128'];

        $barcodeConfigsToTwig = array_map(function ($config) use ($isCode128, $width) {
            $code = $config['code'];
            $labels = array_filter($config['labels'] ?? [], function ($label) {
                return !empty($label);
            });

            $longestLabel = array_reduce($labels, function ($carry, $label) {
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
                'labels' => $labels
            ];
        }, $barcodeConfigs);

        $logo = ($barcodeConfig['logo'] && file_exists(getcwd() . "/uploads/attachements/" . $barcodeConfig['logo'])
                ? $barcodeConfig['logo']
                : null);

        $showCustomIcon = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::INCLUDE_CUSTOMS_IN_LABEL);

        $customIcon = ($barcodeConfig['custom-icon'] && file_exists(getcwd() . "/uploads/attachements/" . $barcodeConfig['custom-icon'])
            ? $barcodeConfig['custom-icon']
            : null);

        $customText = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::CUSTOM_TEXT_LABEL);

        $showEmergencyIcon = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::INCLUDE_EMERGENCY_IN_LABEL);

        $emergencyIcon = ($barcodeConfig['emergency-icon'] && file_exists(getcwd() . "/uploads/attachements/" . $barcodeConfig['emergency-icon'])
            ? $barcodeConfig['emergency-icon']
            : null);

        $emergencyText = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::EMERGENCY_TEXT_LABEL);

        return $this->PDFGenerator->getOutputFromHtml(
            $this->templating->render('prints/barcode-template.html.twig', [
                'logo' => $logo,
                'showCustomIcon' => $showCustomIcon,
                'showEmergencyIcon' => $showEmergencyIcon,
                'customIcon' => $customIcon,
                'customText' => $customText,
                'emergencyIcon' => $emergencyIcon,
                'emergencyText' => $emergencyText,
                'arrival' => $arrival,
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
    public function generatePDFStateSheet(string $title, array $sheetConfigs): string
    {
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
     * @param array $barcodeConfigs ['code' => string][]
     * @param string $name
     * @return string
     */
    public function getBarcodeFileName(array $barcodeConfigs, string $name): string
    {
        $barcodeCounter = count($barcodeConfigs);

        return (
            PDFGeneratorService::PREFIX_BARCODE_FILENAME . '_' .
            $name .
            ($barcodeCounter === 1 ? ('_' . $barcodeConfigs[0]['code']) : '') .
            '.pdf'
        );
    }
}
