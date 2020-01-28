<?php

namespace App\Controller;

use App\Entity\DimensionsEtiquettes;
use App\Entity\MailerServer;
use App\Entity\Menu;
use App\Entity\PrefixeNomDemande;
use App\Repository\DaysWorkedRepository;
use App\Repository\DimensionsEtiquettesRepository;
use App\Repository\MailerServerRepository;
use App\Entity\ParametrageGlobal;

use App\Repository\ParametrageGlobalRepository;
use App\Repository\PrefixeNomDemandeRepository;
use App\Repository\TranslationRepository;
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
class GlobalParamController extends AbstractController
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
     * @return Response
     * @throws NonUniqueResultException
     */
    public function index(TranslationRepository $translationRepository,
                          UserService $userService,
                          DimensionsEtiquettesRepository $dimensionsEtiquettesRepository,
                          ParametrageGlobalRepository $parametrageGlobalRepository,
                          MailerServerRepository $mailerServerRepository): response
    {
        if (!$userService->hasRightFunction(Menu::PARAM)) {
            return $this->redirectToRoute('access_denied');
        }

        $dimensions =  $dimensionsEtiquettesRepository->findOneDimension();
        $mailerServer =  $mailerServerRepository->findOneMailerServer();
        $paramGlo = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::CREATE_DL_AFTER_RECEPTION);
        $paramGloPrepa = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::CREATE_PREPA_AFTER_DL);
        $redirect = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::REDIRECT_AFTER_NEW_ARRIVAL);

        return $this->render('parametrage_global/index.html.twig',
            [
            	'dimensions_etiquettes' => $dimensions,
                'parametrageG' => $paramGlo,
                'redirect' => $redirect,
                'parametrageGPrepa' => $paramGloPrepa,
                'mailerServer' => $mailerServer,
                'wantsBL' => $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::INCLUDE_BL_IN_LABEL),
				'translations' => $translationRepository->findAll(),
				'menusTranslations' => array_column($translationRepository->getMenus(), '1'),
                'paramCodeENC' => $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::USES_UTF8),
                'encodages' => [ParametrageGlobal::ENCODAGE_EUW, ParametrageGlobal::ENCODAGE_UTF8]
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
                                                 DimensionsEtiquettesRepository $dimensionsEtiquettesRepository): response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$userService->hasRightFunction(Menu::PARAM)) {
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
            $parametrageGlobal->setParametre($data['param-bl-etiquette']);

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
                                       PrefixeNomDemandeRepository $prefixeNomDemandeRepository): response
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
            if (!$userService->hasRightFunction(Menu::PARAM)) {
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
            if (!$userService->hasRightFunction(Menu::PARAM)) {
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
            if (!$userService->hasRightFunction(Menu::PARAM)) {
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
                                     MailerServerRepository $mailerServerRepository): response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$userService->hasRightFunction(Menu::PARAM)) {
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
                $ifExist->setParametre($data['val']);
                $em->flush();
            }
            else
            {
                $parametrage = new ParametrageGlobal();
                $parametrage
                    ->setLabel(ParametrageGlobal::CREATE_DL_AFTER_RECEPTION)
                    ->setParametre($data['val']);
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
                $ifExist->setParametre($data['val']);
                $em->flush();
            }
            else
            {
                $parametrage = new ParametrageGlobal();
                $parametrage
                    ->setLabel(ParametrageGlobal::CREATE_PREPA_AFTER_DL)
                    ->setParametre($data['val']);
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
                $ifExist->setParametre($data['val']);
                $em->flush();
            }
            else
            {
                $parametrage = new ParametrageGlobal();
                $parametrage
                    ->setLabel(ParametrageGlobal::REDIRECT_AFTER_NEW_ARRIVAL)
                    ->setParametre($data['val']);
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
     * @param TranslationRepository $translationRepository
     * @param TranslationService $translationService
     * @return Response
     * @throws NonUniqueResultException
     * @throws NoResultException
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
            $parametrageGlobal->setParametre($data);

            $em->flush();

            return new JsonResponse(true);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/obtenir-encodage", name="get_encodage", options={"expose"=true}, methods="POST")
     * @param Request $request
     * @param TranslationRepository $translationRepository
     * @param TranslationService $translationService
     * @return Response
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
    public function getEncodage(Request $request,
                                 ParametrageGlobalRepository $parametrageGlobalRepository): Response
    {
        if ($request->isXmlHttpRequest())
        {
            $parametrageGlobal = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::USES_UTF8);
            return new JsonResponse($parametrageGlobal->getParametre());
        }
        throw new NotFoundHttpException("404");
    }
}
