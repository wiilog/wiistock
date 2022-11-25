<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Attachment;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\DeliveryRequest\Demande;
use App\Entity\Emplacement;
use App\Entity\FieldsParam;
use App\Entity\Livraison;
use App\Entity\Menu;
use App\Entity\Pack;
use App\Entity\PreparationOrder\PreparationOrderArticleLine;
use App\Entity\PreparationOrder\PreparationOrderReferenceLine;
use App\Entity\Setting;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Exceptions\NegativeQuantityException;
use App\Helper\FormatHelper;
use App\Service\CSVExportService;
use App\Service\LivraisonService;
use App\Service\LivraisonsManagerService;
use App\Service\PDFGeneratorService;
use App\Service\PreparationsManagerService;
use App\Service\SpecificService;
use App\Service\TranslationService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Throwable;
use WiiCommon\Helper\Stream;


/**
 * @Route("/livraison")
 */
class LivraisonController extends AbstractController
{
    /**
     * @Route("/liste/{demandId}", name="livraison_index", methods={"GET", "POST"})
     * @HasPermission({Menu::ORDRE, Action::DISPLAY_ORDRE_LIVR})
     */
    public function index(EntityManagerInterface $entityManager,
                          string $demandId = null): Response {

        $statutRepository = $entityManager->getRepository(Statut::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $demandeRepository = $entityManager->getRepository(Demande::class);

        $filterDemand = $demandId
            ? $demandeRepository->find($demandId)
            : null;

        return $this->render('livraison/index.html.twig', [
            'filterDemandId' => isset($filterDemand) ? $demandId : null,
            'filterDemandValue' => isset($filterDemand) ? $filterDemand->getNumero() : null,
            'filtersDisabled' => isset($filterDemand),
            'displayDemandFilter' => true,
            'statuts' => $statutRepository->findByCategorieName(CategorieStatut::ORDRE_LIVRAISON),
            'types' => $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_LIVRAISON]),
        ]);
    }

    /**
     * @Route("/finir/{id}", name="livraison_finish", options={"expose"=true}, methods={"POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::ORDRE, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function finish(Livraison $livraison,
                           LivraisonsManagerService $livraisonsManager,
                           EntityManagerInterface $entityManager): Response
    {
        if ($livraison->getStatut()?->getCode() === Livraison::STATUT_A_TRAITER) {
            try {
                $dateEnd = new DateTime('now');
                /** @var Utilisateur $user */
                $user = $this->getUser();
                $livraisonsManager->finishLivraison(
                    $user,
                    $livraison,
                    $dateEnd,
                    $livraison->getDemande()->getDestination()
                );
                $entityManager->flush();
            }
            catch(NegativeQuantityException $exception) {
                $barcode = $exception->getArticle()->getBarCode();
                return new JsonResponse([
                    'success' => false,
                    'message' => "La quantité en stock de l'article $barcode est inférieure à la quantité prélevée."
                ]);
            }
        }

        return new JsonResponse([
            'success' => true,
            'redirect' => $this->generateUrl('livraison_show', [
                'id' => $livraison->getId()
            ])
        ]);
    }

    /**
     * @Route("/api", name="livraison_api", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::ORDRE, Action::DISPLAY_ORDRE_LIVR}, mode=HasPermission::IN_JSON)
     */
    public function api(Request $request,
                        LivraisonService $livraisonService): Response
    {
        $filterDemandId = $request->request->get('filterDemand');
        $data = $livraisonService->getDataForDatatable($request->request, $filterDemandId);
        return new JsonResponse($data);
    }

    /**
     * @Route("/api-article/{id}", name="livraison_article_api", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::ORDRE, Action::DISPLAY_ORDRE_LIVR}, mode=HasPermission::IN_JSON)
     */
    public function apiArticle(Livraison $livraison): Response
    {
        $preparation = $livraison->getPreparation();
        $data = [];
        if ($preparation) {
            $rows = [];
            /** @var PreparationOrderArticleLine $articleLine */
            foreach ($preparation->getArticleLines() as $articleLine) {
                $article = $articleLine->getArticle();
                if ($articleLine->getQuantityToPick() !== 0 && $articleLine->getPickedQuantity() !== 0) {
                    $rows[] = [
                        "reference" => $article->getArticleFournisseur()->getReferenceArticle() ? $article->getArticleFournisseur()->getReferenceArticle()->getReference() : '',
                        "label" => $article->getLabel() ?: '',
                        "location" => FormatHelper::location($article->getEmplacement()),
                        "quantity" => $articleLine->getPickedQuantity(),
                        "Actions" => $this->renderView('livraison/datatableLivraisonListeRow.html.twig', [
                            'id' => $article->getId(),
                        ])
                    ];
                }
            }

            /** @var PreparationOrderReferenceLine $referenceLine */
            foreach ($preparation->getReferenceLines() as $referenceLine) {
                if ($referenceLine->getPickedQuantity() > 0) {
                    $reference = $referenceLine->getReference();
                    $rows[] = [
                        "reference" => $reference->getReference(),
                        "label" => $reference->getLibelle(),
                        "location" =>  FormatHelper::location($reference->getEmplacement()),
                        "quantity" => $referenceLine->getPickedQuantity(),
                        "Actions" => $this->renderView('livraison/datatableLivraisonListeRow.html.twig', [
                            'refArticleId' => $reference->getId(),
                        ])
                    ];
                }
            }

            $data['data'] = $rows;
        } else {
            $data = false; //TODO gérer retour message erreur
        }
        return new JsonResponse($data);
    }

    /**
     * @Route("/voir/{id}", name="livraison_show", methods={"GET","POST"})
     * @HasPermission({Menu::ORDRE, Action::DISPLAY_ORDRE_LIVR})
     */
    public function show(Livraison $livraison, EntityManagerInterface $manager): Response
    {
        $demande = $livraison->getDemande();

        $utilisateurPreparation = $livraison->getPreparation() ? $livraison->getPreparation()->getUtilisateur() : null;
        $destination = $demande ? $demande->getDestination() : null;
        $dateLivraison = $livraison->getDateFin();
        $comment = $demande->getCommentaire();

        return $this->render('livraison/show.html.twig', [
            'demande' => $demande,
            'livraison' => $livraison,
            'preparation' => $livraison->getPreparation(),
            'finished' => $livraison->isCompleted(),
            'headerConfig' => [
                [ 'label' => 'Numéro', 'value' => $livraison->getNumero() ],
                [ 'label' => 'Statut', 'value' => $livraison->getStatut() ? ucfirst($this->getFormatter()->status($livraison->getStatut())) : '' ],
                [ 'label' => 'Opérateur', 'value' => $utilisateurPreparation ? $utilisateurPreparation->getUsername() : '' ],
                [ 'label' => 'Demandeur', 'value' => FormatHelper::deliveryRequester($demande) ],
                [ 'label' => 'Point de livraison', 'value' => $destination ? $destination->getLabel() : '' ],
                [ 'label' => 'Date de livraison', 'value' => $dateLivraison ? $dateLivraison->format('d/m/Y') : '' ],
                [
                    'label' => 'Commentaire',
                    'value' => $comment ?: '',
                    'isRaw' => true,
                    'colClass' => 'col-sm-6 col-12',
                    'isScrollable' => true,
                    'isNeededNotEmpty' => true
                ]
            ]
        ]);
    }

    /**
     * @Route("/{livraison}", name="livraison_delete", options={"expose"=true}, methods={"DELETE"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::ORDRE, Action::DELETE}, mode=HasPermission::IN_JSON)
     */
    public function delete(Request $request,
                           Livraison $livraison,
                           LivraisonsManagerService $livraisonsManager,
                           PreparationsManagerService $preparationsManager,
                           EntityManagerInterface $entityManager): Response
    {
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);
        $preparation = $livraison->getpreparation();

        /** @var Utilisateur $user */
        $user = $this->getUser();

        $livraisonStatus = $livraison->getStatut();
        $demande = $livraison->getDemande();

        $articleDestinationId = $request->request->get('dropLocation');
        $articlesDestination = !empty($articleDestinationId) ? $emplacementRepository->find($articleDestinationId) : null;
        if (empty($articlesDestination)) {
            $articlesDestination = isset($demande) ? $demande->getDestination() : null;
        }

        if (isset($livraisonStatus) &&
            isset($articlesDestination)) {
            $livraisonsManager->resetStockMovementsOnDelete(
                $livraison,
                $articlesDestination,
                $user,
                $entityManager
            );
        }

        $preparationsManager->resetPreparationToTreat($preparation, $entityManager);

        $entityManager->flush();

        $preparation->setLivraison(null);
        $entityManager->remove($livraison);
        $entityManager->flush();

        return new JsonResponse ([
            'success' => true,
            'redirect' => $this->generateUrl('preparation_show', [
                'id' => $preparation->getId()
            ]),
        ]);
    }

    /**
     * @Route("/csv", name="get_delivery_order_csv", options={"expose"=true}, methods={"GET"})
     * @param Request $request
     * @param CSVExportService $CSVExportService
     * @param EntityManagerInterface $entityManager
     * @param LivraisonService $livraisonService
     * @return Response
     */
    public function getDeliveryOrderCSV(Request $request,
                                        CSVExportService $CSVExportService,
                                        EntityManagerInterface $entityManager,
                                        LivraisonService $livraisonService): Response
    {
        $dateMin = $request->query->get('dateMin');
        $dateMax = $request->query->get('dateMax');

        try {
            $dateTimeMin = DateTime::createFromFormat('Y-m-d H:i:s', $dateMin . ' 00:00:00');
            $dateTimeMax = DateTime::createFromFormat('Y-m-d H:i:s', $dateMax . ' 23:59:59');
        } catch (Throwable $throwable) {
        }
        if (isset($dateTimeMin) && isset($dateTimeMax)) {

            $csvHeader = [
                'numéro',
                'statut',
                'date création',
                'date de livraison',
                'date de la demande',
                'demandeur',
                'opérateur',
                'type',
                'commentaire',
                'référence',
                'libellé',
                'emplacement',
                'quantité à livrer',
                'quantité en stock',
                'code-barre'
            ];

            return $CSVExportService->streamResponse(
                function ($output) use ($entityManager, $dateTimeMin, $dateTimeMax, $CSVExportService, $livraisonService) {
                    $livraisonRepository = $entityManager->getRepository(Livraison::class);
                    $deliveryIterator = $livraisonRepository->iterateByDates($dateTimeMin, $dateTimeMax);

                    foreach ($deliveryIterator as $delivery) {
                        $livraisonService->putLivraisonLine($output, $CSVExportService, $delivery);
                    }
                }, 'export_Ordres_Livraison.csv',
                $csvHeader
            );
        }
        else {
            throw new NotFoundHttpException('404');
        }
    }

    /**
     * @Route("/{deliveryOrder}/api-delivery-note", name="api_delivery_note_livraison", options={"expose"=true}, methods="GET", condition="request.isXmlHttpRequest()")
     * @param Request $request
     * @param Livraison $deliveryOrder
     * @return JsonResponse
     */
    public function apiDeliveryNote(Request $request,
                                    EntityManagerInterface $manager,
                                    Livraison $deliveryOrder): JsonResponse {
        /** @var Utilisateur $loggedUser */
        $loggedUser = $this->getUser();
        $maxNumberOfPacks = 10;

        $settingRepository = $manager->getRepository(Setting::class);

        $preparationArticleLines = $deliveryOrder->getPreparation()->getArticleLines();
        $deliveryOrderHasPacks = Stream::from($preparationArticleLines)
            ->some(fn(PreparationOrderArticleLine $articleLine) => $articleLine->getPack());

        if(!$deliveryOrderHasPacks) {
            return $this->json([
                "success" => false,
                "msg" => 'Des unités logistiques sont nécessaires pour générer un bon de livraison'
            ]);
        }

        $preparationPacks = Stream::from($preparationArticleLines)
            ->map(fn(PreparationOrderArticleLine $articleLine) => $articleLine->getPack())->toArray();

        $packs = array_slice($preparationPacks, 0, $maxNumberOfPacks);
        $packs = array_map(function(Pack $pack) {
            return [
                "code" => $pack->getCode(),
                "quantity" => $pack->getQuantity(),
                "comment" => $pack->getComment(),
            ];
        }, $packs);

        $userSavedData = $loggedUser->getSavedDeliveryOrderDeliveryNoteData();
        $dispatchSavedData = $deliveryOrder->getDeliveryNoteData();
        $defaultData = [
            'deliveryNumber' => $deliveryOrder->getNumero(),
            'username' => $loggedUser->getUsername(),
            'packs' => $packs,
            'consignor' => $settingRepository->getOneParamByLabel(Setting::DISPATCH_WAYBILL_CONSIGNER),
            'consignor2' => $settingRepository->getOneParamByLabel(Setting::DISPATCH_WAYBILL_CONSIGNER),
            'userPhone' => $settingRepository->getOneParamByLabel(Setting::DISPATCH_WAYBILL_CONTACT_PHONE_OR_MAIL),
        ];

        $deliveryNoteData = array_reduce(
            array_keys(Livraison::DELIVERY_NOTE_DATA),
            function(array $carry, string $dataKey) use ($request, $userSavedData, $dispatchSavedData, $defaultData) {
                $carry[$dataKey] = (
                    $dispatchSavedData[$dataKey]
                    ?? ($userSavedData[$dataKey]
                        ?? ($defaultData[$dataKey]
                            ?? null))
                );

                return $carry;
            },
            []
        );

        $fieldsParamRepository = $manager->getRepository(FieldsParam::class);

        $html = $this->renderView('dispatch/modalPrintDeliveryNoteContent.html.twig', array_merge($deliveryNoteData, [
            'dispatchEmergencyValues' => $fieldsParamRepository->getElements(FieldsParam::ENTITY_CODE_DEMANDE, FieldsParam::FIELD_CODE_EMERGENCY),
        ]));

        return $this->json([
            "success" => true,
            "html" => $html
        ]);
    }

    /**
     * @Route("/{deliveryOrder}/delivery-note", name="delivery_note_delivery_order", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     * @param EntityManagerInterface $entityManager
     * @param Livraison $deliveryOrder
     * @param PDFGeneratorService $pdf
     * @param Request $request
     * @return JsonResponse
     */
    public function postDeliveryNoteDeliveryOrder(EntityManagerInterface $entityManager,
                                     Livraison $deliveryOrder,
                                     PDFGeneratorService $pdf,
                                     Request $request,
                                     SpecificService $specificService): JsonResponse {

        /** @var Utilisateur $loggedUser */
        $loggedUser = $this->getUser();

        $data = json_decode($request->getContent(), true);

        $userDataToSave = [];
        $dispatchDataToSave = [];

        // force dispatch number
        $data['deliveryNumber'] = $deliveryOrder->getNumero();

        foreach(array_keys(Livraison::DELIVERY_NOTE_DATA) as $deliveryNoteKey) {
            if(isset(Livraison::DELIVERY_NOTE_DATA[$deliveryNoteKey])) {
                $value = $data[$deliveryNoteKey] ?? null;
                $dispatchDataToSave[$deliveryNoteKey] = $value;
                if(Livraison::DELIVERY_NOTE_DATA[$deliveryNoteKey]) {
                    $userDataToSave[$deliveryNoteKey] = $value;
                }
            }
        }

        $loggedUser->setSavedDispatchDeliveryNoteData($userDataToSave);
        $deliveryOrder->setDeliveryNoteData($dispatchDataToSave);

        $entityManager->flush();

        $settingRepository = $entityManager->getRepository(Setting::class);
        $logo = $settingRepository->getOneParamByLabel(Setting::DELIVERY_NOTE_LOGO);

        $nowDate = new DateTime('now');
        $client = SpecificService::CLIENTS[$specificService->getAppClient()];

        $documentTitle = "BL - {$deliveryOrder->getNumero()} - {$client} - {$nowDate->format('dmYHis')}";

        $fileName = $pdf->generatePDFDeliveryNote($documentTitle, $logo, $deliveryOrder);

        $deliveryNoteAttachment = new Attachment();
        $deliveryNoteAttachment
            ->setDeliveryOrder($deliveryOrder)
            ->setFileName($fileName)
            ->setOriginalName($documentTitle . '.pdf');

        $entityManager->persist($deliveryNoteAttachment);

        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'msg' => 'Le téléchargement de votre bon de livraison va commencer...',
            'attachmentId' => $deliveryNoteAttachment->getId()
        ]);
    }

    /**
     * @Route("/{deliveryOrder}/delivery-note/{attachment}", name="print_delivery_note_delivery_order", options={"expose"=true}, methods="GET")
     * @param Livraison $deliveryOrder
     * @param KernelInterface $kernel
     * @param Attachment $attachment
     * @return PdfResponse
     */
    public function printDeliveryNote(Livraison $deliveryOrder,
                                      KernelInterface $kernel,
                                      Attachment $attachment): Response {
        if(!$deliveryOrder->getDeliveryNoteData()) {
            return $this->json([
                "success" => false,
                "msg" => 'Le bon de livraison n\'existe pas pour cette ordre de livraison'
            ]);
        }

        $response = new BinaryFileResponse(($kernel->getProjectDir() . '/public/uploads/attachements/' . $attachment->getFileName()));
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $attachment->getOriginalName());

        return $response;
    }
}
