<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\ChampLibre;
use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\Menu;
use App\Entity\MouvementTraca;
use App\Entity\ParametrageGlobal;
use App\Entity\PieceJointe;

use App\Entity\Statut;
use App\Entity\Utilisateur;

use App\Service\AttachmentService;
use App\Service\CSVExportService;
use App\Service\FilterSupService;
use App\Service\FreeFieldService;
use App\Service\MouvementTracaService;
use App\Service\SpecificService;
use App\Service\UserService;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\FileBag;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use DateTime;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @Route("/mouvement-traca")
 */
class MouvementTracaController extends AbstractController
{

    /**
     * @var UserService
     */
    private $userService;

    /**
     * @var AttachmentService
     */
    private $attachmentService;

    /**
     * @var MouvementTracaService
     */
    private $mouvementTracaService;

    /**
     * MouvementTracaController constructor.
     * @param MouvementTracaService $mouvementTracaService
     * @param AttachmentService $attachmentService
     * @param UserService $userService
     */
    public function __construct(MouvementTracaService $mouvementTracaService,
                                AttachmentService $attachmentService,
                                UserService $userService)
    {
        $this->userService = $userService;
        $this->attachmentService = $attachmentService;
        $this->mouvementTracaService = $mouvementTracaService;
    }

    /**
     * @Route("/", name="mvt_traca_index", options={"expose"=true})
     * @param EntityManagerInterface $entityManager
     * @param FilterSupService $filterSupService
     * @param Request $request
     * @return RedirectResponse|Response
     * @throws NonUniqueResultException
     */
    public function index(EntityManagerInterface $entityManager,
                          FilterSupService $filterSupService,
                          Request $request)
    {
        if (!$this->userService->hasRightFunction(Menu::TRACA, Action::DISPLAY_MOUV)) {
            return $this->redirectToRoute('access_denied');
        }
        $filtreSupRepository = $entityManager->getRepository(FiltreSup::class);
        $statutRepository = $entityManager->getRepository(Statut::class);
        $parametrageGlobalRepository = $entityManager->getRepository(ParametrageGlobal::class);
        $champLibreRepository = $entityManager->getRepository(ChampLibre::class);

        $packFilter = $request->query->get('colis');
        if (!empty($packFilter)) {
            /** @var Utilisateur $loggedUser */
            $loggedUser = $this->getUser();
            $filtreSupRepository->clearFiltersByUserAndPage($loggedUser, FiltreSup::PAGE_MVT_TRACA);
            $entityManager->flush();
            $filter = $filterSupService->createFiltreSup(FiltreSup::PAGE_MVT_TRACA, FiltreSup::FIELD_COLIS, $packFilter, $loggedUser);
            $entityManager->persist($filter);
            $entityManager->flush();
        }

        $redirectAfterTrackingMovementCreation = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::CLOSE_AND_CLEAR_AFTER_NEW_MVT);

        return $this->render('mouvement_traca/index.html.twig', [
            'statuts' => $statutRepository->findByCategorieName(CategorieStatut::MVT_TRACA),
            'redirectAfterTrackingMovementCreation' => (int)($redirectAfterTrackingMovementCreation ? !$redirectAfterTrackingMovementCreation->getValue() : true),
            'champsLibres' => $champLibreRepository->findByCategoryTypeLabels([CategoryType::MOUVEMENT_TRACA]),
        ]);
    }

    private function errorWithDropOff($pack, $location, $packTranslation, $natureTranslation) {
        $bold = '<span class="font-weight-bold"> ';
        return 'Le ' . $packTranslation . $bold . $pack . '</span> ne dispose pas des ' . $natureTranslation . ' pour être déposé sur l\'emplacement' . $bold . $location . '</span>.';
    }

    /**
     * @Route("/creer", name="mvt_traca_new", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param MouvementTracaService $mouvementTracaService
     * @param FreeFieldService $freeFieldService
     * @param EntityManagerInterface $entityManager
     * @param TranslatorInterface $translator
     * @return Response
     * @throws Exception
     */
    public function new(Request $request,
                        MouvementTracaService $mouvementTracaService,
                        FreeFieldService $freeFieldService,
                        EntityManagerInterface $entityManager,
                        TranslatorInterface $translator): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::TRACA, Action::CREATE)) {
                return $this->redirectToRoute('access_denied');
            }

            $post = $request->request;

            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
            $emplacementRepository = $entityManager->getRepository(Emplacement::class);

            $packTranslation = $translator->trans('arrivage.colis');
            $natureTranslation = $translator->trans('natures.natures requises');

            $operatorId = $post->get('operator');
            if (!empty($operatorId)) {
                $operator = $utilisateurRepository->find($operatorId);
            }
            if (empty($operator)) {
                /** @var Utilisateur $operator */
                $operator = $this->getUser();
            }

            $colisStr = $post->get('colis');
            $commentaire = $post->get('commentaire');
            $quantity = $post->getInt('quantity') ?: 1;

            if ($quantity < 1) {
                return new JsonResponse([
                    'success' => false,
                    'msg' => 'La quantité doit être supérieure à 0.'
                ]);
            }

            $date = new DateTime($post->get('datetime') ?: 'now', new \DateTimeZone('Europe/Paris'));
            $fromNomade = false;
            $fileBag = $request->files->count() > 0 ? $request->files : null;

            $codeToPack = [];
            $createdMouvements = [];

            if (empty($post->get('is-mass'))) {
                $emplacement = $emplacementRepository->find($post->get('emplacement'));
                $createdMvt = $mouvementTracaService->createTrackingMovement(
                    $colisStr,
                    $emplacement,
                    $operator,
                    $date,
                    $fromNomade,
                    null,
                    $post->getInt('type'),
                    [
                        'commentaire' => $commentaire,
                        'quantity' => $quantity
                    ]
                );

                $movementType = $createdMvt->getType();
                $movementTypeName = $movementType ? $movementType->getNom() : null;

                // Dans le cas d'une dépose, on vérifie si l'emplacement peut accueillir le colis
                if ($movementTypeName === MouvementTraca::TYPE_DEPOSE && !$emplacement->ableToBeDropOff($createdMvt->getPack())) {
                    return new JsonResponse([
                        'success' => false,
                        'msg' => $this->errorWithDropOff($colisStr, $emplacement, $packTranslation, $natureTranslation)
                    ]);
                }
                $mouvementTracaService->persistSubEntities($entityManager, $createdMvt);
                $entityManager->persist($createdMvt);
                $createdMouvements[] = $createdMvt;
            }
            else {
                $colisArray = explode(',', $colisStr);
                $emplacementPrise = $emplacementRepository->find($post->get('emplacement-prise'));
                $emplacementDepose = $emplacementRepository->find($post->get('emplacement-depose'));
                foreach ($colisArray as $colis) {
                    $createdMvt = $this->mouvementTracaService->createTrackingMovement(
                        isset($codeToPack[$colis]) ? $codeToPack[$colis] : $colis,
                        $emplacementPrise,
                        $operator,
                        $date,
                        $fromNomade,
                        true,
                        MouvementTraca::TYPE_PRISE,
                        [
                            'commentaire' => $commentaire,
                            'quantity' => $quantity
                        ]
                    );

                    $mouvementTracaService->persistSubEntities($entityManager, $createdMvt);
                    $entityManager->persist($createdMvt);
                    $createdMouvements[] = $createdMvt;
                    $createdPack = $createdMvt->getPack();

                    $createdMvt = $this->mouvementTracaService->createTrackingMovement(
                        $createdPack,
                        $emplacementDepose,
                        $operator,
                        $date,
                        $fromNomade,
                        true,
                        MouvementTraca::TYPE_DEPOSE,
                        [
                            'commentaire' => $commentaire,
                            'quantity' => $quantity
                        ]
                    );

                    // Dans le cas d'une dépose, on vérifie si l'emplacement peut accueillir le colis
                    if (!$emplacementDepose->ableToBeDropOff($createdPack)) {
                        return new JsonResponse([
                            'success' => false,
                            'msg' => $this->errorWithDropOff($createdPack->getCode(), $emplacementDepose, $packTranslation, $natureTranslation)
                        ]);
                    }

                    $mouvementTracaService->persistSubEntities($entityManager, $createdMvt);
                    $entityManager->persist($createdMvt);
                    $createdMouvements[] = $createdMvt;
                    $codeToPack[$colis] = $createdPack;
                }
            }

            if (isset($fileBag)) {
                $fileNames = [];
                foreach ($fileBag->all() as $file) {
                    $fileNames = array_merge(
                        $fileNames,
                        $this->attachmentService->saveFile($file)
                    );
                }
                foreach ($createdMouvements as $mouvement) {
                    $this->persistAttachments($mouvement, $this->attachmentService, $fileNames, $entityManager);
                }
            }
            foreach ($createdMouvements as $mouvement) {
                $freeFieldService->manageFreeFields($mouvement, $post->all(), $entityManager);
            }
            $entityManager->flush();

            $countCreatedMouvements = count($createdMouvements);

            return new JsonResponse([
                'success' => $countCreatedMouvements > 0,
                'mouvementTracaCounter' => $countCreatedMouvements
            ]);
        }
        throw new NotFoundHttpException('404 not found');
    }

    /**
     * @Route("/api", name="mvt_traca_api", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @return Response
     * @throws Exception
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::TRACA, Action::DISPLAY_MOUV)) {
                return $this->redirectToRoute('access_denied');
            }

            $data = $this->mouvementTracaService->getDataForDatatable($request->request);

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/api-modifier", name="mvt_traca_api_edit", options={"expose"=true}, methods="GET|POST")
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @return Response
     */
    public function editApi(EntityManagerInterface $entityManager,
                            Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::TRACA, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $statutRepository = $entityManager->getRepository(Statut::class);
            $mouvementTracaRepository = $entityManager->getRepository(MouvementTraca::class);
            $champLibreRepository = $entityManager->getRepository(ChampLibre::class);

            $mvt = $mouvementTracaRepository->find($data['id']);

            $json = $this->renderView('mouvement_traca/modalEditMvtTracaContent.html.twig', [
                'mvt' => $mvt,
                'statuts' => $statutRepository->findByCategorieName(CategorieStatut::MVT_TRACA),
                'attachments' => $mvt->getAttachments(),
                'champsLibres' => $champLibreRepository->findByCategoryTypeLabels([CategoryType::MOUVEMENT_TRACA]),
            ]);

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/modifier", name="mvt_traca_edit", options={"expose"=true}, methods="GET|POST")
     * @param EntityManagerInterface $entityManager
     * @param MouvementTracaService $mouvementTracaService
     * @param FreeFieldService $freeFieldService
     * @param Request $request
     * @return Response
     */
    public function edit(EntityManagerInterface $entityManager,
                         FreeFieldService $freeFieldService,
                         Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::TRACA, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $post = $request->request;

            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
            $mouvementTracaRepository = $entityManager->getRepository(MouvementTraca::class);

            $operator = $utilisateurRepository->find($post->get('operator'));
            $quantity = $post->getInt('quantity') ?: 1;

            if ($quantity < 1) {
                return new JsonResponse([
                    'success' => false,
                    'msg' => 'La quantité doit être supérieure à 0.'
                ]);
            }

            /** @var MouvementTraca $mvt */
            $mvt = $mouvementTracaRepository->find($post->get('id'));
            $mvt
                ->setOperateur($operator)
                ->setQuantity($quantity)
                ->setCommentaire($post->get('commentaire'));

            $entityManager->flush();

            $listAttachmentIdToKeep = $post->get('files');

            $attachments = $mvt->getAttachments()->toArray();
            foreach ($attachments as $attachment) {
                /** @var PieceJointe $attachment */
                if (!$listAttachmentIdToKeep || !in_array($attachment->getId(), $listAttachmentIdToKeep)) {
                    $this->attachmentService->removeAndDeleteAttachment($attachment, $mvt);
                }
            }

            $this->persistAttachments($mvt, $this->attachmentService, $request->files, $entityManager);
            $freeFieldService->manageFreeFields($mvt, $post->all(), $entityManager);

            $entityManager->flush();

            return new JsonResponse([
                'success' => true
            ]);
        }
        throw new NotFoundHttpException('404');
    }


    /**
     * @Route("/supprimer", name="mvt_traca_delete", options={"expose"=true},methods={"GET","POST"})
     * @param Request $request
     * @param MouvementTracaService $mouvementTracaService
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function delete(Request $request,
                           MouvementTracaService $mouvementTracaService,
                           EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $mouvementTracaRepository = $entityManager->getRepository(MouvementTraca::class);
            /** @var MouvementTraca $mvt */
            $mvt = $mouvementTracaRepository->find($data['mvt']);

            if (!$this->userService->hasRightFunction(Menu::TRACA, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }

            $entityManager->remove($mvt);
            $entityManager->flush();
            return new JsonResponse();
        }

        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/csv", name="get_mouvements_traca_csv", options={"expose"=true}, methods={"GET"})
     * @param Request $request
     * @param CSVExportService $CSVExportService
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws Exception
     */
    public function getMouvementTracaCsv(Request $request,
                                         CSVExportService $CSVExportService,
                                         EntityManagerInterface $entityManager): Response
    {
        $dateMin = $request->query->get('dateMin');
        $dateMax = $request->query->get('dateMax');

        try {
            $dateTimeMin = DateTime::createFromFormat('Y-m-d H:i:s', $dateMin . ' 00:00:00');
            $dateTimeMax = DateTime::createFromFormat('Y-m-d H:i:s', $dateMax . ' 23:59:59');
        } catch (\Throwable $throwable) {
        }

        if (isset($dateTimeMin) && isset($dateTimeMax)) {
            $champLibreRepository = $entityManager->getRepository(ChampLibre::class);
            $freeFields = $champLibreRepository->findByCategoryTypeLabels([CategoryType::MOUVEMENT_TRACA]);

            $freeFieldsIds = array_map(
                function (ChampLibre $cl) {
                    return $cl->getId();
                },
                $freeFields
            );
            $freeFieldsHeader = array_map(
                function (ChampLibre $cl) {
                    return $cl->getLabel();
                },
                $freeFields
            );
            $mouvementTracaRepository = $entityManager->getRepository(MouvementTraca::class);
            $pieceJointeRepository = $entityManager->getRepository(PieceJointe::class);

            $mouvements = $mouvementTracaRepository->getByDates($dateTimeMin, $dateTimeMax);
            $attachmentsNameByMouvementTraca = $pieceJointeRepository->getNameGroupByMouvements();

            $csvHeader = array_merge([
                'date',
                'colis',
                'emplacement',
                'quantité',
                'type',
                'opérateur',
                'commentaire',
                'pieces jointes',
                'origine',
                'numéro de commande',
                'urgence'
            ], $freeFieldsHeader);

            return $CSVExportService->createBinaryResponseFromData(
                'export_mouvement_traca.csv',
                $mouvements,
                $csvHeader,
                function ($mouvement) use ($attachmentsNameByMouvementTraca, $freeFieldsIds) {
                    $row = [];
                    $row[] = $mouvement['datetime'] ? $mouvement['datetime']->format('d/m/Y H:i') : '';
                    $row[] = $mouvement['colis'];
                    $row[] = $mouvement['locationLabel'] ?: '';
                    $row[] = $mouvement['quantity'] ?: '';
                    $row[] = $mouvement['typeName'] ?: '';
                    $row[] = $mouvement['operatorUsername'] ?: '';
                    $row[] = $mouvement['commentaire'] ? strip_tags($mouvement['commentaire']) : '';
                    $row[] = $attachmentsNameByMouvementTraca[(int)$mouvement['id']] ?? '';
                    $row[] = $mouvement['numeroArrivage'] ?: $mouvement['numeroReception'] ?: '';
                    $row[] = $mouvement['numeroCommandeListArrivage'] && !empty($mouvement['numeroCommandeListArrivage'])
                        ? implode(', ', $mouvement['numeroCommandeListArrivage'])
                        : ($mouvement['referenceReception'] ?: '');
                    $row[] = !empty($mouvement['isUrgent']) ? 'oui' : 'non';
                    foreach ($freeFieldsIds as $freeField) {
                        $row[] = $mouvement['freeFields'][$freeField] ?? "";
                    }
                    return [$row];
                }
            );
        } else {
            throw new NotFoundHttpException('404');
        }
    }

    /**
     * @Route("/voir", name="mvt_traca_show", options={"expose"=true}, methods={"GET","POST"})
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @return Response
     */
    public function show(EntityManagerInterface $entityManager,
                         Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::TRACA, Action::DISPLAY_MOUV)) {
                return $this->redirectToRoute('access_denied');
            }

            $statutRepository = $entityManager->getRepository(Statut::class);
            $mouvementTracaRepository = $entityManager->getRepository(MouvementTraca::class);

            $mouvementTraca = $mouvementTracaRepository->find($data);
            $json = $this->renderView('mouvement_traca/modalShowMvtTracaContent.html.twig', [
                'mvt' => $mouvementTraca,
                'statuts' => $statutRepository->findByCategorieName(CategorieStatut::MVT_TRACA),
                'attachments' => $mouvementTraca->getAttachments()
            ]);
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/obtenir-corps-modal-nouveau", name="mouvement_traca_get_appropriate_html", options={"expose"=true}, methods={"GET","POST"})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param SpecificService $specificService
     * @return Response
     */
    public function getAppropriateHtml(Request $request,
                                       EntityManagerInterface $entityManager,
                                       SpecificService $specificService): Response
    {
        if ($request->isXmlHttpRequest() && $typeId = json_decode($request->getContent(), true)) {
            $statutRepository = $entityManager->getRepository(Statut::class);

            if (!$this->userService->hasRightFunction(Menu::TRACA, Action::DISPLAY_MOUV)) {
                return $this->redirectToRoute('access_denied');
            }
            if ($typeId === 'fromStart') {
                $currentClient = $specificService->isCurrentClientNameFunction(SpecificService::CLIENT_SAFRAN_ED);
                $fileToRender = 'mouvement_traca/' . (
                    $currentClient
                        ? 'newMassMvtTraca.html.twig'
                        : 'newSingleMvtTraca.html.twig'
                    );
            } else {
                $appropriateType = $statutRepository->find($typeId);
                $fileToRender = 'mouvement_traca/' . (
                    $appropriateType
                        ? $appropriateType->getNom() === MouvementTraca::TYPE_PRISE_DEPOSE ? 'newMassMvtTraca.html.twig'
                        : 'newSingleMvtTraca.html.twig'
                        : 'newSingleMvtTraca.html.twig');
            }
            return new JsonResponse([
                'modalBody' => $fileToRender === 'mouvement_traca/' ? false : $this->renderView($fileToRender, [])
            ]);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @param MouvementTraca $mouvementTraca
     * @param AttachmentService $attachmentService
     * @param FileBag|array $files
     * @param EntityManagerInterface $entityManager
     */
    private function persistAttachments(MouvementTraca $mouvementTraca, AttachmentService $attachmentService, $files, EntityManagerInterface $entityManager)
    {
        $attachments = $attachmentService->createAttachements($files);
        foreach ($attachments as $attachment) {
            $entityManager->persist($attachment);
            $mouvementTraca->addAttachment($attachment);
        }
    }
}
