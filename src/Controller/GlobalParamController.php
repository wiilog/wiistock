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
use App\Service\UserService;
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
     * @var MailerServerRepository
     */
    private $mailerServerRepository;

    /**
     * @var DimensionsEtiquettesRepository
     */
    private $dimensionsEtiquettesRepository;

    /**
     * @var PrefixeNomDemandeRepository
     */
    private $prefixeNomDemandeRepository;

    /**
     * @var DaysWorkedRepository
     */
    private $daysWorkedRepository;

    /**
     * @var ParametrageGlobalRepository
     */
    private $parametrageGlobalRepository;

    /**
     * @var UserService
     */
    private $userService;

    public function __construct(ParametrageGlobalRepository $parametrageGlobalRepository, MailerServerRepository $mailerServerRepository, PrefixeNomDemandeRepository $prefixeNomDemandeRepository, DimensionsEtiquettesRepository $dimensionsEtiquettesRepository, UserService $userService, DaysWorkedRepository $daysWorkedRepository)
    {
        $this->dimensionsEtiquettesRepository = $dimensionsEtiquettesRepository;
        $this->prefixeNomDemandeRepository = $prefixeNomDemandeRepository;
        $this->daysWorkedRepository = $daysWorkedRepository;
        $this->mailerServerRepository = $mailerServerRepository;
        $this->userService = $userService;
        $this->parametrageGlobalRepository = $parametrageGlobalRepository;
    }

    /**
     * @Route("/", name="global_param_index")
     */
    public function index(): response
    {
        if (!$this->userService->hasRightFunction(Menu::PARAM)) {
            return $this->redirectToRoute('access_denied');
        }

        $dimensions =  $this->dimensionsEtiquettesRepository->findOneDimension();
        $mailerServer =  $this->mailerServerRepository->findOneMailerServer();
        $paramGlo = $this->parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::CREATE_DL_AFTER_RECEPTION);
        $paramGloPrepa = $this->parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::CREATE_PREPA_AFTER_DL);

        return $this->render('parametrage_global/index.html.twig',
            [
            'dimensions_etiquettes' => $dimensions,
                'parametrageG' => $paramGlo,
                'parametrageGPrepa' => $paramGloPrepa,
                'mailerServer' => $mailerServer,
                'wantsBL' => $this->parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::INCLUDE_BL_IN_ETIQUETTE)
        ]);
    }

    /**
     * @Route("/ajax-etiquettes", name="ajax_dimensions_etiquettes",  options={"expose"=true},  methods="GET|POST")
     */
    public function ajaxDimensionEtiquetteServer(Request $request): response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM)) {
                return $this->redirectToRoute('access_denied');
            }
            $em = $this->getDoctrine()->getEntityManager();
            $dimensions =  $this->dimensionsEtiquettesRepository->findOneDimension();
            if (!$dimensions) {
                $dimensions = new DimensionsEtiquettes();
                $em->persist($dimensions);
            }
            $dimensions
                ->setHeight(intval($data['height']))
                ->setWidth(intval($data['width']));
            $ifExist = $this->parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::INCLUDE_BL_IN_ETIQUETTE);
            $em = $this->getDoctrine()->getManager();
            if ($ifExist)
            {
                $ifExist->setParametre($data['param-bl-etiquette']);
                $em->flush();
            }
            else
            {
                $parametrage = new ParametrageGlobal();
                $parametrage
                    ->setLabel(ParametrageGlobal::INCLUDE_BL_IN_ETIQUETTE)
                    ->setParametre($data['param-bl-etiquette']);
                $em->persist($parametrage);
                $em->flush();
            }
            $em->flush();

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/ajax-update-prefix-demand", name="ajax_update_prefix_demand",  options={"expose"=true},  methods="GET|POST")
     */
    public function updatePrefixDemand(Request $request): response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $prefixeDemande =  $this->prefixeNomDemandeRepository->findOneByTypeDemande($data['typeDemande']);

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
     */
    public function getPrefixDemand(Request $request)
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $prefixeNomDemande = $this->prefixeNomDemandeRepository->findOneByTypeDemande($data);
            $prefix = $prefixeNomDemande ? $prefixeNomDemande->getPrefixe() : '';

            return new JsonResponse($prefix);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/api", name="days_param_api", options={"expose"=true}, methods="GET|POST")
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::PARAM)) {
                return $this->redirectToRoute('access_denied');
            }

            $days = $this->daysWorkedRepository->findAllOrdered();
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
     */
    public function apiEdit(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM)) {
                return $this->redirectToRoute('access_denied');
            }

            $day = $this->daysWorkedRepository->find($data['id']);

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
     */
    public function edit(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM)) {
                return $this->redirectToRoute('access_denied');
            }

            $em = $this->getDoctrine()->getEntityManager();
            $day = $this->daysWorkedRepository->find($data['day']);
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
     */
    public function ajaxMailerServer(Request $request): response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM)) {
                return $this->redirectToRoute('access_denied');
            }

            $em = $this->getDoctrine()->getEntityManager();
            $mailerServer =  $this->mailerServerRepository->findOneMailerServer();
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
     */
    public function actifDesactifCreateDemandeLivraison(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true))
        {
            $ifExist = $this->parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::CREATE_DL_AFTER_RECEPTION);
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
     */
    public function actifDesactifCreateprepa(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true))
        {
            $ifExist = $this->parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::CREATE_PREPA_AFTER_DL);
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
}
