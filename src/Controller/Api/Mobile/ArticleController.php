<?php

namespace App\Controller\Api\Mobile;

use App\Annotation as Wii;
use App\Controller\AbstractController;
use App\Entity\Article;
use App\Entity\ArticleFournisseur;
use App\Entity\CategorieStatut;
use App\Entity\Emplacement;
use App\Entity\MouvementStock;
use App\Entity\NativeCountry;
use App\Entity\ReferenceArticle;
use App\Entity\Setting;
use App\Entity\Statut;
use App\Entity\Tracking\Pack;
use App\Entity\Tracking\TrackingMovement;
use App\Entity\Type;
use App\Exceptions\FormException;
use App\Service\ArticleDataService;
use App\Service\MouvementStockService;
use App\Service\SettingsService;
use App\Service\TrackingMovementService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use WiiCommon\Helper\Stream;

#[Route("/api/mobile")]
class ArticleController extends AbstractController {

    #[Route("/create-article", methods: [self::POST], condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestVersionChecked]
    public function postArticle(Request                 $request,
                                EntityManagerInterface  $entityManager,
                                ArticleDataService      $articleDataService,
                                SettingsService         $settingsService,
                                MouvementStockService   $mouvementStockService,
                                TrackingMovementService $trackingMovementService): Response
    {
        $articleRepository = $entityManager->getRepository(Article::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $statusRepository = $entityManager->getRepository(Statut::class);
        $locationRepository = $entityManager->getRepository(Emplacement::class);
        $nativeCountryRepository = $entityManager->getRepository(NativeCountry::class);
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $supplierArticleRepository = $entityManager->getRepository(ArticleFournisseur::class);

        $rfidPrefix = $settingsService->getValue($entityManager, Setting::RFID_PREFIX);
        $defaultLocationId = $settingsService->getValue($entityManager, Setting::ARTICLE_LOCATION);
        $defaultLocation = $defaultLocationId ? $locationRepository->find($defaultLocationId) : null;

        $now = new DateTime('now');

        $rfidTag = $request->request->get('rfidTag');
        $countryStr = $request->request->get('country');
        $destinationStr = $request->request->get('destination');
        $referenceStr = $request->request->get('reference');

        if (empty($rfidTag)) {
            throw new FormException("Le tag RFID est invalide.");
        }

        if (!empty($rfidPrefix) && !str_starts_with($rfidTag, $rfidPrefix)) {
            throw new FormException("Le tag RFID ne respecte pas le préfixe paramétré ($rfidPrefix).");
        }
        $article = $articleRepository->findOneBy(['RFIDtag' => $rfidTag]);

        if ($article) {
            throw new FormException("Tag RFID déjà existant en base.");
        }

        $typeStr = $request->request->get('type');
        $type = $typeStr
            ? $typeRepository->find($typeStr)
            : null;

        $statut = $statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::ARTICLE, Article::STATUT_ACTIF);

        $fromMatrix = $request->request->getBoolean('fromMatrix');
        $destination = !empty($destinationStr)
            ? ($fromMatrix
                ? ($locationRepository->findOneBy(['label' => $destinationStr]) ?: $defaultLocation)
                : $locationRepository->find($destinationStr))
            : null;

        if (!$destination) {
            throw new FormException("L'emplacement de destination de l'article est inconnu.");
        }

        $countryFrom = !empty($countryStr)
            ? $nativeCountryRepository->findOneBy(['code' => $countryStr])
            : null;
        if (!$countryFrom && $countryStr) {
            throw new FormException("Le code pays est inconnu");
        }

        if ($fromMatrix) {
            $ref = $referenceArticleRepository->findOneBy([
                'reference' => $referenceStr,
                'typeQuantite' => ReferenceArticle::QUANTITY_TYPE_ARTICLE,
            ]);
        } else {
            $ref = $referenceArticleRepository->find($referenceStr);
            $articleSupplier = $supplierArticleRepository->find($request->request->get('supplier_reference'));
        }
        if (!$ref) {
            throw new FormException("Référence scannée ({$referenceStr}) inconnue.");
        } else if ($fromMatrix) {
            $type = $ref->getType();
            if ($ref->getArticlesFournisseur()->isEmpty()) {
                throw new FormException("La référence scannée ({$referenceStr}) n'a pas d'article fournisseur paramétré.");
            } else {
                $articleSupplier = $ref->getArticlesFournisseur()->first();
            }
        }
        $refTypeLabel = $ref->getType()->getLabel();
        if ($ref->getType()?->getId() !== $type?->getId()) {
            throw new FormException("Le type selectionné est différent de celui de la référence ({$refTypeLabel})");
        }

        if (!$articleSupplier) {
            throw new FormException("Référence fournisseur inconnue.");
        }

        $expiryDateStr = $request->request->get('expiryDate');
        $expiryDate = $expiryDateStr
            ? ($fromMatrix
                ? DateTime::createFromFormat('dmY', $expiryDateStr)
                : new DateTime($expiryDateStr))
            : null;

        $manufacturingDateStr = $request->request->get('manufacturingDate');
        $manufacturingDate = $manufacturingDateStr
            ? ($fromMatrix
                ? DateTime::createFromFormat('dmY', $manufacturingDateStr)
                : new DateTime($manufacturingDateStr))
            : null;

        $productionDateStr = $request->request->get('productionDate');
        $productionDate = $productionDateStr
            ? ($fromMatrix
                ? DateTime::createFromFormat('dmY', $productionDateStr)
                : new DateTime($productionDateStr))
            : null;

        $labelStr = $request->request->get('label');
        $commentStr = $request->request->get('comment');
        $priceStr = $request->request->get('price');
        $quantityStr = $request->request->getInt('quantity');
        $deliveryLineStr = $request->request->get('deliveryLine');
        $commandNumberStr = $request->request->get('commandNumber');
        $batchStr = $request->request->get('batch');

        $article = new Article();
        $article
            ->setLabel($labelStr)
            ->setConform(true)
            ->setStatut($statut)
            ->setCommentaire(!empty($commentStr) ? $commentStr : null)
            ->setPrixUnitaire(floatval($priceStr))
            ->setReference($ref)
            ->setQuantite($quantityStr)
            ->setEmplacement($destination)
            ->setArticleFournisseur($articleSupplier)
            ->setType($type)
            ->setBarCode($articleDataService->generateBarcode())
            ->setStockEntryDate($now)
            ->setDeliveryNote($deliveryLineStr)
            ->setNativeCountry($countryFrom)
            ->setProductionDate($productionDate)
            ->setManufacturedAt($manufacturingDate)
            ->setPurchaseOrder($commandNumberStr)
            ->setRFIDtag($rfidTag)
            ->setBatch($batchStr)
            ->setExpiryDate($expiryDate);

        $entityManager->persist($article);

        $stockMovement = $mouvementStockService->createMouvementStock(
            $this->getUser(),
            null,
            $article->getQuantite(),
            $article,
            MouvementStock::TYPE_ENTREE
        );

        $mouvementStockService->finishStockMovement(
            $stockMovement,
            $now,
            $article->getEmplacement()
        );

        $entityManager->persist($stockMovement);

        $trackingMovement = $trackingMovementService->createTrackingMovement(
            $article->getTrackingPack() ?: $article->getBarCode(),
            $article->getEmplacement(),
            $this->getUser(),
            $now,
            true,
            true,
            TrackingMovement::TYPE_DEPOSE,
            [
                'refOrArticle' => $article,
                'mouvementStock' => $stockMovement,
            ]
        );

        $entityManager->persist($trackingMovement);
        $entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Article bien généré.'
        ]);
    }

    #[Route("/logistic-unit/articles", methods: [self::GET], condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestVersionChecked]
    public function getLogisticUnitArticles(Request $request, EntityManagerInterface $entityManager): Response
    {
        $packRepository = $entityManager->getRepository(Pack::class);

        $code = $request->query->get('code');

        $pack = $packRepository->findOneBy(['code' => $code]);

        if ($pack) {
            $articles = $pack->getChildArticles()
                ->map(static fn(Article $article) => [
                    'id' => $article->getId(),
                    'barCode' => $article->getBarCode(),
                    'label' => $article->getLabel(),
                    'location' => $article->getEmplacement()?->getLabel(),
                    'quantity' => $article->getQuantite(),
                    'reference' => $article->getReference()
                ])
                ->toArray();
        }
        else {
            $articles = [];
        }

        return $this->json($articles);
    }

    #[Route("/articles", methods: [self::GET], condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestVersionChecked]
    public function getArticles(Request $request, EntityManagerInterface $entityManager): Response
    {
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $articleRepository = $entityManager->getRepository(Article::class);
        $packRepository = $entityManager->getRepository(Pack::class);
        $statutRepository = $entityManager->getRepository(Statut::class);

        $referenceActiveStatusId = $statutRepository
            ->findOneByCategorieNameAndStatutCode(ReferenceArticle::CATEGORIE, ReferenceArticle::STATUT_ACTIF)
            ->getId();

        $resData = [];

        $barCode = $request->query->get('barCode');
        $barcodes = $request->query->get('barcodes');
        $location = $request->query->get('location');
        $createIfNotExist = $request->query->get('createIfNotExist');

        if (!empty($barcodes)) {
            $barcodes = json_decode($barcodes, true);
            $articles = Stream::from($articleRepository->findBy(['barCode' => $barcodes]))
                ->map(fn(Article $article) => [
                    'barcode' => $article->getBarCode(),
                    'quantity' => $article->getQuantite(),
                    'reference' => $article->getArticleFournisseur()->getReferenceArticle()->getReference()
                ])->toArray();

            return $this->json([
                'articles' => $articles
            ]);
        } else if (!empty($barCode)) {
            $statusCode = Response::HTTP_OK;
            $referenceArticle = $referenceArticleRepository->findOneBy([
                'barCode' => $barCode,
            ]);
            if (!empty($referenceArticle) && (!$location || $referenceArticle->getEmplacement()->getLabel() === $location)) {
                $statusReferenceArticle = $referenceArticle->getStatut();
                $statusReferenceId = $statusReferenceArticle ? $statusReferenceArticle->getId() : null;
                // we can transfer if reference is active AND it is not linked to any active orders
                $referenceArticleArray = [
                    'can_transfer' => (
                        ($statusReferenceId === $referenceActiveStatusId)
                        && !$referenceArticleRepository->isUsedInQuantityChangingProcesses($referenceArticle)
                    ),
                    "id" => $referenceArticle->getId(),
                    "barCode" => $referenceArticle->getBarCode(),
                    "quantity" => $referenceArticle->getQuantiteDisponible(),
                    "is_ref" => "1"
                ];
                $resData['article'] = $referenceArticleArray;
            } else {
                $article = $articleRepository->getOneArticleByBarCodeAndLocation($barCode, $location);

                if (!empty($article)) {
                    $canAssociate = in_array($article['articleStatusCode'], [Article::STATUT_ACTIF, Article::STATUT_EN_LITIGE]);

                    $article['can_transfer'] = ($article['reference_status'] === ReferenceArticle::STATUT_ACTIF);
                    $article['can_associate'] = $canAssociate;
                    $resData['article'] = $canAssociate ? $article : null;
                } else {
                    $pack = $packRepository->getOneArticleByBarCodeAndLocation($barCode, $location);
                    if (!empty($pack)) {
                        $pack["can_transfer"] = 1;
                        $pack["articles"] = $pack["articles"] ? explode(";", $pack["articles"]) : null;
                    } else if ($createIfNotExist) {
                        $pack = [
                            'barCode' => $barCode,
                            'articlesCount' => null,
                            'is_lu' => "1",
                            'project' => null,
                            'location' => null,
                            'is_ref' => 0,
                        ];
                    }
                    $resData['article'] = $pack;
                }
            }

            if (!empty($resData['article'])) {
                $resData['article']['is_ref'] = (int)$resData['article']['is_ref'];
            }

            $resData['success'] = !empty($resData['article']);
        } else {
            throw new BadRequestHttpException();
        }
        return new JsonResponse($resData, $statusCode);
    }

    #[Route("/article-by-rfid-tag/{rfid}", methods: [self::GET], condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestVersionChecked]
    public function getArticleByRFIDTag(EntityManagerInterface $entityManager, string $rfid): Response
    {
        $article = $entityManager->getRepository(Article::class)->findOneBy([
            'RFIDtag' => $rfid
        ]);
        return $this->json([
            'success' => true,
            'article' => $article?->getId()
        ]);
    }

    #[Route("/default-article-values", methods: [self::GET], condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestVersionChecked]
    public function getDefaultArticleValues(EntityManagerInterface $entityManager,
                                            SettingsService        $settingsService): Response
    {
        $locationRepository = $entityManager->getRepository(Emplacement::class);
        $articleDefaultLocationId = $settingsService->getValue($entityManager, Setting::ARTICLE_LOCATION);
        $articleDefaultLocation = $articleDefaultLocationId ? $locationRepository->find($articleDefaultLocationId) : null;

        $defaultValues = [
            'destination' => $articleDefaultLocation?->getId(),
            'type' => $settingsService->getValue($entityManager, Setting::ARTICLE_TYPE),
            'reference' => $settingsService->getValue($entityManager, Setting::ARTICLE_REFERENCE),
            'label' => $settingsService->getValue($entityManager, Setting::ARTICLE_LABEL),
            'quantity' => $settingsService->getValue($entityManager, Setting::ARTICLE_QUANTITY),
            'supplier' => $settingsService->getValue($entityManager, Setting::ARTICLE_SUPPLIER),
            'supplierReference' => $settingsService->getValue($entityManager, Setting::ARTICLE_SUPPLIER_REFERENCE),
        ];

        return $this->json([
            'success' => true,
            'defaultValues' => $defaultValues
        ]);
    }

}
