<?php

namespace App\Controller;

use App\Entity\Filter;
use App\Repository\ChampsLibreRepository;
use App\Repository\FilterRepository;
use App\Service\RefArticleDataService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class FilterController
 * @package App\Controller
 * @Route("/filter")
 */
class FilterController extends AbstractController
{
    /**
     * @var ChampsLibreRepository
     */
    private $champsLibreRepository;

    /**
     * @var FilterRepository
     */
    private $filterRepository;

    /**
     * @var RefArticleDataService
     */
    private $refArticleDataService;

    /**
     * FilterController constructor.
     * @param ChampsLibreRepository $champsLibreRepository
     * @param FilterRepository $filterRepository
     * @param RefArticleDataService $refArticleDataService
     */
    public function __construct(ChampsLibreRepository $champsLibreRepository, FilterRepository $filterRepository, RefArticleDataService $refArticleDataService)
    {
        $this->champsLibreRepository = $champsLibreRepository;
        $this->filterRepository = $filterRepository;
        $this->refArticleDataService = $refArticleDataService;
    }

    /**
     * @Route("/creer", name="filter_new", options={"expose"=true})
     */
    public function new(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $em = $this->getDoctrine()->getManager();

            // on vérifie qu'il n'existe pas déjà un filtre sur le même champ
            $userId = $this->getUser()->getId();
            $existingFilter = $this->filterRepository->countByChampAndUser($data['field'], $userId);

            if($existingFilter == 0) {
                $filter = new Filter();

                // champ Champ Libre
                if (isset($data['field'])) {
                    $field = $data['field'];

                    if (intval($field) != 0) {
                        $champLibre = $this->champsLibreRepository->find(intval($field));
                        $filter->setChampLibre($champLibre);
                    } else {
                        $filter->setChampFixe($data['field']);
                    }
                } else {
                    return new JsonResponse(false); //TODO gérer retour erreur (champ obligatoire)
                }

                // champ Value
                if (isset($data['value'])) {
                    $filter->setValue($data['value']);
                }

                // champ Utilisateur
                $user = $this->getUser();
                $filter->setUtilisateur($user);

                $em->persist($filter);
                $em->flush();

                $filterArray = [
                    'id' => $filter->getId(),
                    'champLibre' => $filter->getChampLibre(),
                    'champFixe' => $filter->getChampFixe(),
                    'value' => $filter->getValue()
                ];

                $result = [
                    'reload' => $this->refArticleDataService->getRefArticleDataByParams($request->request),
                    'filterHtml' => $this->renderView('reference_article/oneFilter.html.twig', ['filter' => $filterArray])
                ];
            } else {
                $result = false; //TODO gérer retour erreur (filtre déjà existant)
            }
            return new JsonResponse($result);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/supprimer", name="filter_delete", options={"expose"=true})
     */
    public function delete(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $filterId = $data['filterId'];

            if ($filterId) {
                $filter = $this->filterRepository->find($filterId);
                $em = $this->getDoctrine()->getManager();
                $em->remove($filter);
                $em->flush();
            }

            $data = $this->refArticleDataService->getRefArticleDataByParams();

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }
}
