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

/**
 * @Route("/nonworkeddays")
 */
class NonWorkedDaysController extends AbstractController
{
    /**
     * @var Twig_Environment
     */
    private $templating;

    /**
     * @var GlobalParamService
     */
    private $globalParamService;

    /**
     * @var ParametrageGlobalRepository
     */
    private $paramGlobalRepository;

    /**
     * @var NonWorkedDaysRepository
     */
    private $nonWorkedDayRepository;

    public function __construct(Twig_Environment $templating,
                                GlobalParamService $globalParamService,
                                ParametrageGlobalRepository $parametrageGlobalRepository,
                                NonWorkedDaysRepository $nonWorkedDaysRepository)
    {
     $this->paramGlobalRepository = $parametrageGlobalRepository;
     $this->globalParamService = $globalParamService;
     $this->nonWorkedDayRepository = $nonWorkedDaysRepository;
     $this->templating = $templating;
    }

    public function index(EntityManagerInterface $entityManager)
    {
        //TODO CR créer les droits d'affichage des jours fériés

    }

    /**
     * @Route("/new", name="nonworkedday_new", options={"expose"=true})
     */
    public function newNonWorkedDay()
    {

    }
}

