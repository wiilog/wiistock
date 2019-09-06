<?php

namespace App\Controller;

use App\Entity\AlerteExpiry;

use App\Entity\Menu;
use App\Repository\AlerteExpiryRepository;
use App\Repository\ReferenceArticleRepository;
use App\Service\AlerteService;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @Route("/alerte-peremption")
 */
class AlerteExpiryController extends AbstractController
{
	/**
	 * @var AlerteExpiryRepository
	 */
	private $alerteExpiryRepository;

	/**
	 * @var UserService
	 */
	private $userService;

	/**
	 * @var ReferenceArticleRepository
	 */
	private $referenceArticleRepository;

	/**
	 * @var AlerteService
	 */
	private $alerteService;


    public function __construct(AlerteService $alerteService, AlerteExpiryRepository $alerteExpiryRepository, UserService $userService, ReferenceArticleRepository $referenceArticleRepository)
    {
		$this->alerteExpiryRepository = $alerteExpiryRepository;
		$this->userService = $userService;
		$this->referenceArticleRepository = $referenceArticleRepository;
		$this->alerteService = $alerteService;
    }

    /**
     * @Route("/api", name="alerte_expiry_api", options={"expose"=true}, methods="GET|POST")
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            $alertes = $this->alerteExpiryRepository->findAll();
            $rows = [];
			$refs = [];

            foreach ($alertes as $alerte) {
				if (!$alerte->getRefArticle()) {
					$refs[] = [$alerte];
				} else {
					$refId = $alerte->getRefArticle()->getId();
					$refs[$refId][] = $alerte;
				}
			}

            foreach($refs as $refId => $alertes) {
            	$reference = $this->referenceArticleRepository->find($refId);

            	$delay = $user = '';
            	$active = false;
            	$alertesId = [];

            	foreach($alertes as $alerte) {
					$delay .= $alerte->getNbPeriod() . ' ' . $alerte->getTypePeriod();
					if ($alerte->getNbPeriod() > 1 && $alerte->getTypePeriod() != 'mois') $delay .= 's';
					$delay .= '<br>';

					if ($this->alerteService->isAlerteExpiryActive($alerte)) $active = true;

					$user .= $alerte->getUser() ? $alerte->getUser()->getUsername() : '';
					$user .= '<br>';

					$alertesId[] = $alerte->getId();
				}

            	$rows[] = [
					'Référence' => $reference ? $reference->getReference(). '<br>(' . $reference->getLibelle() . ')' : 'toutes',
					'Date péremption' => $reference ? $reference->getExpiryDate() ? $reference->getExpiryDate()->format('d/m/Y') : '-' : '-',
					'Délai alerte' => $delay,
					'Active' => $active,
					'Utilisateur' => $user,
					'Actions' => $this->renderView('alerte_expiry/datatableAlerteExpiryRow.html.twig', [
						'alertesId' => $alertesId,
					]),
                ];
            }
            $data['data'] = $rows;

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/liste/{filter}", name="alerte_expiry_index", methods="GET")
     */
    public function index($filter = null): Response
    {
    	$nbAlerts = $this->alerteExpiryRepository->countDateReached();

        return $this->render('alerte_expiry/index.html.twig', [
        	'nbAlerts' => $nbAlerts,
			'filter' => $filter == 'active'
		]);
    }

    /**
     * @Route("/creer", name="alerte_expiry_new", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function new(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM)) {
                return $this->redirectToRoute('access_denied');
            }

            $em = $this->getDoctrine()->getManager();

            if ($data['allRef']) {
            	$refArticle = null;
			} else {
            	$refArticle = $this->referenceArticleRepository->find($data['reference']);
			}

            switch($data['period']) {
				case AlerteExpiry::TYPE_PERIOD_DAY:
				case AlerteExpiry::TYPE_PERIOD_WEEK:
				case AlerteExpiry::TYPE_PERIOD_MONTH:
					$typePeriod = $data['period'];
					break;
				default:
					return new JsonResponse(false);
			}

            $alerte = new AlerteExpiry();
            $alerte
				->setNbPeriod($data['nbPeriods'])
				->setTypePeriod($typePeriod)
				->setUser($this->getUser())
				->setRefArticle($refArticle);

			$em->persist($alerte);
			$em->flush();

			return new JsonResponse(true);
        }

        throw new XmlHttpException('404 not found');
    }

    /**
     * @Route("/api-modifier", name="alerte_expiry_api_edit", options={"expose"=true}, methods="GET|POST")
     */
    public function editApi(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM)) {
                return $this->redirectToRoute('access_denied');
            }

            $alerte = $this->alerteExpiryRepository->find($data['id']);
            $json = $this->renderView('alerte_expiry/modalEditAlerteExpiryContent.html.twig', [
                'alerte' => $alerte,
            ]);

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/modifier", name="alerte_expiry_edit", options={"expose"=true}, methods="GET|POST")
     */
    public function edit(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM)) {
                return $this->redirectToRoute('access_denied');
            }

            $alerte = $this->alerteExpiryRepository->find($data['id']);

			if ($data['allRef']) {
				$refArticle = null;
			} else {
				$refArticle = $this->referenceArticleRepository->find($data['reference']);
			}

			switch($data['period']) {
				case AlerteExpiry::TYPE_PERIOD_DAY:
				case AlerteExpiry::TYPE_PERIOD_WEEK:
				case AlerteExpiry::TYPE_PERIOD_MONTH:
					$typePeriod = $data['period'];
					break;
				default:
					return new JsonResponse(false);
			}
            if ($alerte) {
            	$alerte
					->setRefArticle($refArticle)
					->setNbPeriod($data['nbPeriods'])
					->setTypePeriod($typePeriod);
			}
            $em = $this->getDoctrine()->getManager();
            $em->flush();

            return new JsonResponse();
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/supprimer", name="alerte_expiry_delete", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function delete(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM)) {
                return $this->redirectToRoute('access_denied');
            }

            $alerte = $this->alerteExpiryRepository->find($data['alerte']);
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($alerte);
            $entityManager->flush();

            return new JsonResponse();
        }
        throw new NotFoundHttpException('404');
    }

}
