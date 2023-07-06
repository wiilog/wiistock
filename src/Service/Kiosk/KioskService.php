<?php

namespace App\Service\Kiosk;

use App\Entity\Article;
use App\Entity\TagTemplate;
use App\Service\ArticleDataService;
use App\Service\PDFGeneratorService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use SGK\BarcodeBundle\Generator\Generator as BarcodeGenerator;
use Symfony\Component\HttpFoundation\Response;

class KioskService
{

//    #[Required]
//    public Generator $barcodeGenerator;
//
    private $articleDataService;

    private $PDFGeneratorService;

    private $barcodeGenerator;

    public function __construct(BarcodeGenerator $barcodeGenerator, ArticleDataService $articleDataService, PDFGeneratorService $PDFGeneratorService) {
        $this->barcodeGenerator = $barcodeGenerator;
        $this->articleDataService = $articleDataService;
        $this->PDFGeneratorService = $PDFGeneratorService;
    }


    public function getTextForLabel(Article $article, EntityManagerInterface $entityManager ) {
        $referenceArticle = $article->getReferenceArticle();

        $labelText = $article->getBarCode() ? $article->getBarCode() . '\n' : '';
        $labelText .= $referenceArticle?->getLibelle() ? ' L/R :' . $referenceArticle?->getLibelle() . '\n' : '';
        $labelText .= $article->getReference() ? 'C/R :' . $article->getReference() . '\n' : '';
        $labelText .= $article->getLabel() ? 'L/A :' . $article->getLabel() . '\n' : '';

        return str_replace('_', '_5F', $labelText);
    }

    public function testPrintWiispool(array $options, ?Article $article = null): ?Response
    {
        $barcodeConfig = $article ? [$this->articleDataService->getBarcodeConfig($article, null, true)] : [[
            'code' => $options[0]['barcode'],
            'labels' => [$options[0]['text']],
        ]];

        $fileName = $this->PDFGeneratorService->getBarcodeFileName(
            $barcodeConfig,
            'article',
            'BRN'
        );

        return new PdfResponse(
            $this->PDFGeneratorService->generatePDFBarCodes($fileName, $barcodeConfig),
            $fileName
        );
    }
}
