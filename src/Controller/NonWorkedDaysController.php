<?php


namespace App\Controller;

use App\Entity\NonWorkedDays;
use App\Repository\NonWorkedDaysRepository;
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

class NonWorkedDaysController extends AbstractController
{
    /**
     * @var NonWorkedDaysRepository
     * @Route("/nonworkedday")
     */
    private $repository;

    public function __construct(NonWorkedDaysRepository $nonWorkedDaysRepository)
    {
        $this->repository = $nonWorkedDaysRepository;
    }

    /**
     * @return Response
     */
    public function index(): Response
    {
        $publicHollydays = $this->repository->findAll();
        return $this->render('parametrage_global/index.html.twig', [
            'days' => $publicHollydays,
        ]);
    }

    /**
     * @Route("/nonworkedday/new", name="nonworkedday_new", options={ "expose"=true },  methods="POST")
     */
    public function newNonWorkedDay(Request $request,
                                    EntityManagerInterface $entityManager)
    {
        if (($request->isXmlHttpRequest())) {
            if (!(empty($request->request->get('date')))) {
                $nonWorkedDayToAdd = $request->request->get('date');
                $date_input = new \DateTime($nonWorkedDayToAdd);
                $publicHolliday = new NonWorkedDays();

                $check = $this->repository->findBy(array('day' => $date_input));

                if (count($check) == 0) {
                    $publicHolliday->setDay($date_input);
                    $entityManager->persist($publicHolliday);
                    $entityManager->flush();

                    return new JsonResponse([
                        'success' => true,
                        'text' => "Le jour non travaillé a bien été enregistré."
                    ]);
                }
                return new JsonResponse([
                    'success' => false,
                    'text' => "Ce jour non travaillé est déja enegistré."
                ]);
            }
            return new JsonResponse([
                'success' => false,
                'text' => "Aucun jour non travaillé sélectionné."
            ]);
        }
        throw new NotFoundHttpException('404 not found');
    }
}

//    TODO Cédric créer les droits d'ajout de jour férié
//            if (!$userService->hasRightFunction(Menu::(joursferies a créer), Action:: a definir)) {
//                return $this->redirectToRoute('access_denied');
//            }
