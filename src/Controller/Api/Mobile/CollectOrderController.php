<?php

namespace App\Controller\Api\Mobile;

use App\Annotation as Wii;
use App\Controller\AbstractController;
use App\Entity\Article;
use App\Entity\Emplacement;
use App\Entity\OrdreCollecte;
use App\Entity\ReferenceArticle;
use App\Entity\Tracking\TrackingMovement;
use App\Exceptions\ArticleNotAvailableException;
use App\Repository\Tracking\TrackingMovementRepository;
use App\Service\ExceptionLoggerService;
use App\Service\OrdreCollecteService;
use App\Service\Tracking\TrackingMovementService;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

#[Route("/api/mobile")]
class CollectOrderController extends AbstractController {

    #[Route("/beginCollecte", methods: [self::POST], condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestVersionChecked]
    public function beginCollecte(Request                $request,
                                  EntityManagerInterface $entityManager)
    {
        $nomadUser = $this->getUser();

        $ordreCollecteRepository = $entityManager->getRepository(OrdreCollecte::class);

        $id = $request->request->get('id');
        $ordreCollecte = $ordreCollecteRepository->find($id);

        $data = [];

        if ($ordreCollecte->getStatut()?->getCode() == OrdreCollecte::STATUT_A_TRAITER &&
            (empty($ordreCollecte->getUtilisateur()) || $ordreCollecte->getUtilisateur() === $nomadUser)) {
            // modif de la collecte
            $ordreCollecte->setUtilisateur($nomadUser);

            $entityManager->flush();

            $data['success'] = true;
        } else {
            $data['success'] = false;
            $data['msg'] = "Cette collecte a déjà été prise en charge par un opérateur.";
        }

        return new JsonResponse($data);
    }

    #[Route("/finishCollecte", methods: [self::POST], condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestVersionChecked]
    public function finishCollecte(Request                   $request,
                                   ExceptionLoggerService    $exceptionLoggerService,
                                   OrdreCollecteService      $ordreCollecteService,
                                   TrackingMovementService   $trackingMovementService,
                                   EntityManagerInterface    $entityManager): JsonResponse
    {
        $nomadUser = $this->getUser();

        $statusCode = Response::HTTP_OK;

        $resData = ['success' => [], 'errors' => [], 'data' => []];

        $collectes = json_decode($request->request->get('collectes'), true);
        if (!$collectes) {
            $jsonData = json_decode($request->getContent(), true);
            $collectes = $jsonData['collectes'];
        }
        $trackingMovementRepository = $entityManager->getRepository(TrackingMovement::class);
        $articleRepository = $entityManager->getRepository(Article::class);
        $refArticlesRepository = $entityManager->getRepository(ReferenceArticle::class);
        $ordreCollecteRepository = $entityManager->getRepository(OrdreCollecte::class);
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);

        // on termine les collectes
        foreach ($collectes as $collecteArray) {
            $collecte = $ordreCollecteRepository->find($collecteArray['id']);
            try {
                $entityManager->transactional(function ()
                use (
                    $trackingMovementService,
                    $entityManager,
                    $collecteArray,
                    $collecte,
                    $nomadUser,
                    &$resData,
                    $trackingMovementRepository,
                    $articleRepository,
                    $refArticlesRepository,
                    $ordreCollecteRepository,
                    $emplacementRepository,
                    $ordreCollecteService
                ) {
                    $ordreCollecteService->setEntityManager($entityManager);
                    $date = DateTime::createFromFormat(DateTimeInterface::ATOM, $collecteArray['date_end']);

                    foreach ($collecteArray['mouvements'] as $collectMovement) {
                        if ($collectMovement['is_ref'] == 0) {
                            $barcode = $collectMovement['barcode'];
                            $pickedQuantity = $collectMovement['quantity'];
                            if ($barcode) {
                                $isInCollect = !$collecte
                                    ->getArticles()
                                    ->filter(fn(Article $article) => $article->getBarCode() === $barcode)
                                    ->isEmpty();

                                if (!$isInCollect) {
                                    /** @var Article $article */
                                    $article = $articleRepository->findOneBy(['barCode' => $barcode]);
                                    if ($article) {
                                        $article->setQuantite($pickedQuantity);
                                        $collecte->addArticle($article);

                                        $referenceArticle = $article->getArticleFournisseur()->getReferenceArticle();
                                        foreach ($collecte->getOrdreCollecteReferences() as $ordreCollecteReference) {
                                            if ($ordreCollecteReference->getReferenceArticle() === $referenceArticle) {
                                                $ordreCollecteReference->setQuantite($ordreCollecteReference->getQuantite() - $pickedQuantity);
                                                if ($ordreCollecteReference->getQuantite() === 0) {
                                                    $collecte->removeOrdreCollecteReference($ordreCollecteReference);
                                                    $entityManager->remove($ordreCollecteReference);
                                                }
                                                break;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                    $newCollecte = $ordreCollecteService->finishCollecte($collecte, $nomadUser, $date, $collecteArray['mouvements'], true);
                    $entityManager->flush();

                    if (!empty($newCollecte)) {
                        $newCollecteId = $newCollecte->getId();
                        $newCollecteArray = $ordreCollecteRepository->getById($newCollecteId);

                        $articlesCollecte = $articleRepository->getByOrdreCollecteId($newCollecteId);
                        $refArticlesCollecte = $refArticlesRepository->getByOrdreCollecteId($newCollecteId);
                        $articlesCollecte = array_merge($articlesCollecte, $refArticlesCollecte);
                    }

                    $resData['success'][] = [
                        'numero_collecte' => $collecte->getNumero(),
                        'id_collecte' => $collecte->getId(),
                    ];

                    $newTakings = $trackingMovementService->getMobileUserPicking(
                        $entityManager,
                        $nomadUser,
                        TrackingMovementRepository::MOUVEMENT_TRACA_STOCK,
                        [$collecte->getId()]
                    );

                    if (!empty($newTakings)) {
                        if (!isset($resData['data']['stockTakings'])) {
                            $resData['data']['stockTakings'] = [];
                        }
                        array_push(
                            $resData['data']['stockTakings'],
                            ...$newTakings
                        );
                    }

                    if (isset($newCollecteArray)) {
                        if (!isset($resData['data']['newCollectes'])) {
                            $resData['data']['newCollectes'] = [];
                        }
                        $resData['data']['newCollectes'][] = $newCollecteArray;
                    }

                    if (!empty($articlesCollecte)) {
                        if (!isset($resData['data']['articlesCollecte'])) {
                            $resData['data']['articlesCollecte'] = [];
                        }
                        array_push(
                            $resData['data']['articlesCollecte'],
                            ...$articlesCollecte
                        );
                    }
                });
            } catch (Throwable $throwable) {
                // we create a new entity manager because transactional() can call close() on it if transaction failed
                if (!$entityManager->isOpen()) {
                    $entityManager = new EntityManager($entityManager->getConnection(), $entityManager->getConfiguration());
                    $ordreCollecteService->setEntityManager($entityManager);

                    $trackingMovementRepository = $entityManager->getRepository(TrackingMovement::class);
                    $articleRepository = $entityManager->getRepository(Article::class);
                    $refArticlesRepository = $entityManager->getRepository(ReferenceArticle::class);
                    $ordreCollecteRepository = $entityManager->getRepository(OrdreCollecte::class);
                    $emplacementRepository = $entityManager->getRepository(Emplacement::class);
                }

                $user = $collecte->getUtilisateur() ? $collecte->getUtilisateur()->getUsername() : '';

                $message = (
                ($throwable instanceof ArticleNotAvailableException) ? ("Une référence de la collecte n'est pas active, vérifiez les transferts de stock en cours associés à celle-ci.") :
                    (($throwable->getMessage() === OrdreCollecteService::COLLECTE_ALREADY_BEGUN) ? ("La collecte " . $collecte->getNumero() . " a déjà été effectuée (par " . $user . ").") :
                        (($throwable->getMessage() === OrdreCollecteService::COLLECTE_MOUVEMENTS_EMPTY) ? ("La collecte " . $collecte->getNumero() . " ne contient aucun article.") :
                            false))
                );

                if (!$message) {
                    $exceptionLoggerService->sendLog($throwable);
                }

                $resData['errors'][] = [
                    'numero_collecte' => $collecte->getNumero(),
                    'id_collecte' => $collecte->getId(),

                    'message' => $message ?: 'Une erreur est survenue',
                ];
            }
        }

        return new JsonResponse($resData, $statusCode);
    }

    #[Route("/collectable-articles", methods: [self::GET], condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestVersionChecked]
    public function getCollectableArticles(Request                $request,
                                           EntityManagerInterface $entityManager): Response
    {
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $articleRepository = $entityManager->getRepository(Article::class);

        $reference = $request->query->get('reference');
        $barcode = $request->query->get('barcode');

        /** @var ReferenceArticle $referenceArticle */
        $referenceArticle = $referenceArticleRepository->findOneBy(['reference' => $reference]);

        if ($referenceArticle) {
            return $this->json(['articles' => $articleRepository->getCollectableMobileArticles($referenceArticle, $barcode)]);
        } else {
            throw new NotFoundHttpException();
        }
    }
}
