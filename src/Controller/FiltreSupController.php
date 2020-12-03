<?php

namespace App\Controller;

use App\Entity\FiltreSup;
use App\Service\FilterSupService;
use App\Service\LitigeService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class FiltreSupController
 * @package App\Controller
 * @Route("/filtre-sup")
 */
class FiltreSupController extends AbstractController
{

    /**
     * @Route("/creer", name="filter_sup_new", options={"expose"=true})
     * @param EntityManagerInterface $entityManager
     * @param FilterSupService $filterSupService
     * @param Request $request
     * @return Response
     * @throws NonUniqueResultException
     */
    public function new(EntityManagerInterface $entityManager,
                        FilterSupService $filterSupService,
                        Request $request): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $page = $data['page'];
            $user = $this->getUser();

            $filtreSupRepository = $entityManager->getRepository(FiltreSup::class);
            $filterLabels = [
                'dateMin' => FiltreSup::FIELD_DATE_MIN,
                'dateMax' => FiltreSup::FIELD_DATE_MAX,
                'expectedDate' => FiltreSup::FIELD_DATE_EXCEPTED,
                'type' => FiltreSup::FIELD_TYPE,
                'emergency' => FiltreSup::FIELD_EMERGENCY,
                'arrivage_string' => FiltreSup::FIELD_ARRIVAGE_STRING,
                'reception_string' => FiltreSup::FIELD_RECEPTION_STRING,
                'litigeOrigin' => FiltreSup::FIELD_LITIGE_ORIGIN,
                'commande' => FiltreSup::FIELD_COMMANDE,
                'numArrivage' => FiltreSup::FIELD_NUM_ARRIVAGE,
                'anomaly' => FiltreSup::FIELD_ANOMALY,
                'customs' => FiltreSup::FIELD_CUSTOMS,
                'frozen' => FiltreSup::FIELD_FROZEN,
                'statusEntity' => FiltreSup::FIELD_STATUS_ENTITY,
                'alert' => FiltreSup::FIELD_ALERT
            ];

            foreach ($filterLabels as $filterLabel => $filterName) {
                if (array_key_exists($filterLabel, $data)) {
                    if (!is_array($data[$filterLabel]) && (strpos($data[$filterLabel], ',') || strpos($data[$filterLabel], ':'))) {
                        return new JsonResponse(false);
                    }
                    $value = is_array($data[$filterLabel]) ? implode(',', $data[$filterLabel]) : $data[$filterLabel];

                    if (!empty($value)) {
                        $filter = $filtreSupRepository->findOnebyFieldAndPageAndUser($filterName, $page, $user);
                        if (!$filter) {
                            $filter = $filterSupService->createFiltreSup($page, $filterName, null, $user);
                            $entityManager->persist($filter);
                        }

                        $filter->setValue($value);
                    } else {
                        $filter = $filtreSupRepository->findOnebyFieldAndPageAndUser($filterName, $page, $user);
                        if ($filter) {
                            $entityManager->remove($filter);
                        }
                    }
                }
            }

            $filterLabelsSelect2 = [
                'utilisateurs' => FiltreSup::FIELD_USERS,
                'multipleTypes' => FiltreSup::FIELD_MULTIPLE_TYPES,
                'declarants' => FiltreSup::FIELD_DECLARANTS,
                'emplacement' => FiltreSup::FIELD_EMPLACEMENT,
                'reference' => FiltreSup::FIELD_REFERENCE,
                'statut' => FiltreSup::FIELD_STATUT,
                'colis' => FiltreSup::FIELD_COLIS,
                'carriers' => FiltreSup::FIELD_CARRIERS,
                'providers' => FiltreSup::FIELD_PROVIDERS,
                'demCollecte' => FiltreSup::FIELD_DEM_COLLECTE,
                'demande' => FiltreSup::FIELD_DEMANDE,
                'natures' => FiltreSup::FIELD_NATURES,
                'disputeNumber' => FiltreSup::FIELD_LITIGE_DISPUTE_NUMBER,
                'receivers' => FiltreSup::FIELD_RECEIVERS,
                'requesters' => FiltreSup::FIELD_REQUESTERS,
                'operators' => FiltreSup::FIELD_OPERATORS,
                'dispatchNumber' => FiltreSup::FIELD_DISPATCH_NUMBER,
                'emergencyMultiple' => FiltreSup::FIELD_EMERGENCY_MULTIPLE
            ];

            foreach ($filterLabelsSelect2 as $filterLabel => $filterName) {
                if (array_key_exists($filterLabel, $data)) {
                    if (!empty($data[$filterLabel])) {
                        $filter = $filtreSupRepository->findOnebyFieldAndPageAndUser($filterName, $page, $user);
                        if (!$filter) {
                            $filter = $filterSupService->createFiltreSup($page, $filterName, null, $user);
                            $entityManager->persist($filter);
                        }
                        if (is_array($data[$filterLabel])) {
                            $value = [];
                            foreach ($data[$filterLabel] as $elem) {
                                if (strpos($elem['id'], ',')
                                    || strpos($elem['text'], ',')
                                    || strpos($elem['id'], ':')
                                    || strpos($elem['text'], ':')) {
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
                        $filter = $filtreSupRepository->findOnebyFieldAndPageAndUser($filterName, $page, $user);
                        if ($filter) {
                            $entityManager->remove($filter);
                        }
                    }
                }
            }
            $entityManager->flush();
            return new JsonResponse(true);
        } else {
            throw new BadRequestHttpException();
        }
    }

    /**
     * @Route("/api", name="filter_get_by_page", options={"expose"=true})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param LitigeService $litigeService
     * @return Response
     */
    public function getByPage(Request $request,
                              EntityManagerInterface $entityManager,
                              LitigeService $litigeService): Response
    {
        if ($request->isXmlHttpRequest() && $page = json_decode($request->getContent(), true)) {
            $filtreSupRepository = $entityManager->getRepository(FiltreSup::class);

            $filters = $filtreSupRepository->getFieldAndValueByPageAndUser($page, $this->getUser());
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
            throw new BadRequestHttpException();
        }
    }
}
