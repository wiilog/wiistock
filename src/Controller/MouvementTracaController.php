<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Arrivage;
use App\Entity\CategorieStatut;
use App\Entity\Colis;
use App\Entity\Emplacement;
use App\Entity\Litige;
use App\Entity\Menu;
use App\Entity\MouvementTraca;
use App\Entity\ParametrageGlobal;
use App\Entity\PieceJointe;

use App\Entity\Statut;
use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;

use App\Service\AttachmentService;
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
     * @var UtilisateurRepository
     */
    private $utilisateurRepository;

    /**
     * @var MouvementTracaService
     */
    private $mouvementTracaService;

    /**
     * ArrivageController constructor.
     * @param MouvementTracaService $mouvementTracaService
     * @param AttachmentService $attachmentService
     * @param UtilisateurRepository $utilisateurRepository
     * @param UserService $userService
     */
    public function __construct(MouvementTracaService $mouvementTracaService,
                                AttachmentService $attachmentService,
                                UtilisateurRepository $utilisateurRepository,
                                UserService $userService)
    {
        $this->utilisateurRepository = $utilisateurRepository;
        $this->userService = $userService;
        $this->attachmentService = $attachmentService;
        $this->mouvementTracaService = $mouvementTracaService;
    }

    /**
     * @Route("/", name="mvt_traca_index")
     * @param EntityManagerInterface $entityManager
     * @return RedirectResponse|Response
     * @throws NonUniqueResultException
     */
    public function index(EntityManagerInterface $entityManager)
    {
        if (!$this->userService->hasRightFunction(Menu::TRACA, Action::DISPLAY_MOUV)) {
            return $this->redirectToRoute('access_denied');
        }

        $statutRepository = $entityManager->getRepository(Statut::class);
        $parametrageGlobalRepository = $entityManager->getRepository(ParametrageGlobal::class);

        $redirectAfterTrackingMovementCreation = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::CLOSE_AND_CLEAR_AFTER_NEW_MVT);
        return $this->render('mouvement_traca/index.html.twig', [
            'statuts' => $statutRepository->findByCategorieName(CategorieStatut::MVT_TRACA),
            'redirectAfterTrackingMovementCreation' => (int)($redirectAfterTrackingMovementCreation ? !$redirectAfterTrackingMovementCreation->getValue() : true)
        ]);
    }

    /**
     * @Route("/creer", name="mvt_traca_new", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws Exception
     */
    public function new(Request $request,
                        EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::TRACA, Action::CREATE)) {
                return $this->redirectToRoute('access_denied');
            }

            $post = $request->request;

            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
            $emplacementRepository = $entityManager->getRepository(Emplacement::class);

            $operator = $utilisateurRepository->find($post->get('operator'));
            $colisStr = $post->get('colis');
            $commentaire = $post->get('commentaire');
            $date = new DateTime($post->get('datetime'), new \DateTimeZone('Europe/Paris'));
            $fromNomade = false;
            $fileBag = $request->files->count() > 0 ? $request->files : null;

            $createdMouvements = [];

            if (empty($post->get('is-mass'))) {
                $emplacement = $emplacementRepository->find($post->get('emplacement'));
                $createdMvt = $this->mouvementTracaService->persistMouvementTraca(
                    $colisStr,
                    $emplacement,
                    $operator,
                    $date,
                    $fromNomade,
                    null,
                    $post->getInt('type'),
                    ['commentaire' => $commentaire]
                );
                $entityManager->persist($createdMvt);
                $createdMouvements[] = $createdMvt;
            } else {
                $colisArray = explode(',', $colisStr);
                foreach ($colisArray as $colis) {
                    $emplacementPrise = $emplacementRepository->find($post->get('emplacement-prise'));
                    $emplacementDepose = $emplacementRepository->find($post->get('emplacement-depose'));
                    $createdMvt = $this->mouvementTracaService->persistMouvementTraca(
                        $colis,
                        $emplacementPrise,
                        $operator,
                        $date,
                        $fromNomade,
                        true,
                        MouvementTraca::TYPE_PRISE,
                        ['commentaire' => $commentaire]
                    );
                    $entityManager->persist($createdMvt);
                    $createdMouvements[] = $createdMvt;
                    $createdMvt = $this->mouvementTracaService->persistMouvementTraca(
                        $colis,
                        $emplacementDepose,
                        $operator,
                        $date,
                        $fromNomade,
                        true,
                        MouvementTraca::TYPE_DEPOSE,
                        ['commentaire' => $commentaire]
                    );
                    $entityManager->persist($createdMvt);
                    $createdMouvements[] = $createdMvt;
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
                    $this->addAttachements($mouvement, $this->attachmentService, $fileNames, $entityManager);
                }
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

            $mvt = $mouvementTracaRepository->find($data['id']);

            $json = $this->renderView('mouvement_traca/modalEditMvtTracaContent.html.twig', [
                'mvt' => $mvt,
                'statuts' => $statutRepository->findByCategorieName(CategorieStatut::MVT_TRACA),
                'attachements' => $mvt->getAttachements()
            ]);

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/modifier", name="mvt_traca_edit", options={"expose"=true}, methods="GET|POST")
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @return Response
     */
    public function edit(EntityManagerInterface $entityManager,
                         Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::TRACA, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $post = $request->request;

            $statutRepository = $entityManager->getRepository(Statut::class);
            $emplacementRepository = $entityManager->getRepository(Emplacement::class);
            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
            $mouvementTracaRepository = $entityManager->getRepository(MouvementTraca::class);

            $date = DateTime::createFromFormat(DateTime::ATOM, $post->get('datetime') . ':00P', new \DateTimeZone('Europe/Paris'));
            $type = $statutRepository->find($post->get('type'));
            $location = $emplacementRepository->find($post->get('emplacement'));
            $operator = $utilisateurRepository->find($post->get('operator'));

            $mvt = $mouvementTracaRepository->find($post->get('id'));
            $mvt
                ->setDatetime($date)
                ->setOperateur($operator)
                ->setColis($post->get('colis'))
                ->setType($type)
                ->setEmplacement($location)
                ->setCommentaire($post->get('commentaire'));

            $entityManager->flush();

            $listAttachmentIdToKeep = $post->get('files');

            $attachments = $mvt->getAttachements()->toArray();
            foreach ($attachments as $attachment) {
                /** @var PieceJointe $attachment */
                if (!$listAttachmentIdToKeep || !in_array($attachment->getId(), $listAttachmentIdToKeep)) {
                    $this->attachmentService->removeAndDeleteAttachment($attachment, null, null, $mvt);
                }
            }

            $this->addAttachements($mvt, $this->attachmentService, $request->files, $entityManager);
            $entityManager->flush();

            return new JsonResponse();
        }
        throw new NotFoundHttpException('404');
    }


    /**
     * @Route("/supprimer", name="mvt_traca_delete", options={"expose"=true},methods={"GET","POST"})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function delete(Request $request,
                           EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $mouvementTracaRepository = $entityManager->getRepository(MouvementTraca::class);
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
     * @Route("/mouvement-traca-infos", name="get_mouvements_traca_for_csv", options={"expose"=true}, methods={"GET","POST"})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws Exception
     */
    public function getMouvementIntels(Request $request,
                                       EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $mouvementTracaRepository = $entityManager->getRepository(MouvementTraca::class);
            $colisRepository = $entityManager->getRepository(Colis::class);
            $dateMin = $data['dateMin'] . ' 00:00:00';
            $dateMax = $data['dateMax'] . ' 23:59:59';

            $dateTimeMin = DateTime::createFromFormat('d/m/Y H:i:s', $dateMin);
            $dateTimeMax = DateTime::createFromFormat('d/m/Y H:i:s', $dateMax);

            $mouvements = $mouvementTracaRepository->findByDates($dateTimeMin, $dateTimeMax);

            foreach ($mouvements as $mouvement) {
                $date = $mouvement->getDatetime();
                if ($dateTimeMin >= $date || $dateTimeMax <= $date) {
                    array_splice($mouvements, array_search($mouvement, $mouvements), 1);
                }
            }

            $headers = [
                'date',
                'colis',
                'emplacement',
                'type',
                'opérateur',
                'commentaire',
                'pieces jointes',
                'origine',
                'numéro de commande',
                'urgence'
            ];
            $data = [];
            $data[] = $headers;

            foreach ($mouvements as $mouvement) {
                $mouvementData = [];

                $mouvementData[] = $mouvement->getDatetime() ? $mouvement->getDatetime()->format('d/m/Y H:i') : '';
                $mouvementData[] = $mouvement->getColis();
                $mouvementData[] = $mouvement->getEmplacement() ? $mouvement->getEmplacement()->getLabel() : '';
                $mouvementData[] = $mouvement->getType() ? $mouvement->getType()->getNom() : '';
                $mouvementData[] = $mouvement->getOperateur() ? $mouvement->getOperateur()->getUsername() : '';
                $mouvementData[] = strip_tags($mouvement->getCommentaire());

                $attachments = $mouvement->getAttachements() ? $mouvement->getAttachements()->toArray() : [];
                $attachmentsNames = [];
                foreach ($attachments as $attachment) {
                    $attachmentsNames[] = $attachment->getOriginalName();
                }
                $mouvementData[] = implode(", ", $attachmentsNames);
                $mouvementData[] =
                    $mouvement->getArrivage()
                        ? $mouvement->getArrivage()->getNumeroArrivage()
                        : ($mouvement->getReception()
                        ? $mouvement->getReception()->getNumeroReception()
                        : '');
                $mouvementData[] =
                    $mouvement->getArrivage()
                        ? $mouvement->getArrivage()->getNumeroCommandeList()
                        : ($mouvement->getReception()
                        ? $mouvement->getReception()->getReference()
                        : '');
                $colis = $colisRepository->findOneBy(['code' => $mouvement->getColis()]);
                if ($colis) {
                    $arrivage = $colis->getArrivage();
                    $mouvementData[] = ($arrivage->getIsUrgent() ? 'oui' : 'non');
                } else {
                    $mouvementData[] = 'non';
                }
                $data[] = $mouvementData;
            }
            return new JsonResponse($data);
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
                'attachments' => $mouvementTraca->getAttachements()
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
     * @param FileBag $files
     * @param EntityManagerInterface $entityManager
     */
    private function addAttachements(MouvementTraca $mouvementTraca, AttachmentService $attachmentService, FileBag $files, EntityManagerInterface $entityManager) {
        $attachments = $attachmentService->addAttachements($files);
        foreach ($attachments as $attachment) {
            $entityManager->persist($attachment);
            $mouvementTraca->addAttachement($attachment);
        }
        $entityManager->persist($mouvementTraca);
        $entityManager->flush();
    }
}
