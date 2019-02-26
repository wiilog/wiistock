<?php

namespace App\Controller;

use App\Entity\ChampsLibre;
use App\Entity\type;

use App\Form\ChampsLibreType;

use App\Repository\ChampsLibreRepository;
use App\Repository\TypeRepository;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @Route("/champs/libre")
 */
class ChampsLibreController extends AbstractController
{
     
    /**
     * @var ChampslibreRepository
     */
    private $champsLibreRepository;
    
    /**
     * @var TypeRepository
     */
    private $typeRepository;

    public function __construct(ChampsLibreRepository $champsLibreRepository, TypeRepository $typeRepository)
    {
        $this->champsLibreRepository = $champsLibreRepository;
        $this->typeRepository = $typeRepository;
    }

    /**
     * @Route("/", name="champs_libre_index", methods={"GET"})
     */
    public function index(): Response
    {
        return $this->render('champs_libre/index.html.twig', [
            'types' => $this->typeRepository->findAll(),
        ]);
    }

    /**
     * @Route("/typeApi", name="typeApi", options={"expose"=true}, methods={"POST"})
     */
    public function typeApi(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) //Si la requête est de type Xml
        {
            $types = $this->typeRepository->findAll();
            $rows = [];
            foreach ($types as $type) {
                // $url['edit'] = $this->generateUrl('article_edit', ['id' => $article->getId()] );
                $url['show'] = $this->generateUrl('champs_libre_new', ['id' => $type->getId()]);
                $rows[] =
                [
                    'id' => ($article->getId() ? $article->getId() : "Non défini"),
                    'Label' => ($article->getNom() ? $article->getNom() : "Non défini"),
                    'Actions' => $this->renderView('article/datatableArticleRow.html.twig', ['url' => $url]),
                ];
            }
            $data['data'] = $rows;
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/new/{id}", name="champs_libre_new", methods={"GET","POST"})
     */
    public function new(Request $request, $id): Response
    {
        return $this->render('champs_libre/new.html.twig', [
            'type' => $this->typeRepository->find($id),
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="champs_libre_show", methods={"GET"})
     */
    public function show(ChampsLibre $champsLibre): Response
    {
        return $this->render('champs_libre/show.html.twig', [
            'champs_libre' => $champsLibre,
        ]);
    }

    /**
     * @Route("/{id}/edit", name="champs_libre_edit", methods={"GET","POST"})
     */
    public function edit(Request $request, ChampsLibre $champsLibre): Response
    {
        $form = $this->createForm(ChampsLibreType::class, $champsLibre);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('champs_libre_index', [
                'id' => $champsLibre->getId(),
            ]);
        }

        return $this->render('champs_libre/edit.html.twig', [
            'champs_libre' => $champsLibre,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="champs_libre_delete", methods={"DELETE"})
     */
    public function delete(Request $request, ChampsLibre $champsLibre): Response
    {
        if ($this->isCsrfTokenValid('delete'.$champsLibre->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($champsLibre);
            $entityManager->flush();
        }

        return $this->redirectToRoute('champs_libre_index');
    }
}
