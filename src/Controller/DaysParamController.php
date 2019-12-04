<?php

namespace App\Controller;


use App\Entity\Menu;
use App\Repository\DaysWorkedRepository;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/parametres-days")
 */
class DaysParamController extends AbstractController
{
    /**
     * @var UserService
     */
    private $userService;

    /**
     * @var DaysWorkedRepository
     */
    private $daysWorkedRepository;

    private $engDayToFr = [
        'monday' => 'Lundi',
        'tuesday' => 'Mardi',
        'wednesday' => 'Mercredi',
        'thursday' => 'Jeudi',
        'friday' => 'Vendredi',
        'saturday' => 'Samedi',
        'sunday' => 'Dimanche',
    ];

    public function __construct(UserService $userService, DaysWorkedRepository $daysWorkedRepository)
    {
        $this->userService = $userService;
        $this->daysWorkedRepository = $daysWorkedRepository;
    }

    /**
     * @Route("/", name="reunification")
     */
    public function index()
    {
        if (!$this->userService->hasRightFunction(Menu::PARAM)) {
            return $this->redirectToRoute('access_denied');
        }

        return $this->render('parametrage_global/index.html.twig', [

        ]);
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

                if (count($arrayTimes) % 2 != 0) {
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
}
