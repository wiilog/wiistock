<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\CategorieStatut;
use App\Entity\Menu;
use App\Entity\MouvementStock;
use App\Repository\EmplacementRepository;
use App\Repository\MouvementStockRepository;
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
 * @Route("/mouvement-stock")
 */
class MouvementStockController extends AbstractController
{
    /**
     * @var MouvementStockRepository
     */
    private $mouvementStockRepository;

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

    public function __construct(EmplacementRepository $emplacementRepository, UtilisateurRepository $utilisateurRepository, StatutRepository $statutRepository, UserService $userService, MouvementStockRepository $mouvementStockRepository)
    {
        $this->emplacementRepository = $emplacementRepository;
        $this->utilisateurRepository = $utilisateurRepository;
        $this->statutRepository = $statutRepository;
        $this->userService = $userService;
        $this->mouvementStockRepository = $mouvementStockRepository;
    }

    /**
     * @Route("/", name="mouvement_stock_index")
     */
    public function index()
    {
        if (!$this->userService->hasRightFunction(Menu::STOCK, Action::LIST)) {
            return $this->redirectToRoute('access_denied');
        }

        return $this->render('mouvement_stock/index.html.twig', [
            'statuts' => $this->statutRepository->findByCategorieName(CategorieStatut::MVT_STOCK),
            'utilisateurs' => $this->utilisateurRepository->findAllSorted(),
            'emplacements' => $this->emplacementRepository->findAll(),
        ]);
    }

    /**
     * @Route("/api", name="mouvement_stock_api", options={"expose"=true}, methods="GET|POST")
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::LIST)) {
                return $this->redirectToRoute('access_denied');
            }

            $mouvements = $this->mouvementStockRepository->findAll();

            $rows = [];
            foreach ($mouvements as $mouvement) {
            	if ($mouvement->getPreparationOrder()) {
            		$orderPath = 'preparation_show';
            		$orderId = $mouvement->getPreparationOrder()->getId();
				} else if ($mouvement->getLivraisonOrder()) {
            		$orderPath = 'livraison_show';
            		$orderId = $mouvement->getLivraisonOrder()->getId();
				} else if ($mouvement->getCollecteOrder()) {
            		$orderPath = 'ordre_collecte_show';
            		$orderId = $mouvement->getCollecteOrder()->getId();
				} else {
            		$orderPath = $orderId = null;
				}

                $rows[] = [
                    'id' => $mouvement->getId(),
                    'date' => $mouvement->getDate() ? $mouvement->getDate()->format('d/m/Y H:i:s') : '',
                    'refArticle' => $mouvement->getArticle() ? $mouvement->getArticle()->getReference() : $mouvement->getRefArticle()->getReference(),
                    'quantite' => $mouvement->getQuantity(),
                    'origine' => $mouvement->getEmplacementFrom() ? $mouvement->getEmplacementFrom()->getLabel() : '',
                    'destination' => $mouvement->getEmplacementTo() ? $mouvement->getEmplacementTo()->getLabel() : '',
                    'type' => $mouvement->getType(),
                    'operateur' => $mouvement->getUser() ? $mouvement->getUser()->getUsername() : '',
                    'actions' => $this->renderView('mouvement_stock/datatableMvtStockRow.html.twig', [
                        'mvt' => $mouvement,
						'orderPath' => $orderPath,
						'orderId' => $orderId
                    ])
                ];
            }

            $data['data'] = $rows;

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/supprimer", name="mvt_stock_delete", options={"expose"=true},methods={"GET","POST"})
     */
    public function delete(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $mouvement = $this->mouvementStockRepository->find($data['mvt']);

            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($mouvement);
            $entityManager->flush();
            return new JsonResponse();
        }

        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/mouvement-stock-infos", name="get_mouvements_stock_for_csv", options={"expose"=true}, methods={"GET","POST"})
     */
    public function getMouvementIntels(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $dateMin = $data['dateMin'] . '00:00:00';
            $dateMax = $data['dateMax'] . '23:59:59';
            $newDateMin = new DateTime($dateMin);
            $newDateMax = new DateTime($dateMax);
            $mouvements = $this->mouvementStockRepository->findByDates($dateMin, $dateMax);
            foreach($mouvements as $mouvement) {
                if ($newDateMin > $mouvement->getDate() || $newDateMax < $mouvement->getDate()) {
                    array_splice($mouvements, array_search($mouvement, $mouvements), 1);
                }
            }

            $headers = [];
            $headers = array_merge($headers, ['date', 'référence article', 'quantité', 'origine', 'destination', 'type', 'opérateur']);
            $data = [];
            $data[] = $headers;

            foreach ($mouvements as $mouvement) {
                $mouvementData = [];

                $mouvementData[] = $mouvement->getDate() ? $mouvement->getDate()->format('d/m/Y H:i:s') : '';
                $mouvementData[] = $mouvement->getRefArticle() ? $mouvement->getRefArticle()->getReference() : $mouvement->getArticle() ? $mouvement->getArticle()->getReference() : '';
				$mouvementData[] = $mouvement->getQuantity();
				$mouvementData[] = $mouvement->getEmplacementFrom() ? $mouvement->getEmplacementFrom()->getLabel() : '';
				$mouvementData[] = $mouvement->getEmplacementTo() ? $mouvement->getEmplacementTo()->getLabel() : '';
                $mouvementData[] = $mouvement->getType();
                $mouvementData[] = $mouvement->getUser() ? $mouvement->getUser()->getUsername() : '';

                $data[] = $mouvementData;
            }
            return new JsonResponse($data);
        } else {
            throw new NotFoundHttpException('404');
        }
    }
}
