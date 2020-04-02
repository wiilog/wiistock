<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\CategorieStatut;
use App\Entity\Emplacement;
use App\Entity\Menu;

use App\Entity\MouvementStock;
use App\Entity\Statut;

use App\Entity\Utilisateur;
use App\Service\MouvementStockService;
use App\Service\UserService;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
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
     * @Route("/", name="mouvement_stock_index")
     * @param UserService $userService
     * @param EntityManagerInterface $entityManager
     * @return RedirectResponse|Response
     */
    public function index(UserService $userService,
                          EntityManagerInterface $entityManager)
    {
        if (!$userService->hasRightFunction(Menu::STOCK, Action::DISPLAY_MOUV_STOC)) {
            return $this->redirectToRoute('access_denied');
        }

        $statutRepository = $entityManager->getRepository(Statut::class);
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);

        return $this->render('mouvement_stock/index.html.twig', [
            'statuts' => $statutRepository->findByCategorieName(CategorieStatut::MVT_STOCK),
            'emplacements' => $emplacementRepository->findAll(),
        ]);
    }

    /**
     * @Route("/api", name="mouvement_stock_api", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param UserService $userService
     * @param MouvementStockService $mouvementStockService
     * @return Response
     * @throws Exception
     */
    public function api(Request $request,
                        UserService $userService,
                        MouvementStockService $mouvementStockService): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$userService->hasRightFunction(Menu::STOCK, Action::DISPLAY_MOUV_STOC)) {
                return $this->redirectToRoute('access_denied');
            }

            /** @var Utilisateur $user */
            $user = $this->getUser();

            $data = $mouvementStockService->getDataForDatatable($user, $request->request);

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/supprimer", name="mvt_stock_delete", options={"expose"=true},methods={"GET","POST"})
     * @param Request $request
     * @param UserService $userService
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function delete(Request $request,
                           UserService $userService,
                           EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $mouvementStockRepository = $entityManager->getRepository(MouvementStock::class);
            $mouvement = $mouvementStockRepository->find($data['mvt']);

            if (!$userService->hasRightFunction(Menu::STOCK, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }

            $entityManager->remove($mouvement);
            $entityManager->flush();
            return new JsonResponse();
        }

        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/mouvement-stock-infos", name="get_mouvements_stock_for_csv", options={"expose"=true}, methods={"GET","POST"})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws Exception
     */
    public function getMouvementIntels(Request $request,
                                       EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $dateMin = $data['dateMin'] . ' 00:00:00';
            $dateMax = $data['dateMax'] . ' 23:59:59';

			$dateTimeMin = DateTime::createFromFormat('d/m/Y H:i:s', $dateMin);
			$dateTimeMax = DateTime::createFromFormat('d/m/Y H:i:s', $dateMax);

            $mouvementStockRepository = $entityManager->getRepository(MouvementStock::class);

            $mouvements = $mouvementStockRepository->findByDates($dateTimeMin, $dateTimeMax);
            foreach($mouvements as $mouvement) {
                if ($dateTimeMin > $mouvement->getDate() || $dateTimeMax < $mouvement->getDate()) {
                    array_splice($mouvements, array_search($mouvement, $mouvements), 1);
                }
            }

            $headers = ['date', 'ordre', 'référence article', 'quantité', 'origine', 'destination', 'type', 'opérateur'];
            $data = [];
            $data[] = $headers;

            foreach ($mouvements as $mouvement) {
                $mouvementData = [];
                $reference = $mouvement->getRefArticle() ? $mouvement->getRefArticle()->getReference() : null;
                // TODO code-barre au lieu de référence ??
                $reference = $reference ? $reference : $mouvement->getArticle()->getReference();

                $orderNo = null;
				if ($mouvement->getPreparationOrder()) {
					$orderNo = $mouvement->getPreparationOrder()->getNumero();
				} else if ($mouvement->getLivraisonOrder()) {
					$orderNo = $mouvement->getLivraisonOrder()->getNumero();
				} else if ($mouvement->getCollecteOrder()) {
					$orderNo = $mouvement->getCollecteOrder()->getNumero();
				} else if ($mouvement->getReceptionOrder()) {
					$orderNo = $mouvement->getReceptionOrder()->getNumeroReception();
				}
                $mouvementData[] = $mouvement->getDate() ? $mouvement->getDate()->format('d/m/Y H:i:s') : '';
                $mouvementData[] = $orderNo ? ' ' . $orderNo : '';
                $mouvementData[] = $reference;
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
