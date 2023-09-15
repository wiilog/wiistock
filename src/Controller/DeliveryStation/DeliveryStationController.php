<?php

namespace App\Controller\DeliveryStation;

use App\Annotation\HasValidToken;
use App\Controller\AbstractController;
use App\Entity\ArticleFournisseur;
use App\Entity\CategorieCL;
use App\Entity\CategoryType;
use App\Entity\DeliveryRequest\Demande;
use App\Entity\FreeField;
use App\Entity\KioskToken;
use App\Entity\ReferenceArticle;
use App\Entity\Setting;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Entity\VisibilityGroup;
use App\Service\AttachmentService;
use App\Service\DeliveryRequestService;
use App\Service\Kiosk\KioskService;
use App\Service\LivraisonService;
use DateInterval;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\Article;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Environment;
use WiiCommon\Helper\Stream;


/**
 * @Route("/caisse-automatique")
 */
class DeliveryStationController extends AbstractController
{

    #[Route("/", name: "delivery_station_index", options: ["expose" => true])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $type = $entityManager->getRepository(Type::class)->findByCategoryLabelsAndLabels([CategoryType::DEMANDE_LIVRAISON], ['L - Consommable'])[0];
        $visibilityGroup = $entityManager->getRepository(VisibilityGroup::class)->findBy([])[0] ?? null;

        $rawMessage = "Demande de livraison simplifiée sur le flux @typelivraison et groupe de visibilité @groupevisibilite";
        $homeMessage = str_replace('@typelivraison', "<strong>{$type->getLabel()}</strong>", str_replace('@groupevisibilite', "<strong>{$visibilityGroup->getLabel()}</strong>", $rawMessage));

        return $this->render('delivery_station/home.html.twig', [
            'homeMessage' => $homeMessage,
            'type' => $type->getId(),
            'visibilityGroup' => $visibilityGroup?->getId(),
        ]);
    }

    #[Route("/login/{mobileLoginKey}", name: "delivery_station_login", options: ["expose" => true], methods: "POST")]
    public function login(string $mobileLoginKey, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $entityManager->getRepository(Utilisateur::class)->findOneBy(['mobileLoginKey' => $mobileLoginKey]);
        return $this->json([
            'success' => !!$user,
        ]);
    }

    #[Route("/formulaire", name: "delivery_station_form", options: ["expose" => true])]
    public function form(Request $request, EntityManagerInterface $entityManager): Response
    {
        $request = $request->query;

        $type = $entityManager->getRepository(Type::class)->find($request->get('type'));
        $visibilityGroup = $entityManager->getRepository(VisibilityGroup::class)->find($request->get('visibilityGroup'));
        $freeFields = [];
        return $this->render('delivery_station/form.html.twig', [
            'freeFields' => $freeFields,
            'form' => true,
            'type' => $type->getLabel(),
            'visibilityGroup' => $visibilityGroup->getLabel(),
        ]);
    }

    #[Route("/get-informations", name: "delivery_station_get_informations", options: ["expose" => true], methods: "GET")]
    public function getReferenceInformations(Request                $request,
                                             EntityManagerInterface $entityManager): JsonResponse
    {
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $initialReference = $referenceArticleRepository->find($request->query->get('reference'));
        $barcode = $request->query->has('barcode')
            ? $request->query->get('barcode')
            : null;

        if ($barcode) {
            if (str_starts_with($barcode, Article::BARCODE_PREFIX)) {
                $article = $entityManager->getRepository(Article::class)->findOneBy(['barCode' => $barcode]);

                if($article->isAvailable()) {
                    if($article->getReferenceArticle()->getId() === $initialReference->getId()) {
                        $values = [
                            'location' => $this->formatService->location($article->getEmplacement()),
                            'suppliers' => $article->getArticleFournisseur()?->getFournisseur()?->getCodeReference() ?: '-',
                        ];
                    } else {
                        return $this->json([
                            'success' => false,
                            'msg' => "L'article renseigné n'est pas lié à la référence sélectionée.",
                        ]);
                    }
                } else {
                    return $this->json([
                        'success' => false,
                        'msg' => "L'article sélectionné n'est pas disponible.",
                    ]);
                }
            } elseif (str_starts_with($barcode, ReferenceArticle::BARCODE_PREFIX)) {
                $reference = $referenceArticleRepository->findOneBy(['barCode' => $barcode]);

                if($reference->getId() === $initialReference->getId()) {
                    $values = [
                        'location' => $this->formatService->location($reference->getEmplacement()),
                        'suppliers' => Stream::from($reference->getArticlesFournisseur())
                            ->map(static fn(ArticleFournisseur $supplierArticle) => $supplierArticle->getFournisseur()->getCodeReference())
                            ->join(','),
                    ];
                } else {
                    return $this->json([
                        'success' => false,
                        'msg' => "La référence renseignée doit être identique à celle sélectionnée au début du processus.",
                    ]);
                }

            } else {
                return $this->json([
                    'success' => false,
                    'msg' => "Le code barre renseigné ne correspond à aucun article ou référence.",
                ]);
            }
        } else {
            $values = [
                'id' => $initialReference->getId(),
                'reference' => $initialReference->getReference(),
                'label' => $initialReference->getLibelle(),
                'stockQuantity' => $initialReference->getQuantiteDisponible(),
                'barcode' => $initialReference->getBarCode(),
                'image' => $initialReference->getImage()
                    ? "{$initialReference->getImage()->getFullPath()}"
                    : "",
            ];
        }

        return $this->json([
            'success' => true,
            'values' => $values,
        ]);
    }

    #[Route("/get-free-fields", name: "delivery_station_get_free_fields", options: ["expose" => true], methods: "GET")]
    public function getFreeFields(EntityManagerInterface $entityManager): JsonResponse
    {
        $type = $entityManager->getRepository(Type::class)->findByCategoryLabelsAndLabels([CategoryType::DEMANDE_LIVRAISON], ['L - Consommable'])[0]; // TODO A remplacer par le paramétrage
        $freeFields = $entityManager->getRepository(FreeField::class)->findByTypeAndCategorieCLLabel($type, CategorieCL::DEMANDE_LIVRAISON);

        return $this->json([
            'empty' => empty($freeFields),
            'template' => !empty($freeFields) ?
                $this->renderView('free_field/freeFieldsEdit.html.twig', [
                    'freeFields' => $freeFields,
                    'freeFieldValues' => null,
                    'colType' => "col-6",
                    'actionType' => "new",
                    'disabledNeeded' => true,
                ]) : "",
        ]);
    }

    #[Route("/submit-request", name: "delivery_station_submit_request", options: ["expose" => true], methods: "POST")]
    public function submitRequest(Request                $request,
                                  EntityManagerInterface $entityManager,
                                  DeliveryRequestService $deliveryRequestService,
                                  LivraisonService       $deliveryOrderService): JsonResponse
    {
        $data = $request->request->all();
        dump($data);
        /*$request = $deliveryRequestService->newDemande([
            'isManual' => true,
            'type' => $data['type'],
            'demandeur' => $nomadUser,
            'destination' => $location['id'],
            'expectedAt' => $delivery['expectedAt'] ?? $now->format('Y-m-d'),
            'project' => $delivery['project'] ?? null,
            'commentaire' => $delivery['comment'] ?? null,
        ], $entityManager, $freeFieldService, true);

        $entityManager->persist($request);*/

        return $this->json([
            'success' => true,
        ]);
    }
}
