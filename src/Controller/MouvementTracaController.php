<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Arrivage;
use App\Entity\CategorieStatut;
use App\Entity\Menu;
use App\Entity\MouvementTraca;
use App\Repository\EmplacementRepository;
use App\Repository\MouvementTracaRepository;
use App\Repository\StatutRepository;
use App\Repository\UtilisateurRepository;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
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
     * ArrivageController constructor.
     */

    public function __construct(EmplacementRepository $emplacementRepository, UtilisateurRepository $utilisateurRepository, StatutRepository $statutRepository, UserService $userService, MouvementTracaRepository $mouvementTracaRepository)
    {
        $this->emplacementRepository = $emplacementRepository;
        $this->utilisateurRepository = $utilisateurRepository;
        $this->statutRepository = $statutRepository;
        $this->userService = $userService;
        $this->mouvementRepository = $mouvementTracaRepository;
    }

    /**
     * @Route("/", name="mvt_traca_index")
     */
    public function index()
    {
        if (!$this->userService->hasRightFunction(Menu::ARRIVAGE, Action::LIST)) {
            return $this->redirectToRoute('access_denied');
        }

        return $this->render('mouvement_traca/index.html.twig', [
            'statuts' => $this->statutRepository->findByCategorieName(CategorieStatut::MVT_TRACA),
            'utilisateurs' => $this->utilisateurRepository->findAllSorted(),
            'emplacements' => $this->emplacementRepository->findAll(),
        ]);
    }

    /**
     * @Route("/api", name="mvt_traca_api", options={"expose"=true}, methods="GET|POST")
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::ARRIVAGE, Action::LIST)) {
                return $this->redirectToRoute('access_denied');
            }

            $mvts = $this->mouvementRepository->findAll();

            $rows = [];
            foreach ($mvts as $mvt) {
				$dateArray = explode('_', $mvt->getDate());
				$date = new DateTime($dateArray[0]);
                $rows[] = [
                    'id' => $mvt->getId(),
                    'date' => $date->format('d/m/Y H:i:s'),
                    'refArticle' => $mvt->getRefArticle(),
                    'refEmplacement' => $mvt->getRefEmplacement(),
                    'type' => $mvt->getType(),
                    'operateur' => $mvt->getOperateur(),
                    'Actions' => $this->renderView('mouvement_traca/datatableMvtStockRow.html.twig', [
                        'mvt' => $mvt,
                    ])
                ];
            }

            $data['data'] = $rows;

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/supprimer", name="mvt_traca_delete", options={"expose"=true},methods={"GET","POST"})
     */
    public function delete(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $mvt = $this->mouvementRepository->find($data['mvt']);

            if (!$this->userService->hasRightFunction(Menu::ARRIVAGE, Action::DELETE)) {
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
     */
    public function getMouvementIntels(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $dateMin = $data['dateMin'] . '00:00:00';
            $dateMax = $data['dateMax'] . '23:59:59';
            $newDateMin = new DateTime($dateMin);
            $newDateMax = new DateTime($dateMax);
            $mouvements = $this->mouvementRepository->findByDates($dateMin, $dateMax);
            foreach($mouvements as $mouvement) {
                $date = substr($mouvement->getDate(),0, -10);
                $newDate = new DateTime($date);
                if ($newDateMin >= $newDate || $newDateMax <= $newDate) {
                    array_splice($mouvements, array_search($mouvement, $mouvements), 1);
                }
            }

            $headers = [];
            $headers = array_merge($headers, ['date', 'colis', 'emplacement', 'type', 'opérateur']);
            $data = [];
            $data[] = $headers;

            foreach ($mouvements as $mouvement) {
                $mouvementData = [];

                $mouvementData[] = substr($mouvement->getDate(), 0,10). ' ' . substr($mouvement->getDate(), 11,8);
                $mouvementData[] = $mouvement->getRefArticle();
                $mouvementData[] = $mouvement->getRefEmplacement();
                $mouvementData[] = $mouvement->getType();
                $mouvementData[] = $mouvement->getOperateur();

                $data[] = $mouvementData;
            }
            return new JsonResponse($data);
        } else {
            throw new NotFoundHttpException('404');
        }
    }
}
