<?php

namespace App\Service\Kiosk;

use App\Entity\Article;
use App\Entity\FiltreRef;
use App\Entity\ReferenceArticle;
use App\Entity\Setting;
use App\Entity\Utilisateur;
use App\Service\PDFGeneratorService;
use App\Service\RefArticleDataService;
use Doctrine\ORM\EntityManagerInterface;
use ZplGenerator\Client\CloudClient;
use ZplGenerator\Client\SocketClient;
use ZplGenerator\Elements\Codes\BarCode128;
use ZplGenerator\Elements\Codes\QrCode;
use ZplGenerator\Elements\Common\Align;
use ZplGenerator\Elements\Container;
use ZplGenerator\Elements\Image;
use ZplGenerator\Elements\Text\Text;
use ZplGenerator\Elements\Text\TextConfig;
use ZplGenerator\Printer\Printer;

class KioskService
{
    public function printLabel($options, EntityManagerInterface $entityManager): void
    {
        $settingRepository = $entityManager->getRepository(Setting::class);

        // kiosk settings
        $printerName = $settingRepository->getOneParamByLabel('PRINTER_NAME');
        $printerSerialNumber = $settingRepository->getOneParamByLabel('PRINTER_SERIAL_NUMBER');
        $printerLabelWidth = $settingRepository->getOneParamByLabel('PRINTER_LABEL_WIDTH');
        $printerLabelHeight = $settingRepository->getOneParamByLabel('PRINTER_LABEL_HEIGHT');
        $printerDpi = $settingRepository->getOneParamByLabel('PRINTER_DPI');

        // local variables
        $zebraCloudApiKey = $_SERVER['ZEBRA_CLOUD_API_KEY'];
        $zebraCloudTenant = $_SERVER['ZEBRA_CLOUD_TENANT'];

        // global settings
        $logo = $settingRepository->getOneParamByLabel('LABEL_LOGO');
        $labelTypeIs128 = $settingRepository->getOneParamByLabel('BARCORE_TYPE');

        if ($logo) {
            $logo = Image::fromPath(0, 0, $logo)
                ->setAlignment(Align::LEFT)
                ->setHeight($this->convertLocation($printerLabelHeight, 15));
        }

        if ($labelTypeIs128){
            $code = BarCode128::create($this->convertLocation( $printerLabelWidth, 25), $this->convertLocation( $printerLabelHeight, 20))
                ->setContent($options['barcode'])
                ->setAlignment(Align::CENTER)
                ->setDisplayText(true)
                ->setTextConfig(new TextConfig(null, 4, null))
                ->setTextPosition(false)
                ->setWidth($this->convertLocation($printerLabelWidth, 100))
                ->setHeight($this->convertLocation($printerLabelHeight, 100));
        }
        else {
            $code = QrCode::create(0,  $this->convertLocation($printerLabelHeight, 20))
                ->setContent($options['barcode'])
                ->setSize(10)
                ->setAlignment(Align::CENTER)
                ->setErrorCorrection(QrCode::EC_HIGHEST);
        }

        $text = Text::create(0, 50)
            ->setText($options['text'])
            ->setAlignment(Align::CENTER)
            ->setWidth(100)
            ->setMaxLines(1000);

        $client = CloudClient::create($zebraCloudApiKey, $zebraCloudTenant, $printerSerialNumber);

        $printer = Printer::create()
            ->setDimension($printerLabelWidth, $printerLabelHeight)
            ->setDPI($printerDpi);

        $label = $printer->createLabel()
            ->with($code)
            ->with($text);

        if (isset($logo)) {
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

        $showQuantity = $settingRepository->getOneParamByLabel('INCLURE_QTT_SUR_ETIQUETTE');
        $showEntryDate = $settingRepository->getOneParamByLabel('INCLURE_DATE_EXPIRATION_SUR_ETIQUETTE_ARTICLE_RECEPTION');
        $showBatchNumber = $settingRepository->getOneParamByLabel('INCLURE_NUMERO_DE_LOT_SUR_ETIQUETTE_ARTICLE_RECEPTION');

        $labelText = $referenceArticle?->getLibelle() ? ' L/R ' . $referenceArticle?->getLibelle() . '\&' : '';
        $labelText .= $article->getReference() ? 'C/R :' . $article->getReference() . '\&' : '';
        $labelText .= $article->getLabel() ? 'L/A :' . $article->getLabel() . '\&' : '';
        $labelText .= $showQuantity && $article->getQuantite() ? 'Qte : ' . $article->getQuantite() . '\&' : '';
        $labelText .= $showEntryDate && $article->getStockEntryDate() ? 'Date d\'entrée : ' . $article->getStockEntryDate()?->format('d/m/Y') . '\&' : '';
        $labelText .= $showBatchNumber && $article->getBatch() ? 'N° lot : ' . $article->getBatch() . '\&' : '';

        return $labelText;
    }
}
