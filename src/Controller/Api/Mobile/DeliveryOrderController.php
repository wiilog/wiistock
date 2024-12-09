<?php

namespace App\Controller\Api\Mobile;

use App\Annotation as Wii;
use App\Controller\AbstractController;
use App\Entity\Article;
use App\Entity\DeliveryRequest\DeliveryRequestArticleLine;
use App\Entity\Emplacement;
use App\Entity\Livraison;
use App\Entity\Tracking\Pack;
use App\Service\ExceptionLoggerService;
use App\Service\LivraisonsManagerService;
use App\Service\TranslationService;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;
use WiiCommon\Helper\Stream;

#[Route("/api/mobile")]
class DeliveryOrderController extends AbstractController {


    #[Route("/beginLivraison", methods: [self::POST], condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestVersionChecked]
    public function beginLivraison(Request                $request,
                                   EntityManagerInterface $entityManager,
                                   TranslationService     $translation)
    {
        $nomadUser = $this->getUser();

        $livraisonRepository = $entityManager->getRepository(Livraison::class);

        $id = $request->request->get('id');
        $livraison = $livraisonRepository->find($id);

        $data = [];

        if ($livraison->getStatut()?->getCode() == Livraison::STATUT_A_TRAITER &&
            (empty($livraison->getUtilisateur()) || $livraison->getUtilisateur() === $nomadUser)) {
            // modif de la livraison
            $livraison->setUtilisateur($nomadUser);

            $entityManager->flush();

            $data['success'] = true;
        } else {
            $data['success'] = false;
            $data['msg'] = "Cette " . mb_strtolower($translation->translate("Demande", "Livraison", "Livraison", false)) . " a déjà été prise en charge par un opérateur.";
        }

        return new JsonResponse($data);
    }
    #[Route("/finishLivraison", methods: [self::POST], condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestVersionChecked]
    public function finishLivraison(Request                  $request,
                                    ExceptionLoggerService   $exceptionLoggerService,
                                    EntityManagerInterface   $entityManager,
                                    LivraisonsManagerService $livraisonsManager,
                                    TranslationService       $translation)
    {
        $nomadUser = $this->getUser();

        $statusCode = Response::HTTP_OK;
        $livraisonRepository = $entityManager->getRepository(Livraison::class);
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);

        $livraisons = json_decode($request->request->get('livraisons'), true);
        $resData = ['success' => [], 'errors' => []];

        // on termine les livraisons
        // même comportement que LivraisonController.finish()
        foreach ($livraisons as $livraisonArray) {
            $livraison = $livraisonRepository->find($livraisonArray['id']);

            if ($livraison) {
                $dateEnd = DateTime::createFromFormat(DateTimeInterface::ATOM, $livraisonArray['date_end']);
                $location = $emplacementRepository->findOneBy(['label' => $livraisonArray['location']]);
                try {
                    if ($location) {
                        // flush auto at the end
                        $livraisonsManager->setEntityManager($entityManager);
                        $livraisonsManager->finishLivraison($nomadUser, $livraison, $dateEnd, $location);
                        $entityManager->flush();

                        $resData['success'][] = [
                            'numero_livraison' => $livraison->getNumero(),
                            'id_livraison' => $livraison->getId(),
                        ];
                    } else {
                        throw new Exception(LivraisonsManagerService::MOUVEMENT_DOES_NOT_EXIST_EXCEPTION);
                    }
                } catch (Throwable $throwable) {
                    // we create a new entity manager because transactional() can call close() on it if transaction failed
                    if (!$entityManager->isOpen()) {
                        $entityManager = new EntityManager($entityManager->getConnection(), $entityManager->getConfiguration());
                        $livraisonsManager->setEntityManager($entityManager);
                    }

                    $message = match ($throwable->getMessage()) {
                        LivraisonsManagerService::MOUVEMENT_DOES_NOT_EXIST_EXCEPTION => "L'emplacement que vous avez sélectionné n'existe plus.",
                        LivraisonsManagerService::LIVRAISON_ALREADY_BEGAN => "La " . mb_strtolower($translation->translate("Ordre", "Livraison", "Livraison", false)) . " a déjà été commencée",
                        default => false,
                    };

                    if ($throwable->getCode() === LivraisonsManagerService::NATURE_NOT_ALLOWED) {
                        $message = $throwable->getMessage();
                    }

                    if (!$message) {
                        $exceptionLoggerService->sendLog($throwable, $request);
                    }

                    $resData['errors'][] = [
                        'numero_livraison' => $livraison->getNumero(),
                        'id_livraison' => $livraison->getId(),

                        'message' => $message ?: 'Une erreur est survenue',
                    ];
                }
            }
        }

        return new JsonResponse($resData, $statusCode);
    }

    #[Route("/check-delivery-logistic-unit-content", methods: [self::GET], condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestVersionChecked]
    public function checkDeliveryLogisticUnitContent(Request                $request,
                                                     EntityManagerInterface $entityManager): Response
    {
        $delivery = $entityManager->getRepository(Livraison::class)->findOneBy(['id' => $request->query->get('livraisonId')]);

        $articlesLines = $delivery->getDemande()->getArticleLines();
        $numberArticlesInLU = Stream::from($articlesLines)
            ->filter(fn(DeliveryRequestArticleLine $line) => $line->getPack())
            ->keymap(fn(DeliveryRequestArticleLine $line) => [$line->getPack()->getCode(), $line->getPack()->getChildArticles()->count()])
            ->toArray();

        return $this->json([
            'numberArticlesInLU' => $numberArticlesInLU
        ]);
    }


    #[Route("/check-logistic-unit-content", methods: [self::GET], condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestVersionChecked]
    public function checkLogisticUnitContent(Request                $request,
                                             EntityManagerInterface $entityManager): Response
    {
        $logisticUnit = $entityManager->getRepository(Pack::class)->findOneBy(['code' => $request->query->get('logisticUnit')]);
        $articlesAllInTransit = Stream::from($logisticUnit->getChildArticles())->every(fn(Article $article) => $article->isInTransit());
        $articlesNotInTransit = [];
        if (!$articlesAllInTransit) {

            $articlesNotInTransit = Stream::from($logisticUnit->getChildArticles())
                ->filter(fn(Article $article) => !$article->isInTransit())
                ->map(fn(Article $article) => [
                    'barcode' => $article->getBarCode(),
                    'reference' => $article->getReference(),
                    'quantity' => $article->getQuantite(),
                    'label' => $article->getLabel(),
                    'location' => $article->getEmplacement()->getLabel(),
                    'currentLogisticUnitCode' => $article->getCurrentLogisticUnit()->getCode(),
                    'selected' => true
                ])
                ->values();
        }

        return $this->json([
            'extraArticles' => $articlesNotInTransit
        ]);
    }
}
