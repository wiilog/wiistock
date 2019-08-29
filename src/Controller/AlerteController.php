<?php

namespace App\Controller;

use App\Entity\Alerte;
use App\Entity\Menu;

use App\Repository\AlerteRepository;
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
 * @Route("/alerte")
 */
class AlerteController extends AbstractController
{
    /**
     * @var AlerteRepository
     */
    private $alerteRepository;

    /**
     * @var ReferenceArticleRepository
     */
    private $referenceArticleRepository;

    /**
     * @var UtilisateurRepository
     */
    private $utilisateurRepository;

    /**
     * @var UserService
     */
    private $userService;


    public function __construct(AlerteRepository $alerteRepository, UtilisateurRepository $utilisateurRepository, ReferenceArticleRepository $referenceArticleRepository, UserService $userService)
    {
        $this->alerteRepository = $alerteRepository;
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->utilisateurRepository = $utilisateurRepository;
        $this->userService = $userService;
    }

    /**
     * @Route("/api", name="alerte_api", options={"expose"=true}, methods="GET|POST")
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            $alertes = $this->alerteRepository->findAll();
            $rows = [];

            foreach ($alertes as $alerte) {
                $rows[] = [
                    'id' => $alerte->getId(),
                    'Code' => $alerte->getNumero(),
                    "SeuilAlerte" => $alerte->getLimitAlert(),
                    'SeuilSecurite' => $alerte->getLimitSecurity(),
                    'Statut' => $alerte->getActivated() ? 'active' : 'inactive',
                    'Référence' => $alerte->getRefArticle() ? $alerte->getRefArticle()->getLibelle() . '<br>(' . $alerte->getRefArticle()->getReference() . ')' : null,
                    'QuantiteStock' => $alerte->getRefArticle() ? $alerte->getRefArticle()->getQuantiteStock() : null,
                    'Utilisateur' => $alerte->getUser() ? $alerte->getUser()->getUsername() : null,
                    'Actions' => $this->renderView('alerte/datatableAlerteRow.html.twig', [
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
     * @Route("/", name="alerte_index", methods="GET")
     */
    public function index(): Response
    {
        return $this->render('alerte/index.html.twig');
    }

    /**
     * @Route("/creer", name="alerte_new", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function new(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM)) {
                return $this->redirectToRoute('access_denied');
            }

            $em = $this->getDoctrine()->getManager();
            $refArticle = $this->referenceArticleRepository->find($data['reference']);

			// on vérifie qu'une alerte n'existe pas déjà sur cette référence
			$alertAlreadyExist = $this->alerteRepository->countByRef($refArticle);
			if ($alertAlreadyExist) {
				$response = [
					'success' => false,
					'msg' => 'Une alerte existe déjà sur cette référence.'
				];
			} elseif (!$refArticle) {
				$response = ['success' => false];
			} else {
				$alerte = new Alerte();
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
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM)) {
                return $this->redirectToRoute('access_denied');
            }

            $alerte = $this->alerteRepository->find($data['id']);
            $json = $this->renderView('alerte/modalEditAlerteContent.html.twig', [
                'alerte' => $alerte,
            ]);

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/modifier", name="alerte_edit", options={"expose"=true}, methods="GET|POST")
     */
    public function edit(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM)) {
                return $this->redirectToRoute('access_denied');
            }

            $alerte = $this->alerteRepository->find($data['id']);

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
     * @Route("/supprimer", name="alerte_delete", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function delete(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM)) {
                return $this->redirectToRoute('access_denied');
            }

            $alerte = $this->alerteRepository->find($data['alerte']);
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($alerte);
            $entityManager->flush();

            return new JsonResponse();
        }
        throw new NotFoundHttpException('404');
    }

}
