<?php


namespace App\Controller;

use App\Entity\WorkFreeDay;
use App\Repository\WorkFreeDayRepository;
use App\Repository\ParametrageGlobalRepository;
use App\Service\GlobalParamService;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use phpDocumentor\Reflection\Types\Mixed_;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Environment as Twig_Environment;

/**
 * Class NonWorkedDaysController
 * @package App\Controller
 * @Route("/jours-non-travailles")
 */
class WorkFreeDayController extends AbstractController
{

    /**
     * @Route ("/api", name="workFreeDays_table_api", options={"expose"=true},  methods="GET", condition="request.isXmlHttpRequest()")
     * @param EntityManagerInterface $entityManager
     * @return JsonResponse
     */
    public function workFreeDaysTableApi(EntityManagerInterface $entityManager)
    {
        $workFreeDayRepository = $entityManager->getRepository(WorkFreeDay::class);
        $rows = [];

        $workFreeDays = $workFreeDayRepository->findBy([], ['id' => 'DESC']); /// TODO
        /** @var WorkFreeDay $day */
        foreach ($workFreeDays as $day) {
            $rows[] = [
                'actions' => $this->renderView('parametrage_global/datatableWorkFreeDayRow.html.twig', [
                    'workFreeDayId' => $day->getId(),
                    'dateStr' => $day->getDay()->format('Y-m-d')
                ]),
                'day' => $this->getFrenchDay($day->getDay())
            ];
        }

        return new JsonResponse([
            'data' => $rows
        ]);
    }

    /**
     * @Route("/new", name="workFreeDay_new", options={ "expose"=true },  methods="POST" , condition="request.isXmlHttpRequest()"))
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return JsonResponse
     * @throws \Exception
     */
    public function newNonWorkedDay(Request $request,
                                    EntityManagerInterface $entityManager)
    {
            if (!(empty($request->request->get('date')))) {
                $nonWorkedDayToAdd = $request->request->get('date');
                $date_input = new \DateTime($nonWorkedDayToAdd);
                $publicHolliday = new WorkFreeDay();

                $nonWorkedDaysRepository = $entityManager->getRepository(WorkFreeDay::class);
                $check = $nonWorkedDaysRepository->findBy(['day' => $date_input]);

                if (count($check) == 0) {
                    $publicHolliday->setDay($date_input);
                    $entityManager->persist($publicHolliday);
                    $entityManager->flush();
                    $inputId = $publicHolliday->getId();

                    return new JsonResponse([
                        'success' => true,
                        'text' => "Le jour non travaillé a bien été enregistré.",
                        'lastid' => $inputId
                    ]);
                }
                else {
                    return new JsonResponse([
                        'success' => false,
                        'text' => "Ce jour non travaillé est déja enegistré.",
                        'lastid' => null
                    ]);
                }
            }
            else {
                return new JsonResponse([
                    'success' => false,
                    'text' => "Aucun jour non travaillé sélectionné."
                ]);
            }
    }

    /**
     * @Route("/supprimer" , name="workFreeDay_delete", options={"expose"=true}, methods={"DELETE"}, condition="request.isXmlHttpRequest()")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return JsonResponse
     */
    public function deleteWorkFreeDay(Request $request,
                                      EntityManagerInterface $entityManager)
    {
        $nonWorkedDaysRepository = $entityManager->getRepository(WorkFreeDay::class);
        $id = $request->request->get('id');
        $workFreeDayToDelete = $nonWorkedDaysRepository->find($id);
        if ($workFreeDayToDelete) {
            $entityManager->remove($workFreeDayToDelete);
            $entityManager->flush();
            $data = [
                'success' => true,
                'message' => "Le jour non travaillé a bien été supprimé de la base de données."
            ];
        }
        else {
            $data = [
                'success' => false,
                'message' => "Ce jour non travaillé a déjà été supprimé."
            ];
        }

        return new JsonResponse($data);
    }

    private function getFrenchDay($day): string
    {
        $weekDays = ["Dimanche", "Lundi", "Mardi", "Mercredi", "Jeudi", "Vendredi", "Samedi"];
        $monthYear = ["Janvier", "Février", "Mars", "Avril", "Mai", "Juin", "Juillet", "Aout", "Septembre", "Octobre", "Novembre", "Décembre"];
        $intDay = $day->format('w');
        $intMonth = $day->format('n');
        $numDay = $day->format('d');
        $year = $day->format('Y');
        return ($weekDays[$intDay] . " " . $numDay . " " . $monthYear[$intMonth - 1] . " " . $year);
    }
}
