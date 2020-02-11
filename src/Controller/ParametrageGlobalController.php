<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\DimensionsEtiquettes;
use App\Entity\MailerServer;
use App\Entity\Menu;
use App\Entity\PrefixeNomDemande;
use App\Repository\DaysWorkedRepository;
use App\Repository\DimensionsEtiquettesRepository;
use App\Repository\MailerServerRepository;
use App\Entity\ParametrageGlobal;

use App\Repository\NatureRepository;
use App\Repository\ParametrageGlobalRepository;
use App\Repository\PrefixeNomDemandeRepository;
use App\Repository\TranslationRepository;
use App\Service\GlobalParamService;
use App\Service\TranslationService;
use App\Service\UserService;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/parametrage-global")
 */
class ParametrageGlobalController extends AbstractController
{
    private $engDayToFr = [
        'monday' => 'Lundi',
        'tuesday' => 'Mardi',
        'wednesday' => 'Mercredi',
        'thursday' => 'Jeudi',
        'friday' => 'Vendredi',
        'saturday' => 'Samedi',
        'sunday' => 'Dimanche',
    ];

	/**
	 * @Route("/", name="global_param_index")
	 * @param TranslationRepository $translationRepository
	 * @param UserService $userService
	 * @param DimensionsEtiquettesRepository $dimensionsEtiquettesRepository
	 * @param ParametrageGlobalRepository $parametrageGlobalRepository
	 * @param MailerServerRepository $mailerServerRepository
	 * @param GlobalParamService $globalParamService
	 * @param NatureRepository $natureRepository
	 * @return Response
	 * @throws NoResultException
	 * @throws NonUniqueResultException
	 */
    public function index(TranslationRepository $translationRepository,
                          UserService $userService,
                          DimensionsEtiquettesRepository $dimensionsEtiquettesRepository,
                          ParametrageGlobalRepository $parametrageGlobalRepository,
                          MailerServerRepository $mailerServerRepository,
						  GlobalParamService $globalParamService,
						  NatureRepository $natureRepository): Response {
        if (!$userService->hasRightFunction(Menu::PARAM, Action::DISPLAY_GLOB)) {
            return $this->redirectToRoute('access_denied');
        }

        $dimensions =  $dimensionsEtiquettesRepository->findOneDimension();
        $mailerServer =  $mailerServerRepository->findOneMailerServer();
        $paramGlo = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::CREATE_DL_AFTER_RECEPTION);
        $redirect = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::REDIRECT_AFTER_NEW_ARRIVAL);
        $paramGloPrepa = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::CREATE_PREPA_AFTER_DL);
        $wantBL = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::INCLUDE_BL_IN_LABEL);
        $paramCodeENC = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::USES_UTF8);
        $paramCodeETQ = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::BARCODE_TYPE_IS_128);
        $fontFamily = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::FONT_FAMILY);

        return $this->render('parametrage_global/index.html.twig',
            [
            	'dimensions_etiquettes' => $dimensions,
                'redirect' => $redirect ? $redirect->getValue() : true,
                'paramReceptions' => [
                    'parametrageG' => $paramGlo ? $paramGlo->getValue() : false,
                    'parametrageGPrepa' => $paramGloPrepa ? $paramGloPrepa->getValue() : false
                ],
                'mailerServer' => $mailerServer,
                'wantsBL' => $wantBL ? $wantBL->getValue() : false,
                'paramTranslations' => [
                    'translations' => $translationRepository->findAll(),
                    'menusTranslations' => array_column($translationRepository->getMenus(), '1')
                ],
                'paramCodeENC' => $paramCodeENC ? $paramCodeENC->getValue() : true,
                'encodings' => [ParametrageGlobal::ENCODAGE_EUW, ParametrageGlobal::ENCODAGE_UTF8],
                'paramCodeETQ' => $paramCodeETQ ? $paramCodeETQ->getValue() : true,
                'typesETQ' => [ParametrageGlobal::CODE_128, ParametrageGlobal::QR_CODE],
				'receptionLocation' => $globalParamService->getReceptionDefaultLocation(),
				'fonts' => [ParametrageGlobal::FONT_MONTSERRAT, ParametrageGlobal::FONT_TAHOMA, ParametrageGlobal::FONT_MYRIAD],
				'fontFamily' => $fontFamily ? $fontFamily->getValue() : ParametrageGlobal::DEFAULT_FONT_FAMILY,
				'receptionLocation' => $globalParamService->getReceptionDefaultLocation(),
				'paramDashboard' => [
					'existingNatureId' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DASHBOARD_NATURE_COLIS),
					'existingListNaturesId' => $globalParamService->getDashboardListNatures(),
					'natures' => $natureRepository->findAll(),
					'locations' => $globalParamService->getDashboardLocations()
				],
        ]);
    }

    /**
     * @Route("/ajax-etiquettes", name="ajax_dimensions_etiquettes",  options={"expose"=true},  methods="GET|POST")
     * @param Request $request
     * @param UserService $userService
     * @param ParametrageGlobalRepository $parametrageGlobalRepository
     * @param DimensionsEtiquettesRepository $dimensionsEtiquettesRepository
     * @return Response
     * @throws NonUniqueResultException
     */
    public function ajaxDimensionEtiquetteServer(Request $request,
                                                 UserService $userService,
                                                 ParametrageGlobalRepository $parametrageGlobalRepository,
                                                 DimensionsEtiquettesRepository $dimensionsEtiquettesRepository): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$userService->hasRightFunction(Menu::PARAM, Action::DISPLAY_GLOB)) {
                return $this->redirectToRoute('access_denied');
            }

            $em = $this->getDoctrine()->getManager();

            $dimensions =  $dimensionsEtiquettesRepository->findOneDimension();
            if (!$dimensions) {
                $dimensions = new DimensionsEtiquettes();
                $em->persist($dimensions);
            }
            $dimensions
                ->setHeight(intval($data['height']))
                ->setWidth(intval($data['width']));

            $parametrageGlobal = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::INCLUDE_BL_IN_LABEL);

            if (empty($parametrageGlobal)) {
                $parametrageGlobal = new ParametrageGlobal();
				$parametrageGlobal->setLabel(ParametrageGlobal::INCLUDE_BL_IN_LABEL);
                $em->persist($parametrageGlobal);
            }
            $parametrageGlobal->setValue($data['param-bl-etiquette']);
            $parametrageGlobal128 = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::BARCODE_TYPE_IS_128);

            if (empty($parametrageGlobal128)) {
                $parametrageGlobal128 = new ParametrageGlobal();
                $parametrageGlobal128->setLabel(ParametrageGlobal::INCLUDE_BL_IN_LABEL);
                $em->persist($parametrageGlobal128);
            }
            $parametrageGlobal128->setValue($data['param-type-etiquette']);

            $em->flush();

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/ajax-update-prefix-demand", name="ajax_update_prefix_demand",  options={"expose"=true},  methods="GET|POST")
     * @param Request $request
     * @param PrefixeNomDemandeRepository $prefixeNomDemandeRepository
     * @return Response
     * @throws NonUniqueResultException
     */
    public function updatePrefixDemand(Request $request,
                                       PrefixeNomDemandeRepository $prefixeNomDemandeRepository): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $prefixeDemande =  $prefixeNomDemandeRepository->findOneByTypeDemande($data['typeDemande']);

            $em = $this->getDoctrine()->getManager();
            if($prefixeDemande == null){
                $newPrefixe = new PrefixeNomDemande();
                $newPrefixe
                    ->setTypeDemandeAssociee($data['typeDemande'])
                    ->setPrefixe($data['prefixe']);

                $em->persist($newPrefixe);
            } else {
                $prefixeDemande->setPrefixe($data['prefixe']);
            }
            $em->flush();
            return new JsonResponse(['typeDemande' => $data['typeDemande'], 'prefixe' => $data['prefixe']]);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/ajax-get-prefix-demand", name="ajax_get_prefix_demand",  options={"expose"=true},  methods="GET|POST")
     * @param Request $request
     * @param PrefixeNomDemandeRepository $prefixeNomDemandeRepository
     * @return JsonResponse
     * @throws NonUniqueResultException
     */
    public function getPrefixDemand(Request $request,
                                    PrefixeNomDemandeRepository $prefixeNomDemandeRepository)
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $prefixeNomDemande = $prefixeNomDemandeRepository->findOneByTypeDemande($data);
            $prefix = $prefixeNomDemande ? $prefixeNomDemande->getPrefixe() : '';

            return new JsonResponse($prefix);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/api", name="days_param_api", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param UserService $userService
     * @param DaysWorkedRepository $daysWorkedRepository
     * @return Response
     */
    public function api(Request $request,
                        UserService $userService,
                        DaysWorkedRepository $daysWorkedRepository): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$userService->hasRightFunction(Menu::PARAM, Action::DISPLAY_GLOB)) {
                return $this->redirectToRoute('access_denied');
            }

            $days = $daysWorkedRepository->findAllOrdered();
            $rows = [];
            foreach ($days as $day) {
                $url['edit'] = $this->generateUrl('days_api_edit', ['id' => $day->getId()]);

                $rows[] =
                    [
                        'Day' => $this->engDayToFr[$day->getDay()],
                        'Worked' => $day->getWorked() ? 'oui' : 'non',
                        'Times' => $day->getTimes() ?? '',
                        'Order' => $day->getDisplayOrder(),
                        'Actions' => $this->renderView('parametrage_global/datatableDaysRow.html.twig', [
                            'url' => $url,
                            'dayId' => $day->getId(),
                        ]),
                    ];
            }
            $data['data'] = $rows;
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/api-modifier", name="days_api_edit", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param UserService $userService
     * @param DaysWorkedRepository $daysWorkedRepository
     * @return Response
     */
    public function apiEdit(Request $request,
                            UserService $userService,
                            DaysWorkedRepository $daysWorkedRepository): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$userService->hasRightFunction(Menu::PARAM, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $day = $daysWorkedRepository->find($data['id']);

            $json = $this->renderView('parametrage_global/modalEditDaysContent.html.twig', [
                'day' => $day,
                'dayWeek' => $this->engDayToFr[$day->getDay()]
            ]);

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/modifier", name="days_edit",  options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param UserService $userService
     * @param DaysWorkedRepository $daysWorkedRepository
     * @return Response
     */
    public function edit(Request $request,
                         UserService $userService,
                         DaysWorkedRepository $daysWorkedRepository): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$userService->hasRightFunction(Menu::PARAM, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $em = $this->getDoctrine()->getManager();
            $day = $daysWorkedRepository->find($data['day']);
            $dayName = $day->getDay();

            $day->setWorked($data['worked']);

            if (isset($data['times'])) {
                $arrayTimes = explode(';', $data['times']);

                if ($day->getWorked() && count($arrayTimes) % 2 != 0) {
                    return new JsonResponse([
                        'success' => false,
                        'msg' => 'Le format des horaires est incorrect.'
                    ]);
                }
                $day->setTimes($data['times']);
            }

            $em->persist($day);
            $em->flush();

            return new JsonResponse([
                'success' => true,
                'msg' => 'Le jour "' . $dayName . '" a bien été modifié.'
            ]);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/ajax-mail-server", name="ajax_mailer_server",  options={"expose"=true},  methods="GET|POST")
     * @param Request $request
     * @param UserService $userService
     * @param MailerServerRepository $mailerServerRepository
     * @return Response
     * @throws NonUniqueResultException
     */
    public function ajaxMailerServer(Request $request,
                                     UserService $userService,
                                     MailerServerRepository $mailerServerRepository): Response {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$userService->hasRightFunction(Menu::PARAM, Action::DISPLAY_GLOB)) {
                return $this->redirectToRoute('access_denied');
            }

            $em = $this->getDoctrine()->getManager();
            $mailerServer =  $mailerServerRepository->findOneMailerServer();
            if ($mailerServer === null) {
                $mailerServerNew = new MailerServer;
                $mailerServerNew
                    ->setUser($data['user'])
                    ->setPassword($data['password'])
                    ->setPort($data['port'])
                    ->setProtocol($data['protocol'])
                    ->setSmtp($data['smtp']);
                $em->persist($mailerServerNew);
            } else {
                $mailerServer
                    ->setUser($data['user'])
                    ->setPassword($data['password'])
                    ->setPort($data['port'])
                    ->setProtocol($data['protocol'])
                    ->setSmtp($data['smtp']);
            }
            $em->flush();

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/demlivr", name="active_desactive_create_demande_livraison", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param ParametrageGlobalRepository $parametrageGlobalRepository
     * @return Response
     * @throws NonUniqueResultException
     */
    public function actifDesactifCreateDemandeLivraison(Request $request,
                                                        ParametrageGlobalRepository $parametrageGlobalRepository): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true))
        {
            $ifExist = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::CREATE_DL_AFTER_RECEPTION);
            $em = $this->getDoctrine()->getManager();
            if ($ifExist)
            {
                $ifExist->setValue($data['val']);
                $em->flush();
            }
            else
            {
                $parametrage = new ParametrageGlobal();
                $parametrage
                    ->setLabel(ParametrageGlobal::CREATE_DL_AFTER_RECEPTION)
                    ->setValue($data['val']);
                $em->persist($parametrage);
                $em->flush();
            }
            return new JsonResponse();
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/prepa", name="active_desactive_create_prepa", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param ParametrageGlobalRepository $parametrageGlobalRepository
     * @return Response
     * @throws NonUniqueResultException
     */
    public function actifDesactifCreateprepa(Request $request,
                                             ParametrageGlobalRepository $parametrageGlobalRepository): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true))
        {
            $ifExist = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::CREATE_PREPA_AFTER_DL);
            $em = $this->getDoctrine()->getManager();
            if ($ifExist)
            {
                $ifExist->setValue($data['val']);
                $em->flush();
            }
            else
            {
                $parametrage = new ParametrageGlobal();
                $parametrage
                    ->setLabel(ParametrageGlobal::CREATE_PREPA_AFTER_DL)
                    ->setValue($data['val']);
                $em->persist($parametrage);
                $em->flush();
            }
            return new JsonResponse();
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/redirection-switch", name="active_desactive_redirection", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param ParametrageGlobalRepository $parametrageGlobalRepository
     * @return Response
     * @throws NonUniqueResultException
     */
    public function actifDesactifRedirectArrival(Request $request,
                                                        ParametrageGlobalRepository $parametrageGlobalRepository): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true))
        {
            $ifExist = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::REDIRECT_AFTER_NEW_ARRIVAL);
            $em = $this->getDoctrine()->getManager();
            if ($ifExist)
            {
                $ifExist->setValue($data['val']);
                $em->flush();
            }
            else
            {
                $parametrage = new ParametrageGlobal();
                $parametrage
                    ->setLabel(ParametrageGlobal::REDIRECT_AFTER_NEW_ARRIVAL)
                    ->setValue($data['val']);
                $em->persist($parametrage);
                $em->flush();
            }
            return new JsonResponse();
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/personnalisation", name="save_translations", options={"expose"=true}, methods="POST")
     * @param Request $request
     * @param TranslationRepository $translationRepository
     * @param TranslationService $translationService
     * @return Response
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
    public function saveTranslations(Request $request,
                                     TranslationRepository $translationRepository,
                                     TranslationService $translationService): Response
	{
		if ($request->isXmlHttpRequest() && $translations = json_decode($request->getContent(), true))
		{
			foreach ($translations as $translation) {
				if (!empty($translation['val'])) {
					$translationObject = $translationRepository->find($translation['id']);
					if ($translationObject) {
						$translationObject
							->setTranslation($translation['val'])
							->setUpdated(1);
					} else {
						return new JsonResponse(false);
					}
				}
			}
			$this->getDoctrine()->getManager()->flush();

			$translationService->generateTranslationsFile();

			return new JsonResponse(true);
		}
		throw new NotFoundHttpException("404");
	}

	/**
	 * @Route("/personnalisation-encodage", name="save_encodage", options={"expose"=true}, methods="POST")
	 * @param Request $request
	 * @param ParametrageGlobalRepository $parametrageGlobalRepository
	 * @return Response
	 * @throws NonUniqueResultException
	 */
    public function saveEncodage(Request $request,
                                 ParametrageGlobalRepository $parametrageGlobalRepository): Response
    {
        if ($request->isXmlHttpRequest())
        {
            $data = json_decode($request->getContent(), true);
            $parametrageGlobal = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::USES_UTF8);
            $em = $this->getDoctrine()->getManager();
            if (empty($parametrageGlobal)) {
                $parametrageGlobal = new ParametrageGlobal();
                $parametrageGlobal->setLabel(ParametrageGlobal::USES_UTF8);
                $em->persist($parametrageGlobal);
            }
            $parametrageGlobal->setValue($data);

            $em->flush();

            return new JsonResponse(true);
        }
        throw new NotFoundHttpException("404");
    }

	/**
	 * @Route("/emplacement-reception", name="edit_reception_location", options={"expose"=true}, methods="POST")
	 * @param Request $request
	 * @param ParametrageGlobalRepository $parametrageGlobalRepository
	 * @return Response
	 * @throws NonUniqueResultException
	 */
    public function editReceptionLocation(Request $request,
                                 ParametrageGlobalRepository $parametrageGlobalRepository): Response
    {
        if ($request->isXmlHttpRequest())
        {
            $post = $request->request;
            $parametrageGlobal = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::DEFAULT_LOCATION_RECEPTION);
            $em = $this->getDoctrine()->getManager();
            if (empty($parametrageGlobal)) {
                $parametrageGlobal = new ParametrageGlobal();
                $parametrageGlobal->setLabel(ParametrageGlobal::DEFAULT_LOCATION_RECEPTION);
                $em->persist($parametrageGlobal);
            }
            $parametrageGlobal->setValue($post->get('value'));

            $em->flush();

            return new JsonResponse(true);
        }
        throw new NotFoundHttpException("404");
    }

	/**
	 * @Route("/police", name="edit_font", options={"expose"=true}, methods="POST")
	 * @param Request $request
	 * @param ParametrageGlobalRepository $parametrageGlobalRepository
	 * @param GlobalParamService $globalParamService
	 * @return Response
	 * @throws NonUniqueResultException
	 */
    public function editFont(Request $request,
							 ParametrageGlobalRepository $parametrageGlobalRepository,
							 GlobalParamService $globalParamService
	): Response
    {
        if ($request->isXmlHttpRequest())
        {
            $post = $request->request;
            $parametrageGlobal = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::FONT_FAMILY);
            $em = $this->getDoctrine()->getManager();
            if (empty($parametrageGlobal)) {
                $parametrageGlobal = new ParametrageGlobal();
                $parametrageGlobal->setLabel(ParametrageGlobal::FONT_FAMILY);
                $em->persist($parametrageGlobal);
            }
            $parametrageGlobal->setValue($post->get('value'));
            $em->flush();

			$globalParamService->generateSassFile();

			return new JsonResponse(true);
        }
        throw new NotFoundHttpException("404");
    }

	/**
	 * @Route("/obtenir-encodage", name="get_encodage", options={"expose"=true}, methods="POST")
	 * @param Request $request
	 * @param ParametrageGlobalRepository $parametrageGlobalRepository
	 * @return Response
	 * @throws NonUniqueResultException
	 */
    public function getEncodage(Request $request,
                                 ParametrageGlobalRepository $parametrageGlobalRepository): Response
    {
        if ($request->isXmlHttpRequest())
        {
            $parametrageGlobal = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::USES_UTF8);
            return new JsonResponse($parametrageGlobal ? $parametrageGlobal->getValue() : true);
        }
        throw new NotFoundHttpException("404");
    }

	/**
	 * @Route("/obtenir-type-code", name="get_is_code_128", options={"expose"=true}, methods="POST")
	 * @param Request $request
	 * @param ParametrageGlobalRepository $parametrageGlobalRepository
	 * @return JsonResponse
	 * @throws NonUniqueResultException
	 */
	public function getIsCode128(Request $request, ParametrageGlobalRepository $parametrageGlobalRepository) {
        if ($request->isXmlHttpRequest()) {
            $parametrageGlobal128 = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::BARCODE_TYPE_IS_128);
            return new JsonResponse($parametrageGlobal128 ? $parametrageGlobal128->getValue() : true);
        }
		throw new NotFoundHttpException("404");
	}


	/**
	 * @Route("/modifier-parametres-tableau-de-bord", name="edit_dashboard_params",  options={"expose"=true},  methods="GET|POST")
	 * @param Request $request
	 * @param ParametrageGlobalRepository $parametrageGlobalRepository
	 * @return Response
	 * @throws NonUniqueResultException
	 */
	public function editDashboardParams(Request $request, ParametrageGlobalRepository $parametrageGlobalRepository): Response
	{
		if ($request->isXmlHttpRequest()) {
			$post = $request->request;

			$listMultipleSelect = [
				ParametrageGlobal::DASHBOARD_LIST_NATURES_COLIS => 'listNaturesColis',
				ParametrageGlobal::DASHBOARD_LOCATIONS_1 => 'locationsFirstGraph',
				ParametrageGlobal::DASHBOARD_LOCATIONS_2 => 'locationsSecondGraph',
			];

			foreach ($listMultipleSelect as $labelParam => $selectId) {
				$listId = $post->get($selectId);
				$listIdStr = $listId ? is_array($listId) ? implode(',', $listId) : $listId : null;
				$param = $parametrageGlobalRepository->findOneByLabel($labelParam);
				$param->setValue($listIdStr);
			}

			$listSelect = [
				ParametrageGlobal::DASHBOARD_NATURE_COLIS => 'natureColis',
				ParametrageGlobal::DASHBOARD_LOCATION_DOCK => 'locationToTreat',
				ParametrageGlobal::DASHBOARD_LOCATION_WAITING_CLEARANCE_DOCK => 'locationWaitingDock',
				ParametrageGlobal::DASHBOARD_LOCATION_WAITING_CLEARANCE_ADMIN => 'locationWaitingAdmin',
				ParametrageGlobal::DASHBOARD_LOCATION_AVAILABLE => 'locationAvailable',
				ParametrageGlobal::DASHBOARD_LOCATION_TO_DROP_ZONES => 'locationDropZone',
				ParametrageGlobal::DASHBOARD_LOCATION_LITIGES => 'locationLitiges',
				ParametrageGlobal::DASHBOARD_LOCATION_URGENCES => 'locationUrgences',
			];

			foreach ($listSelect as $labelParam => $selectId) {
				$param = $parametrageGlobalRepository->findOneByLabel($labelParam);
				$param->setValue($post->get($selectId));
			}

			$this->getDoctrine()->getManager()->flush();

			return new JsonResponse(true);
		}
		throw new NotFoundHttpException("404");
	}


}
