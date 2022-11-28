<?php

namespace App\Service\Kiosk;

use App\Entity\Article;
use App\Entity\Setting;
use Doctrine\ORM\EntityManagerInterface;
use SGK\BarcodeBundle\Generator\Generator as BarcodeGenerator;
use ZplGenerator\Client\CloudClient;
use ZplGenerator\Elements\Codes\QrCode;
use ZplGenerator\Elements\Common\Align;
use ZplGenerator\Elements\Image;
use ZplGenerator\Elements\Text\Text;
use ZplGenerator\Printer\Printer;

class KioskService
{

//    #[Required]
//    public Generator $barcodeGenerator;

    private $barcodeGenerator;

    public function __construct(BarcodeGenerator $barcodeGenerator) {
        $this->barcodeGenerator = $barcodeGenerator;
    }

    public function printLabel($options, EntityManagerInterface $entityManager): void
    {
        $settingRepository = $entityManager->getRepository(Setting::class);

        // kiosk settings
        $printerSerialNumber = key_exists('serialNumber', $options) ? $options['serialNumber'] : $settingRepository->getOneParamByLabel('PRINTER_SERIAL_NUMBER');
        $printerLabelWidth = key_exists('labelWidth', $options) ? $options['labelWidth'] : $settingRepository->getOneParamByLabel('PRINTER_LABEL_WIDTH');
        $printerLabelHeight = key_exists('labelHeight', $options) ? $options['labelHeight'] :$settingRepository->getOneParamByLabel('PRINTER_LABEL_HEIGHT');
        $printerDpi = key_exists('printerDPI', $options) ? $options['printerDPI'] :$settingRepository->getOneParamByLabel('PRINTER_DPI');

        // local variables
        $zebraCloudApiKey = $_SERVER['ZEBRA_CLOUD_API_KEY'];
        $zebraCloudTenant = $_SERVER['ZEBRA_CLOUD_TENANT'];

        // global settings
        $logo = $settingRepository->getOneParamByLabel('LABEL_LOGO');
        $labelTypeIs128 = $settingRepository->getOneParamByLabel('BARCORE_TYPE');

        if ($labelTypeIs128) {
            $image = $this->printBarcodeFunction([
                'code' => $options['barcode'],
                'type' => 'c128',
                'format' => 'png',
                'height' => 70,
                'width' => 3
            ]);
            $code = Image::fromString(0, 20, base64_decode($image));
        }
        else {
            $code = QrCode::create(32, 0)
                ->setContent($options['barcode'])
                ->setSize(6)
                ->setAlignment(Align::LEFT)
                ->setErrorCorrection(QrCode::EC_HIGHEST);
        }

        $text = Text::create(0, 45)
            ->setText($options['text'])
            ->setAlignment(Align::CENTER)
            ->setSpacing(10)
            ->setMaxLines(1000);

        $client = CloudClient::create($zebraCloudApiKey, $zebraCloudTenant, $printerSerialNumber);

        $printer = Printer::create()
            ->setDimension($printerLabelWidth, $printerLabelHeight)
            ->setDPI($printerDpi);

        $label = $printer->createLabel()
            ->with($code)
            ->with($text);

        if ($logo && file_exists($logo)) {
            $logo = Image::fromPath(0, 0, $logo)
                ->setAlignment(Align::LEFT)
                ->setHeight(15);

            $label->with($logo);
        }

        $printer->print($client, $label);
    }

    public function convertLocation( $size , $location): float|int
    {
        return $size * $location / 100 ;
    }

    public function getTextForLabel(Article $article, EntityManagerInterface $entityManager ) {
        $settingRepository = $entityManager->getRepository(Setting::class);

        $referenceArticle = $article->getReferenceArticle();

        $labelText = $article->getBarCode() ? $article->getBarCode() . '\&' : '';
        $labelText .= $referenceArticle?->getLibelle() ? ' L/R :' . $referenceArticle?->getLibelle() . '\&' : '';
        $labelText .= $article->getReference() ? 'C/R :' . $article->getReference() . '\&' : '';
        $labelText .= $article->getLabel() ? 'L/A :' . $article->getLabel() . '\&' : '';

        return str_replace('_', '_5F', $labelText);
    }

    public function printBarcodeFunction($options = []): string {
        return $this->barcodeGenerator->generate($options);
    }
}
