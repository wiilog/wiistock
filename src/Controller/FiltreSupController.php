<?php

namespace App\Controller;

use App\Entity\FiltreSup;
use App\Repository\FiltreSupRepository;
use App\Service\LitigeService;
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

            $filterLabels = [
                'dateMin' => FiltreSup::FIELD_DATE_MIN,
                'dateMax' => FiltreSup::FIELD_DATE_MAX,
                'type' => FiltreSup::FIELD_TYPE,
                'urgence' => FiltreSup::FIELD_EMERGENCY,
                'arrivage_string' => FiltreSup::FIELD_ARRIVAGE_STRING,
                'reception_string' => FiltreSup::FIELD_RECEPTION_STRING,
                'litigeOrigin' => FiltreSup::FIELD_LITIGE_ORIGIN,
                'commande' => FiltreSup::FIELD_COMMANDE,
                'numArrivage' => FiltreSup::FIELD_NUM_ARRIVAGE,
                'anomaly' => FiltreSup::FIELD_ANOMALY,
            ];

            foreach ($filterLabels as $filterLabel => $filterName) {
                if (array_key_exists($filterLabel, $data)) {
                    if (!is_array($data[$filterLabel]) && !strpos($data[$filterLabel], ',') && !strpos($data[$filterLabel], ':')) {
                        return new JsonResponse(false);
                    }
                    $value = is_array($data[$filterLabel]) ? implode(',', $data[$filterLabel]) : $data[$filterLabel];

                    if (!empty($value)) {
                        $filter = $this->filtreSupRepository->findOnebyFieldAndPageAndUser($filterName, $page, $user);
                        if (!$filter) {
                            $filter = new FiltreSup();
                            $filter
                                ->setField($filterName)
                                ->setPage($page)
                                ->setUser($user);
                            $em->persist($filter);
                        }

                        $filter->setValue($value);
                    } else {
                        $filter = $this->filtreSupRepository->findOnebyFieldAndPageAndUser($filterName, $page, $user);
                        if ($filter) {
                            $em->remove($filter);
                        }
                    }
                }
            }

            $filterLabelsSelect2 = [
                'users' => FiltreSup::FIELD_USERS,
                'location' => FiltreSup::FIELD_EMPLACEMENT,
                'reference' => FiltreSup::FIELD_REFERENCE,
                'statut' => FiltreSup::FIELD_STATUT,
                'colis' => FiltreSup::FIELD_COLIS,
                'carriers' => FiltreSup::FIELD_CARRIERS,
                'providers' => FiltreSup::FIELD_PROVIDERS,
                'demCollecte' => FiltreSup::FIELD_DEM_COLLECTE,
                'demande' => FiltreSup::FIELD_DEMANDE,
                'natures' => FiltreSup::FIELD_NATURES
            ];

            foreach ($filterLabelsSelect2 as $filterLabel => $filterName) {
                if (array_key_exists($filterLabel, $data)) {
                    if (!empty($data[$filterLabel])) {
                        $filter = $this->filtreSupRepository->findOnebyFieldAndPageAndUser($filterName, $page, $user);
                        if (!$filter) {
                            $filter = new FiltreSup();
                            $filter
                                ->setField($filterName)
                                ->setPage($page)
                                ->setUser($user);
                            $em->persist($filter);
                        }

                        if (is_array($data[$filterLabel])) {
                            $value = [];
                            foreach ($data[$filterLabel] as $elem) {
                                if (!strpos($elem['id'], ',')
                                    || !strpos($elem['text'], ',')
                                    || !strpos($elem['id'], ':')
                                    || !strpos($elem['text'], ':')) {
                                    return new JsonResponse(false);
                                }
                                $value[] = $elem['id'] . ':' . $elem['text'];
                            }
                            $value = implode(',', $value);
                        } else {
                            $value = $data[$filterLabel];
                        }
                        $filter->setValue($value);
                    } else {
                        $filter = $this->filtreSupRepository->findOnebyFieldAndPageAndUser($filterName, $page, $user);
                        if ($filter) {
                            $em->remove($filter);
                        }
                    }
                }
            }
            $em->flush();
            return new JsonResponse(true);
        } else {
            throw new NotFoundHttpException('404');
        }
    }

    /**
     * @Route("/api", name="filter_get_by_page", options={"expose"=true})
     * @param Request $request
     * @param LitigeService $litigeService
     * @return Response
     */
    public function getByPage(Request $request,
                              LitigeService $litigeService): Response
    {
        if ($request->isXmlHttpRequest() && $page = json_decode($request->getContent(), true)) {

            $filters = $this->filtreSupRepository->getFieldAndValueByPageAndUser($page, $this->getUser());
            if ($page === FiltreSup::PAGE_LITIGE) {
                $translations = $litigeService->getLitigeOrigin();
                foreach ($filters as $index => $filter) {
                    if (isset($translations[$filter['value']])) {
                        $filters[$index]['value'] = $translations[$filter['value']];
                        $filters[$index]['value'] = $translations[$filter['value']];
                    }
                }
            }

            return new JsonResponse($filters);
        } else {
            throw new NotFoundHttpException('404');
        }
    }
}
