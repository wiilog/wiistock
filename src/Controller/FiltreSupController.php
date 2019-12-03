<?php

namespace App\Controller;

use App\Entity\FiltreSup;
use App\Repository\FiltreSupRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class FiltreSupController
 * @package App\Controller
 * @Route("/filtre-sup")
 */
class FiltreSupController extends AbstractController
{
	/**
	 * @var FiltreSupRepository $filtreSupRepository
	 */
	private $filtreSupRepository;

	public function __construct(FiltreSupRepository $filtreSupRepository)
	{
		$this->filtreSupRepository = $filtreSupRepository;
	}

	/**
     * @Route("/creer", name="filter_sup_new", options={"expose"=true})
     */
    public function new(Request $request): Response
    {
		if ($data = json_decode($request->getContent(), true)) {
			$em = $this->getDoctrine()->getManager();

			$page = $data['page'];
			$user = $this->getUser();

			if (isset($data['dateMin'])) {
				$filter = $this->filtreSupRepository->findOnebyFieldAndPageAndUser(FiltreSup::FIELD_DATE_MIN, $page, $user);
				if (!$filter) {
					$filter = new FiltreSup();
					$filter
						->setField(FiltreSup::FIELD_DATE_MIN)
						->setPage($page)
						->setUser($user);
					$em->persist($filter);
				}
				$filter->setValue($data['dateMin']);
				$em->flush();
			} else {
				$filter = $this->filtreSupRepository->findOnebyFieldAndPageAndUser(FiltreSup::FIELD_DATE_MIN, $page, $user);
				if ($filter) {
					$em->remove($filter);
					$em->flush();
				}
			}
			if (isset($data['dateMax'])) {
				$filter = $this->filtreSupRepository->findOnebyFieldAndPageAndUser(FiltreSup::FIELD_DATE_MAX, $page, $user);
				if (!$filter) {
					$filter = new FiltreSup();
					$filter
						->setField(FiltreSup::FIELD_DATE_MAX)
						->setPage($page)
						->setUser($user);
					$em->persist($filter);
				}
				$filter->setValue($data['dateMax']);
				$em->flush();
			} else {
				$filter = $this->filtreSupRepository->findOnebyFieldAndPageAndUser(FiltreSup::FIELD_DATE_MAX, $page, $user);
				if ($filter) {
					$em->remove($filter);
					$em->flush();
				}
			}
			if (isset($data['statut'])) {
				$filter = $this->filtreSupRepository->findOnebyFieldAndPageAndUser(FiltreSup::FIELD_STATUT, $page, $user);
				if (!$filter) {
					$filter = new FiltreSup();
					$filter
						->setField(FiltreSup::FIELD_STATUT)
						->setPage($page)
						->setUser($user);
					$em->persist($filter);
				}
				$filter->setValue($data['statut']);
				$em->flush();
			} else {
				$filter = $this->filtreSupRepository->findOnebyFieldAndPageAndUser(FiltreSup::FIELD_STATUT, $page, $user);
				if ($filter) {
					$em->remove($filter);
					$em->flush();
				}
			}
			if (isset($data['user'])) {
				$filter = $this->filtreSupRepository->findOnebyFieldAndPageAndUser(FiltreSup::FIELD_USERS, $page, $user);
				if (!$filter) {
					$filter = new FiltreSup();
					$filter
						->setField(FiltreSup::FIELD_USERS)
						->setPage($page)
						->setUser($user);
					$em->persist($filter);
				}
				$carriers = str_replace('|', ',', $data['user']);
				$filter->setValue($carriers);
				$em->flush();
			} else {
				$filter = $this->filtreSupRepository->findOnebyFieldAndPageAndUser(FiltreSup::FIELD_USERS, $page, $user);
				if ($filter) {
					$em->remove($filter);
					$em->flush();
				}
			}
			if (isset($data['type'])) {
				$filter = $this->filtreSupRepository->findOnebyFieldAndPageAndUser(FiltreSup::FIELD_TYPE, $page, $user);
				if (!$filter) {
					$filter = new FiltreSup();
					$filter
						->setField(FiltreSup::FIELD_TYPE)
						->setPage($page)
						->setUser($user);
					$em->persist($filter);
				}
				$filter->setValue($data['type']);
				$em->flush();
			} else {
				$filter = $this->filtreSupRepository->findOnebyFieldAndPageAndUser(FiltreSup::FIELD_TYPE, $page, $user);
				if ($filter) {
					$em->remove($filter);
					$em->flush();
				}
			}
			if (isset($data['location'])) {
				$filter = $this->filtreSupRepository->findOnebyFieldAndPageAndUser(FiltreSup::FIELD_EMPLACEMENT, $page, $user);
				if (!$filter) {
					$filter = new FiltreSup();
					$filter
						->setField(FiltreSup::FIELD_EMPLACEMENT)
						->setPage($page)
						->setUser($user);
					$em->persist($filter);
				}
				$filter->setValue($data['location']);
				$em->flush();
			} else {
				$filter = $this->filtreSupRepository->findOnebyFieldAndPageAndUser(FiltreSup::FIELD_EMPLACEMENT, $page, $user);
				if ($filter) {
					$em->remove($filter);
					$em->flush();
				}
			}
			if (isset($data['colis'])) {
				$filter = $this->filtreSupRepository->findOnebyFieldAndPageAndUser(FiltreSup::FIELD_COLIS, $page, $user);
				if (!$filter) {
					$filter = new FiltreSup();
					$filter
						->setField(FiltreSup::FIELD_COLIS)
						->setPage($page)
						->setUser($user);
					$em->persist($filter);
				}
				$filter->setValue($data['colis']);
				$em->flush();
			} else {
				$filter = $this->filtreSupRepository->findOnebyFieldAndPageAndUser(FiltreSup::FIELD_COLIS, $page, $user);
				if ($filter) {
					$em->remove($filter);
					$em->flush();
				}
			}
			if (isset($data['carriers'])) {
				$filter = $this->filtreSupRepository->findOnebyFieldAndPageAndUser(FiltreSup::FIELD_CARRIERS, $page, $user);
				if (!$filter) {
					$filter = new FiltreSup();
					$filter
						->setField(FiltreSup::FIELD_CARRIERS)
						->setPage($page)
						->setUser($user);
					$em->persist($filter);
				}
				$carriers = str_replace('|', ',', $data['carriers']);
				$filter->setValue($carriers);
				$em->flush();
			} else {
				$filter = $this->filtreSupRepository->findOnebyFieldAndPageAndUser(FiltreSup::FIELD_CARRIERS, $page, $user);
				if ($filter) {
					$em->remove($filter);
					$em->flush();
				}
			}
			if (isset($data['providers'])) {
				$filter = $this->filtreSupRepository->findOnebyFieldAndPageAndUser(FiltreSup::FIELD_PROVIDERS, $page, $user);
				if (!$filter) {
					$filter = new FiltreSup();
					$filter
						->setField(FiltreSup::FIELD_PROVIDERS)
						->setPage($page)
						->setUser($user);
					$em->persist($filter);
				}
				$carriers = str_replace('|', ',', $data['providers']);
				$filter->setValue($carriers);
				$em->flush();
			} else {
				$filter = $this->filtreSupRepository->findOnebyFieldAndPageAndUser(FiltreSup::FIELD_PROVIDERS, $page, $user);
				if ($filter) {
					$em->remove($filter);
					$em->flush();
				}
			}
			if (isset($data['demandCollect'])) {
				$filter = $this->filtreSupRepository->findOnebyFieldAndPageAndUser(FiltreSup::FIELD_DEM_COLLECTE, $page, $user);
				if (!$filter) {
					$filter = new FiltreSup();
					$filter
						->setField(FiltreSup::FIELD_DEM_COLLECTE)
						->setPage($page)
						->setUser($user);
					$em->persist($filter);
				}
				$filter->setValue($data['demandCollect']);
				$em->flush();
			} else {
				$filter = $this->filtreSupRepository->findOnebyFieldAndPageAndUser(FiltreSup::FIELD_DEM_COLLECTE, $page, $user);
				if ($filter) {
					$em->remove($filter);
					$em->flush();
				}
			}

			$em->flush();

			return new JsonResponse();
		} else {
			throw new NotFoundHttpException('404');
		}
    }

	/**
	 * @Route("/api", name="filter_get_by_page", options={"expose"=true})
	 */
    public function getByPage(Request $request): Response
	{
		if ($request->isXmlHttpRequest() && $page = json_decode($request->getContent(), true)) {

			$filters = $this->filtreSupRepository->getFieldAndValueByPageAndUser($page, $this->getUser());

			return new JsonResponse($filters);
		} else {
			throw new NotFoundHttpException('404');
		}
	}
}
