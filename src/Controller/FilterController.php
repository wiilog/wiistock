<?php

namespace App\Controller;

use App\Entity\Filter;
use App\Repository\ChampsLibreRepository;
use App\Repository\FilterRepository;
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
     * FilterController constructor.
     * @param ChampsLibreRepository $champsLibreRepository
     * @param FilterRepository $filterRepository
     */
    public function __construct(ChampsLibreRepository $champsLibreRepository, FilterRepository $filterRepository)
    {
        $this->champsLibreRepository = $champsLibreRepository;
        $this->filterRepository = $filterRepository;
    }

    /**
     * @Route("/creer", name="filter_new", options={"expose"=true})
     */
    public function new(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $em = $this->getDoctrine()->getManager();

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

            $filters = $this->filterRepository->findBy(['utilisateur' => $user]);

            return new JsonResponse($filters);
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

            return new JsonResponse();
        }
        throw new NotFoundHttpException("404");
    }
}
