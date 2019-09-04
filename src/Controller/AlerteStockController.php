<?php

namespace App\Controller;

use App\Entity\AlerteStock;
use App\Entity\Article;
use App\Entity\Menu;

use App\Entity\ReferenceArticle;
use App\Repository\AlerteStockRepository;
use App\Repository\ArticleRepository;
use App\Repository\UtilisateurRepository;
use App\Repository\ReferenceArticleRepository;

use App\Service\UserService;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @Route("/alerte-stock")
 */
class AlerteStockController extends AbstractController
{
    /**
     * @var AlerteStockRepository
     */
    private $alerteStockRepository;

    /**
     * @var ReferenceArticleRepository
     */
    private $referenceArticleRepository;

    /**
     * @var UtilisateurRepository
     */
    private $utilisateurRepository;

	/**
	 * @var ArticleRepository
	 */
    private $articleRepository;

    /**
     * @var UserService
     */
    private $userService;


    public function __construct(ArticleRepository $articleRepository, AlerteStockRepository $alerteStockRepository, UtilisateurRepository $utilisateurRepository, ReferenceArticleRepository $referenceArticleRepository, UserService $userService)
    {
        $this->alerteStockRepository = $alerteStockRepository;
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->utilisateurRepository = $utilisateurRepository;
        $this->articleRepository = $articleRepository;
        $this->userService = $userService;
    }

    /**
     * @Route("/api", name="alerte_stock_api", options={"expose"=true}, methods="GET|POST")
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            $alertes = $this->alerteStockRepository->findAll();
            $rows = [];


            foreach ($alertes as $alerte) {
				$ref = $alerte->getRefArticle();

				if ($ref) {
					if ($ref->getTypeQuantite() == ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
						$quantiteStock = $ref->getQuantiteStock();
					} else {
						$quantiteStock = $this->articleRepository->getTotalQuantiteByRefAndStatusLabel($ref, Article::STATUT_ACTIF);
					}
				} else {
					$quantiteStock = '';
				}


                $rows[] = [
                    'id' => $alerte->getId(),
                    'Code' => $alerte->getNumero(),
                    "SeuilAlerte" => $alerte->getLimitAlert(),
                    'SeuilSecurite' => $alerte->getLimitSecurity(),
                    'Statut' => $alerte->getActivated() ? 'active' : 'inactive',
                    'Référence' => $alerte->getRefArticle() ? $alerte->getRefArticle()->getLibelle() . '<br>(' . $alerte->getRefArticle()->getReference() . ')' : null,
                    'QuantiteStock' => $quantiteStock,
                    'Utilisateur' => $alerte->getUser() ? $alerte->getUser()->getUsername() : '',
                    'Actions' => $this->renderView('alerte_stock/datatableAlerteStockRow.html.twig', [
                        'alerteId' => $alerte->getId(),
                    ]),
                ];
            }
            $data['data'] = $rows;

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/", name="alerte_stock_index", methods="GET")
     */
    public function index(): Response
    {
        return $this->render('alerte_stock/index.html.twig');
    }

    /**
     * @Route("/creer", name="alerte_stock_new", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function new(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM)) {
                return $this->redirectToRoute('access_denied');
            }

            $em = $this->getDoctrine()->getManager();
            $refArticle = $this->referenceArticleRepository->find($data['reference']);

			// on vérifie qu'une alerte n'existe pas déjà sur cette référence
			$alertAlreadyExist = $this->alerteStockRepository->countByRef($refArticle);
			if ($alertAlreadyExist) {
				$response = [
					'success' => false,
					'msg' => 'Une alerte de stock existe déjà sur cette référence.'
				];
			} elseif (!$refArticle) {
				$response = [
					'success' => false,
					'msg' => 'Veuillez renseigner une référence.'
				];
			} else {
				$alerte = new AlerteStock();
				$date = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
				$alerte
					->setNumero('A-' . $date->format('YmdHis'))
					->setLimitAlert($data['limitAlert'] ? $data['limitAlert'] : null)
					->setLimitSecurity($data['limitSecurity'] ? $data['limitSecurity'] : null)
					->setUser($this->getUser())
					->setActivated(true)
					->setRefArticle($refArticle);

				$em->persist($alerte);
				$em->flush();

				$response = ['success' => true];
			}

			return new JsonResponse($response);
        }

        throw new XmlHttpException('404 not found');
    }

    /**
     * @Route("/api-modifier", name="alerte_api_edit", options={"expose"=true}, methods="GET|POST")
     */
    public function editApi(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM)) {
                return $this->redirectToRoute('access_denied');
            }

            $alerte = $this->alerteStockRepository->find($data['id']);
            $json = $this->renderView('alerte_stock/modalEditAlerteStockContent.html.twig', [
                'alerte' => $alerte,
            ]);

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/modifier", name="alerte_stock_edit", options={"expose"=true}, methods="GET|POST")
     */
    public function edit(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM)) {
                return $this->redirectToRoute('access_denied');
            }

            $alerte = $this->alerteStockRepository->find($data['id']);

            if ($alerte) {
            	$alerte
					->setLimitAlert($data['limitAlert'] == '' ? null : $data['limitAlert'])
					->setLimitSecurity($data['limitSecurity'] == '' ? null : $data['limitSecurity']);
			}
            $em = $this->getDoctrine()->getManager();
            $em->flush();

            return new JsonResponse();
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/supprimer", name="alerte_stock_delete", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function delete(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM)) {
                return $this->redirectToRoute('access_denied');
            }

            $alerte = $this->alerteStockRepository->find($data['alerte']);
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($alerte);
            $entityManager->flush();

            return new JsonResponse();
        }
        throw new NotFoundHttpException('404');
    }

}
