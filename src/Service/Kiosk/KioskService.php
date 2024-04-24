<?php

namespace App\Service\Kiosk;

use App\Entity\Article;
use App\Entity\Kiosk;
use App\Entity\Project;
use App\Entity\TagTemplate;
use App\Service\ArticleDataService;
use App\Service\FormatService;
use App\Service\PDFGeneratorService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use SGK\BarcodeBundle\Generator\Generator as BarcodeGenerator;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment as Twig_Environment;

class KioskService
{

//    #[Required]
//    public Generator $barcodeGenerator;
//
    #[Required]
    public FormatService $formatService;

    #[Required]
    public EntityManagerInterface $manager;

    #[Required]
    public Twig_Environment $templating;

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

    public function getDataForDatatable(InputBag $params): array {
        $queryResult = $this->manager->getRepository(Kiosk::class)->findByParams($params);

        $kiosks = $queryResult['data'];

        $rows = [];
        foreach ($kiosks as $kiosk) {
            $rows[] = $this->dataRowProject($kiosk);
        }

        return [
            'data' => $rows,
            'recordsTotal' => $queryResult['total'],
            'recordsFiltered' => $queryResult['count'],
        ];
    }

    public function getActionsConfig(Kiosk $kiosk): array{
        return [
            [
                "title" => "Modifier",
                "icon" => "fas fa-pencil-alt",
                "attributes" => [
                    "data-id" => $kiosk->getId(),
                    "data-target" => "#editKioskModal",
                    "data-toggle" => "modal",
                    "onclick" => "editRow($(this), Routing.generate('edit_kiosk_api', true), $(`#editKioskModal`), $('#editKioskSubmit'))"
                ],
            ],
            [
                "title" => "Supprimer",
                "icon" => "wii-icon wii-icon-trash-black",
                "attributes" => [
                    "data-id" => $kiosk->getId(),
                    "onclick" => "deleteKiosk(".$kiosk->getId().")",
                ],
            ],
        ];
    }

    public function dataRowProject(Kiosk $kiosk): array {
        $kioskHaveToken = $kiosk->getToken() !== null;
        $unlinkConfig =
            [
                "title" => "Déconnecter",
                "icon" => "wii-icon wii-icon-link-slash mr-2",
                "class" => "unlink-kiosk",
                "attributes" => [
                    "data-id" => $kiosk->getId(),
                    "onclick" => "unlinkKiosk(".$kiosk->getId().", $(this))",
                ],
            ];
        $actionsConfig = $this->getActionsConfig($kiosk);

        if($kioskHaveToken) {
            array_unshift($actionsConfig, $unlinkConfig);
        }

        return [
            'pickingType' => $this->formatService->type($kiosk->getPickingType()),
            'name' => $kiosk->getName(),
            'pickingLocation' => $this->formatService->location($kiosk->getPickingLocation()),
            'requester' => $this->formatService->user($kiosk->getRequester()),
            'externalLink' => $this->templating->render('kiosk/datatable/redirectLink.html.twig', [
                'id' => $kiosk->getId(),
            ]),
            'actions' => $this->templating->render("utils/action-buttons/dropdown.html.twig", [
                "actions" => $actionsConfig,
            ]),
        ];
    }
}
