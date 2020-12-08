<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Arrivage;
use App\Entity\Article;
use App\Entity\CategorieCL;
use App\Entity\Menu;
use App\Entity\ReceptionTraca;
use App\Entity\Utilisateur;
use App\Service\CSVExportService;
use App\Service\FreeFieldService;
use App\Service\ReceptionTracaService;
use App\Service\UserService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Throwable;

/**
 * @Route("/receptions_traca")
 */
class ReceptionTracaController extends AbstractController
{

    /**
     * @var UserService
     */
    private $userService;

    /**
     * @var ReceptionTracaService
     */
    private $receptionTracaService;

    /**
     * ReceptionTracaController constructor.
     * @param UserService $userService
     * @param ReceptionTracaService $receptionTracaService
     */
    public function __construct(UserService $userService,
                                ReceptionTracaService $receptionTracaService)
    {
        $this->userService = $userService;
        $this->receptionTracaService = $receptionTracaService;
    }

    /**
     * @Route("/", name="reception_traca_index", methods={"GET"})
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function index(EntityManagerInterface $entityManager): Response
    {
        if (!$this->userService->hasRightFunction(Menu::TRACA, Action::DISPLAY_ASSO)) {
            return $this->redirectToRoute('access_denied');
        }

        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);

        return $this->render('reception_traca/index.html.twig', [
            'utilisateurs' => $utilisateurRepository->findBy(['status' => true], ['username' => 'ASC']),
        ]);
    }

    /**
     * @Route("/api", name="reception_traca_api", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @return Response
     * @throws Exception
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::TRACA, Action::DISPLAY_ASSO)) {
                return $this->redirectToRoute('access_denied');
            }

            $data = $this->receptionTracaService->getDataForDatatable($request->request);

            return new JsonResponse($data);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/supprimer", name="reception_traca_delete", options={"expose"=true},methods={"GET","POST"})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function delete(Request $request,
                           EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::TRACA, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }

            $receptionTracaRepository = $entityManager->getRepository(ReceptionTraca::class);

            $recep = $receptionTracaRepository->find($data['recep']);

            $entityManager->remove($recep);
            $entityManager->flush();

            return new JsonResponse();
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/creer", name="reception_traca_new", options={"expose"=true},methods={"GET","POST"})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
		if (!$this->userService->hasRightFunction(Menu::TRACA, Action::CREATE)) {
			return $this->redirectToRoute('access_denied');
		}

		/** @var Utilisateur $loggedUser */
		$loggedUser = $this->getUser();

		$arrivageRepository = $entityManager->getRepository(Arrivage::class);
        $errors = [];
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $arrivages = json_decode($data['numero_arrivage'], true);
            if (count($arrivages) > 0) {
                foreach ($arrivages as $arrivage) {
                    $arrivageDB = $arrivageRepository->findOneBy([
                        'numeroArrivage' => $arrivage
                    ]);
                    if (isset($arrivageDB)) {
                        $recep = new ReceptionTraca();
                        $recep
                            ->setArrivage($arrivage)
                            ->setNumber($data['numero_réception'])
                            ->setDateCreation(new DateTime('now'))
                            ->setUser($loggedUser);
                        $entityManager->persist($recep);
                    } else {
                        $errors[] = $arrivage;
                    }
                }
            } else {
                $recep = new ReceptionTraca();
                $recep
                    ->setArrivage(isset($data['numero_arrivage']) ? $data['numero_arrivage'] : '')
                    ->setNumber($data['numero_réception'])
                    ->setDateCreation(new DateTime('now'))
                    ->setUser($loggedUser);
                $entityManager->persist($recep);
            }

            $entityManager->flush();
            return new JsonResponse([
                'success' => count($errors) === 0,
                'msg' => 'Les numéros suivants ne correspondent à aucun arrivage connu : ' . implode(", ", $errors) . "."
            ]);
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/csv", name="get_tracking_reception_csv", options={"expose"=true}, methods="GET")
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @param CSVExportService $csvService
     * @return Response
     */
    public function exportAllArticles(EntityManagerInterface $entityManager,
                                      Request $request,
                                      CSVExportService $csvService): Response
    {

        $dateMin = $request->query->get('dateMin');
        $dateMax = $request->query->get('dateMax');

        try {
            $dateTimeMin = DateTime::createFromFormat('Y-m-d H:i:s', $dateMin . ' 00:00:00');
            $dateTimeMax = DateTime::createFromFormat('Y-m-d H:i:s', $dateMax . ' 23:59:59');
        } catch (Throwable $throwable) {
        }

        if (!empty($dateTimeMin) && !empty($dateTimeMax)) {

            $today = new DateTime();
            $todayStr = $today->format("d-m-Y-H-i-s");

            $headers = [
                'date',
                'arrivage',
                'reception',
                'utilisateur'
            ];

            return $csvService->streamResponse(function ($output) use ($entityManager, $csvService, $dateTimeMin, $dateTimeMax) {
                $receptionTracaRepository = $entityManager->getRepository(ReceptionTraca::class);

                $trackingReceptions = $receptionTracaRepository->iterateBetween($dateTimeMin, $dateTimeMax);
                /** @var ReceptionTraca $trackingReception */
                foreach ($trackingReceptions as $trackingReception) {
                    $csvService->putLine($output, $trackingReception->serialize());
                }
            }, "association-br_${todayStr}.csv", $headers);
        }
        else {
            throw new BadRequestHttpException();
        }
    }
}
