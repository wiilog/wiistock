<?php

namespace App\Controller;

use App\Entity\ReferenceArticle;
use App\Repository\TypeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class TypeController
 * @package App\Controller
 * @Route("/type")
 */
class TypeController extends AbstractController
{
    /**
     * @var TypeRepository
     */
    private $typeRepository;

    /**
     * TypeController constructor.
     * @param TypeRepository $typeRepository
     */
    public function __construct(TypeRepository $typeRepository)
    {
        $this->typeRepository = $typeRepository;
    }

    /**
     * @Route("/", name="type_show_select", options={"expose"=true})
     */
    public function showSelectInput(Request $request)
    {
        if ($request->isXmlHttpRequest()) {

            $types = $this->typeRepository->getByCategoryLabel(ReferenceArticle::CATEGORIE);

            $view = $this->renderView('type/inputSelectTypes.html.twig', [
                'types' => $types
            ]);
            return new JsonResponse($view);
        }
        throw new NotFoundHttpException("404");
    }
}
