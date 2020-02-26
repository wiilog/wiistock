<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\CategorieStatut;
use App\Entity\Menu;
use App\Entity\MouvementTraca;
use App\Entity\ParametrageGlobal;
use App\Entity\PieceJointe;

use App\Repository\ColisRepository;
use App\Repository\EmplacementRepository;
use App\Repository\MouvementTracaRepository;
use App\Repository\ParametrageGlobalRepository;
use App\Repository\StatutRepository;
use App\Repository\TypeRepository;
use App\Repository\UtilisateurRepository;

use App\Service\AttachmentService;
use App\Service\MouvementTracaService;
use App\Service\SpecificService;
use App\Service\UserService;

use Doctrine\ORM\NonUniqueResultException;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
     * @var MouvementTracaRepository
     */
    private $mouvementRepository;

    /**
     * @var UserService
     */
    private $userService;

	/**
	 * @var AttachmentService
	 */
    private $attachmentService;

    /**
     * @var StatutRepository
     */
    private $statutRepository;

    /**
     * @var UtilisateurRepository
     */
    private $utilisateurRepository;


    /**
     * @var EmplacementRepository
     */
    private $emplacementRepository;

    /**
     * @var ColisRepository
     */
    private $colisRepository;

	/**
	 * @var TypeRepository
	 */
    private $typeRepository;

	/**
	 * @var MouvementTracaService
	 */
    private $mouvementTracaService;

	/**
	 * ArrivageController constructor.
	 * @param MouvementTracaService $mouvementTracaService
	 * @param AttachmentService $attachmentService
	 * @param TypeRepository $typeRepository
	 * @param EmplacementRepository $emplacementRepository
	 * @param UtilisateurRepository $utilisateurRepository
	 * @param StatutRepository $statutRepository
	 * @param UserService $userService
	 * @param MouvementTracaRepository $mouvementTracaRepository
	 */

    public function __construct(MouvementTracaService $mouvementTracaService, ColisRepository $colisRepository, AttachmentService $attachmentService, TypeRepository $typeRepository, EmplacementRepository $emplacementRepository, UtilisateurRepository $utilisateurRepository, StatutRepository $statutRepository, UserService $userService, MouvementTracaRepository $mouvementTracaRepository)
    {
        $this->colisRepository = $colisRepository;
        $this->emplacementRepository = $emplacementRepository;
        $this->utilisateurRepository = $utilisateurRepository;
        $this->statutRepository = $statutRepository;
        $this->userService = $userService;
        $this->mouvementRepository = $mouvementTracaRepository;
        $this->typeRepository = $typeRepository;
        $this->attachmentService = $attachmentService;
        $this->mouvementTracaService = $mouvementTracaService;
    }

    /**
     * @Route("/", name="mvt_traca_index")
     * @param ParametrageGlobalRepository $parametrageGlobalRepository
     * @return RedirectResponse|Response
     * @throws NonUniqueResultException
     */
    public function index(ParametrageGlobalRepository $parametrageGlobalRepository)
    {
        if (!$this->userService->hasRightFunction(Menu::TRACA, Action::DISPLAY_MOUV)) {
            return $this->redirectToRoute('access_denied');
        }
        $redirectAfterTrackingMovementCreation = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::CLOSE_AND_CLEAR_AFTER_NEW_MVT);
        return $this->render('mouvement_traca/index.html.twig', [
            'statuts' => $this->statutRepository->findByCategorieName(CategorieStatut::MVT_TRACA),
            'emplacements' => $this->emplacementRepository->findAll(),
            'redirectAfterTrackingMovementCreation' => (int) ($redirectAfterTrackingMovementCreation ? !$redirectAfterTrackingMovementCreation->getValue() : true)
        ]);
    }

    /**
     * @Route("/creer", name="mvt_traca_new", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param UtilisateurRepository $utilisateurRepository
     * @return Response
     * @throws NonUniqueResultException
     * @throws Exception
     */
	public function new(Request $request,
                        UtilisateurRepository $utilisateurRepository): Response
	{
		if ($request->isXmlHttpRequest()) {
			if (!$this->userService->hasRightFunction(Menu::TRACA, Action::CREATE)) {
				return $this->redirectToRoute('access_denied');
			}

			$post = $request->request;
			$em = $this->getDoctrine()->getManager();

            $operator = $utilisateurRepository->find($post->get('operator'));
			$colisStr = $post->get('colis');
            $commentaire = $post->get('commentaire');
            $date = new DateTime($post->get('datetime'), new \DateTimeZone('Europe/Paris'));
            $fromNomade = false;
            $fileBag = $request->files->count() > 0 ? $request->files : null;

            $createdMouvements = [];

			if (empty($post->get('is-mass'))) {
                $emplacement = $this->emplacementRepository->find($post->get('emplacement'));
                $createdMouvements[] = $this->mouvementTracaService->persistMouvementTraca(
                    $colisStr,
                    $emplacement,
                    $operator,
                    $date,
                    $fromNomade,
			        null,
                    $post->getInt('type'),
                    $commentaire
                );
            }
			else {
			    $colisArray = explode(',', $colisStr);
			    foreach ($colisArray as $colis) {
                    $emplacementPrise = $this->emplacementRepository->find($post->get('emplacement-prise'));
                    $emplacementDepose = $this->emplacementRepository->find($post->get('emplacement-depose'));

                    $createdMouvements[] = $this->mouvementTracaService->persistMouvementTraca(
                        $colis,
                        $emplacementPrise,
                        $operator,
                        $date,
                        $fromNomade,
                        true,
                        MouvementTraca::TYPE_PRISE,
                        $commentaire
                    );
                    $createdMouvements[] = $this->mouvementTracaService->persistMouvementTraca(
                        $colis,
                        $emplacementDepose,
                        $operator,
                        $date,
                        $fromNomade,
                        true,
                        MouvementTraca::TYPE_DEPOSE,
                        $commentaire
                    );
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
                    $this->attachmentService->addAttachements($fileNames, null, null, $mouvement);
                }
            }

            $em->flush();

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
	 */
	public function editApi(Request $request): Response
	{
		if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
			if (!$this->userService->hasRightFunction(Menu::TRACA, Action::EDIT)) {
				return $this->redirectToRoute('access_denied');
			}

			$mvt = $this->mouvementRepository->find($data['id']);

			$json = $this->renderView('mouvement_traca/modalEditMvtTracaContent.html.twig', [
				'mvt' => $mvt,
				'statuts' => $this->statutRepository->findByCategorieName(CategorieStatut::MVT_TRACA),
				'attachements' => $mvt->getAttachements()
			]);

			return new JsonResponse($json);
		}
		throw new NotFoundHttpException('404');
	}

	/**
	 * @Route("/modifier", name="mvt_traca_edit", options={"expose"=true}, methods="GET|POST")
	 * @param Request $request
	 * @return Response
	 */
	public function edit(Request $request): Response
	{
		if ($request->isXmlHttpRequest()) {
			if (!$this->userService->hasRightFunction(Menu::TRACA, Action::EDIT)) {
				return $this->redirectToRoute('access_denied');
			}

			$post = $request->request;

			$date = DateTime::createFromFormat(DateTime::ATOM, $post->get('datetime') . ':00P', new \DateTimeZone('Europe/Paris'));
			$type = $this->statutRepository->find($post->get('type'));
			$location = $this->emplacementRepository->find($post->get('emplacement'));
			$operator = $this->utilisateurRepository->find($post->get('operator'));

			$mvt = $this->mouvementRepository->find($post->get('id'));
			$mvt
				->setDatetime($date)
				->setOperateur($operator)
				->setColis($post->get('colis'))
				->setType($type)
				->setEmplacement($location)
				->setCommentaire($post->get('commentaire'));

			$em = $this->getDoctrine()->getManager();
			$em->flush();

			$listAttachmentIdToKeep = $post->get('files');

			$attachments = $mvt->getAttachements()->toArray();
			foreach ($attachments as $attachment) { /** @var PieceJointe $attachment */
				if (!$listAttachmentIdToKeep || !in_array($attachment->getId(), $listAttachmentIdToKeep)) {
					$this->attachmentService->removeAndDeleteAttachment($attachment, null, null, $mvt);
				}
			}

			$this->attachmentService->addAttachements($request->files, null, null, $mvt);
            $em->flush();

			return new JsonResponse();
		}
		throw new NotFoundHttpException('404');
	}


	/**
     * @Route("/supprimer", name="mvt_traca_delete", options={"expose"=true},methods={"GET","POST"})
     */
    public function delete(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $mvt = $this->mouvementRepository->find($data['mvt']);

            if (!$this->userService->hasRightFunction(Menu::TRACA, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($mvt);
            $entityManager->flush();
            return new JsonResponse();
        }

        throw new NotFoundHttpException("404");
    }

	/**
	 * @Route("/mouvement-traca-infos", name="get_mouvements_traca_for_csv", options={"expose"=true}, methods={"GET","POST"})
	 * @param Request $request
	 * @return Response
	 * @throws Exception
	 */
    public function getMouvementIntels(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
			$dateMin = $data['dateMin'] . ' 00:00:00';
			$dateMax = $data['dateMax'] . ' 23:59:59';

			$dateTimeMin = DateTime::createFromFormat('d/m/Y H:i:s', $dateMin);
			$dateTimeMax = DateTime::createFromFormat('d/m/Y H:i:s', $dateMax);

            $mouvements = $this->mouvementRepository->findByDates($dateTimeMin, $dateTimeMax);

            foreach($mouvements as $mouvement) {
                $date = $mouvement->getDatetime();
                if ($dateTimeMin >= $date || $dateTimeMax <= $date) {
                    array_splice($mouvements, array_search($mouvement, $mouvements), 1);
                }
            }

            $headers = [];
            $headers = array_merge($headers, ['date', 'colis', 'emplacement', 'type', 'opérateur', 'commentaire', 'pieces jointes', 'urgence']);
            $data = [];
            $data[] = $headers;

            foreach ($mouvements as $mouvement) {
                $mouvementData = [];

                $mouvementData[] = $mouvement->getDatetime() ? $mouvement->getDatetime()->format('d/m/Y H:i') : '';
                $mouvementData[] = $mouvement->getColis();
                $mouvementData[] = $mouvement->getEmplacement() ? $mouvement->getEmplacement()->getLabel() : '';
                $mouvementData[] = $mouvement->getType() ? $mouvement->getType()->getNom() : '';
                $mouvementData[] = $mouvement->getOperateur() ? $mouvement->getOperateur()->getUsername(): '';
                $mouvementData[] = strip_tags($mouvement->getCommentaire());

                $attachments = $mouvement->getAttachements() ? $mouvement->getAttachements()->toArray() : [];
                $attachmentsNames = [];
                foreach ($attachments as $attachment) {
                	$attachmentsNames[] = $attachment->getOriginalName();
				}
                $mouvementData[] = implode(", ", $attachmentsNames);
                $colis = $this->colisRepository->findOneByCode($mouvement->getColis());
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
	 * @param Request $request
	 * @return Response
	 */
    public function show(Request $request): Response
	{
		if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
			if (!$this->userService->hasRightFunction(Menu::TRACA, Action::DISPLAY_MOUV)) {
				return $this->redirectToRoute('access_denied');
			}

			$mouvementTraca = $this->mouvementRepository->find($data);
			$json = $this->renderView('mouvement_traca/modalShowMvtTracaContent.html.twig', [
				'mvt' => $mouvementTraca,
				'statuts' => $this->statutRepository->findByCategorieName(CategorieStatut::MVT_TRACA),
				'attachments' => $mouvementTraca->getAttachements()
			]);
			return new JsonResponse($json);
		}
		throw new NotFoundHttpException('404');
	}

    /**
     * @Route("/obtenir-corps-modal-nouveau", name="mouvement_traca_get_appropriate_html", options={"expose"=true}, methods={"GET","POST"})
     * @param Request $request
     * @return Response
     */
    public function getAppropriateHtml(Request $request, StatutRepository $statutRepository, SpecificService $specificService): Response
    {
        if ($request->isXmlHttpRequest() && $typeId = json_decode($request->getContent(), true)) {
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
                    : 'newSingleMvtTraca.html.twig' );
            }
            return new JsonResponse([
                'modalBody' => $fileToRender === 'mouvement_traca/' ? false : $this->renderView($fileToRender, []),
                'safran' => $specificService->isCurrentClientNameFunction(SpecificService::CLIENT_SAFRAN_ED),
            ]);
        }
        throw new NotFoundHttpException('404');
    }
}
