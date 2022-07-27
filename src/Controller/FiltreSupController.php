<?php

namespace App\Controller;

use App\Entity\FiltreSup;
use App\Service\FilterSupService;
use App\Service\DisputeService;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Utilisateur;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use WiiCommon\Helper\Stream;

/**
 * Class FiltreSupController
 * @package App\Controller
 * @Route("/filtre-sup")
 */
class FiltreSupController extends AbstractController
{

    /**
     * @Route("/creer", name="filter_sup_new", options={"expose"=true})
     */
    public function new(EntityManagerInterface $entityManager,
                        FilterSupService $filterSupService,
                        Request $request): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $page = $data['page'];

            /**
             * @var Utilisateur $user
             */
            $user = $this->getUser();
            $filtreSupRepository = $entityManager->getRepository(FiltreSup::class);
            $filterLabels = [
                'dateMin' => FiltreSup::FIELD_DATE_MIN,
                'dateMax' => FiltreSup::FIELD_DATE_MAX,
                'expectedDate' => FiltreSup::FIELD_DATE_EXPECTED,
                'type' => FiltreSup::FIELD_TYPE,
                'emergency' => FiltreSup::FIELD_EMERGENCY,
                'reception_string' => FiltreSup::FIELD_RECEPTION_STRING,
                'litigeOrigin' => FiltreSup::FIELD_LITIGE_ORIGIN,
                'commande' => FiltreSup::FIELD_COMMANDE,
                'numArrivage' => FiltreSup::FIELD_NUM_ARRIVAGE,
                'anomaly' => FiltreSup::FIELD_ANOMALY,
                'customs' => FiltreSup::FIELD_CUSTOMS,
                'frozen' => FiltreSup::FIELD_FROZEN,
                'statusEntity' => FiltreSup::FIELD_STATUS_ENTITY,
                'alert' => FiltreSup::FIELD_ALERT,
                'subject' => FiltreSup::FIELD_SUBJECT,
                'destination' => FiltreSup::FIELD_DESTINATION,
                'fileNumber' => FiltreSup::FIELD_FILE_NUMBER,
                'roundNumber' => FiltreSup::FIELD_ROUND_NUMBER,
                'requestNumber' => FiltreSup::FIELD_REQUEST_NUMBER,
                'category' => FiltreSup::FIELD_CATEGORY,
                'contact' => FiltreSup::FIELD_CONTACT,
            ];
            foreach ($user->getFiltresSup() as $filtreSup) {
                if ($filtreSup->getPage() === $page) {
                    $entityManager->remove($filtreSup);
                }
            }
            $entityManager->flush();
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
                'commandList' => FiltreSup::FIELD_COMMAND_LIST,
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
                'buyers' => FiltreSup::FIELD_BUYERS,
                'managers' => FiltreSup::FIELD_MANAGERS,
                'operators' => FiltreSup::FIELD_OPERATORS,
                'dispatchNumber' => FiltreSup::FIELD_DISPATCH_NUMBER,
                'emergencyMultiple' => FiltreSup::FIELD_EMERGENCY_MULTIPLE,
                'businessUnit' => FiltreSup::FIELD_BUSINESS_UNIT,
                'deliverers' => FiltreSup::FIELD_DELIVERERS,
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

            $filterList = [
                'planning-status-',
                'date-choice'
            ];
            foreach ($filterList as $filterItem) {
                $matches = Stream::from($data)
                    ->filter(fn($element, $key) => str_starts_with($key, $filterItem))
                    ->toArray();
                foreach ($matches as $key => $match) {
                    $filterName = $key;
                    if (!is_array($match) && (strpos($match, ',') || strpos($match, ':'))) {
                        return new JsonResponse(false);
                    }
                    $value = is_array($match) ? implode(',', $match) : $match;

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
            $entityManager->flush();
            return new JsonResponse(true);
        } else {
            throw new BadRequestHttpException();
        }
    }

    /**
     * @Route("/api", name="filter_get_by_page", options={"expose"=true}, condition="request.isXmlHttpRequest()")
     */
    public function getByPage(Request                $request,
                              EntityManagerInterface $entityManager,
                              DisputeService         $disputeService): Response
    {
        if ($page = json_decode($request->getContent(), true)) {
            $filtreSupRepository = $entityManager->getRepository(FiltreSup::class);

            $filters = $filtreSupRepository->getFieldAndValueByPageAndUser($page, $this->getUser());
            if ($page === FiltreSup::PAGE_DISPUTE) {
                $translations = $disputeService->getLitigeOrigin();
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
