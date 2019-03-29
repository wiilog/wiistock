<?php

namespace App\Controller;

use App\Entity\Emplacement;
use App\Repository\EmplacementRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Repository\ArticleRepository;

/**
 * @Route("/emplacement")
 */
class EmplacementController extends AbstractController
{

    /**
     * @var EmplacementRepository
     */
    private $emplacementRepository;

    /**
     * @var ArticleRepository
     */
    private $articleRepository;

    public function __construct(ArticleRepository $articleRepository, EmplacementRepository $emplacementRepository)
    {
        $this->emplacementRepository = $emplacementRepository;
        $this->articleRepository = $articleRepository;
    }

    /**
     * @Route("/api", name="emplacement_api", options={"expose"=true}, methods="GET|POST")
     */
    public function emplacementApi(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) { //Si la requête est de type Xml
            $emplacements = $this->emplacementRepository->findAll();
            $rows = [];
            foreach ($emplacements as $emplacement) {
                $emplacementId = $emplacement->getId();
                $url['edit'] = $this->generateUrl('emplacement_edit', ['id' => $emplacementId]);
                $rows[] = [
                    'id' => $emplacement->getId(),
                    'Nom' => $emplacement->getLabel(),
                    'Description' => $emplacement->getDescription(),
                    'Actions' => $this->renderView('emplacement/datatableEmplacementRow.html.twig', [
                        'url' => $url,
                        'emplacementId' => $emplacementId
                    ]),
                ];
            }
            $data['data'] = $rows;
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/", name="emplacement_index", methods="GET")
     */
    public function index(Request $request): Response
    {
        return $this->render('emplacement/index.html.twig', ['emplacement' => $this->emplacementRepository->findAll()]);
    }

    /**
     * @Route("/creation/emplacement", name="creation_emplacement", options={"expose"=true}, methods="GET|POST")
     */
    public function creationEmplacement(Request $request): Response
    {
        $em = $this->getDoctrine()->getEntityManager();

        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $emplacement = new Emplacement();
            $emplacement->setLabel($data["Label"]);
            $emplacement->setDescription($data["Description"]);
            $em->persist($emplacement);
            $em->flush();
            return new JsonResponse($data);
        }

        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/show", name="emplacement_show", options={"expose"=true},  methods="GET|POST")
     */
    public function show(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $emplacement = $this->emplacementRepository->find($data);

            $json = $this->renderView('emplacement/modalShowEmplacementContent.html.twig', [
                'emplacement' => $emplacement,
            ]);
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/editApi", name="emplacement_edit_api", options={"expose"=true}, methods="GET|POST")
     */
    public function editApi(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $emplacement = $this->emplacementRepository->find($data);
            $json = $this->renderView('emplacement/modalEditEmplacementContent.html.twig', [
                'emplacement' => $emplacement,
            ]);

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/edit", name="emplacement_edit", options={"expose"=true}, methods="GET|POST")
     */
    public function edit(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $emplacement = $this->emplacementRepository->find($data['id']);
            $emplacement
                ->setLabel($data["Label"])
                ->setDescription($data["Description"]);
            $em = $this->getDoctrine()->getManager();
            $em->flush();
            return new JsonResponse();
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/supprimerEmplacement", name="emplacement_delete", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function deleteEmplacement(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $emplacement = $this->emplacementRepository->find($data['emplacement']);
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($emplacement);
            $entityManager->flush();
            return new JsonResponse();
        }
        throw new NotFoundHttpException("404");
    }
}
