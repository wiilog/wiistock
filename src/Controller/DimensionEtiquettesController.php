<?php

namespace App\Controller;

use App\Entity\Menu;

use App\Service\UserService;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;

use App\Repository\DimensionsEtiquettesRepository;
use App\Entity\DimensionsEtiquettes;

/**
 * @Route("/etiquettes")
*/
class DimensionEtiquettesController extends AbstractController
{
    /**
     * @var DimensionsEtiquettesRepository 
     */
    private $dimensionsEtiquettesRepository;

    /**
     * @var UserService
     */
    private $userService;

    public function __construct(DimensionsEtiquettesRepository $dimensionsEtiquettesRepository, UserService $userService)
    {
        $this->dimensionsEtiquettesRepository = $dimensionsEtiquettesRepository;
        $this->userService = $userService;
    }

    /**
     * @Route("/parametrage", name="etiquettes_param")
     */
    public function index(): response
    {
        if (!$this->userService->hasRightFunction(Menu::PARAM)) {
            return $this->redirectToRoute('access_denied');
        }

        $dimensions =  $this->dimensionsEtiquettesRepository->findOneDimension();
        return $this->render('dimensions_etiquettes/index.html.twig', [
            'dimensions_etiquettes' => $dimensions
        ]);
    }

    /**
     * @Route("/ajax-etiquettes", name="ajax_dimensions_etiquettes",  options={"expose"=true},  methods="GET|POST")
     */
    public function ajaxMailerServer(Request $request): response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM)) {
                return $this->redirectToRoute('access_denied');
            }
            $em = $this->getDoctrine()->getEntityManager();
            $dimensions =  $this->dimensionsEtiquettesRepository->findOneDimension();
            if (!$dimensions) {
                $dimensions = new DimensionsEtiquettes();
                $em->persist($dimensions);
            }
            $dimensions
                ->setHeight(intval($data['height']))
                ->setWidth(intval($data['width']));
            $em->flush();

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }
}
