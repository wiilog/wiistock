<?php

namespace App\Controller;

use App\Entity\Emplacement;
use App\Form\EmplacementType;
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
     * @Route("/nouvelEmplacement", name="createEmplacement", options={"expose"=true})
     */
    public function createEmplacement(Request $request) : Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (count($data) >= 2) {
                $emplacement = new Emplacement();
                $em = $this->getDoctrine()->getManager();

                $emplacement->setNom($data[0]['nom'])
                            ->setDescription()($data[0]['description']);
                $em->persist($emplacement);
                $em->flush();
                $data = json_encode($data);
                return new JsonResponse($data);
            }
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/", name="emplacement_index", methods="GET")
     */
    public function index() : Response
    {
        return $this->render('emplacement/index.html.twig');
    }

    /**
     * @Route("/creer", name="emplacement_new", methods="GET|POST")
     */
    public function new(Request $request) : Response
    {
        $emplacement = new Emplacement();
        $form = $this->createForm(EmplacementType::class, $emplacement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($emplacement);
            $em->flush();

            return $this->redirectToRoute('emplacement_index');
        }

        return $this->render('emplacement/new.html.twig', [
            'emplacement' => $emplacement,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/api", name="emplacement_api", options={"expose"=true}, methods="GET|POST")
     */
    public function fournisseurApi(Request $request) : Response
    {
        if ($request->isXmlHttpRequest()) //Si la requête est de type Xml
        {
            $emplacements = $this->emplacementRepository->findAll();
            $rows = [];
            foreach ($emplacements as $emplacement) {
                $emplacementId = $emplacement->getId();
                $url['edit'] = $this->generateUrl('emplacement_edit', ['id' => $emplacementId]);
                $url['show'] = $this->generateUrl('emplacement_show', ['id' => $emplacementId]);
                $rows[] = [
                    'id' => ($emplacement->getId() ? $emplacement->getId() : ""),
                    'Nom' => ($emplacement->getNom() ? $emplacement->getNom() : ""),
                    'Description' => ($emplacement->getDescription() ? $emplacement->getDescription() : ""),
                    'Actions' => $this->renderView('emplacement/datatableEmplacementRow.html.twig', ['url' => $url])
                ];
            }
            $data['data'] = $rows;
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/{id}", name="emplacement_show", methods="GET")
     */
    public function show(Emplacement $emplacement) : Response
    {
        return $this->render('emplacement/show.html.twig', ['emplacement' => $emplacement]);
    }

    /**
     * @Route("/{id}/edit", name="emplacement_edit", methods="GET|POST")
     */
    public function edit(Request $request, Emplacement $emplacement) : Response
    {
        $form = $this->createForm(EmplacementType::class, $emplacement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('emplacement_edit', ['id' => $emplacement->getId()]);
        }

        return $this->render('emplacement/edit.html.twig', [
            'emplacement' => $emplacement,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="emplacement_delete", methods="DELETE")
     */
    public function delete(Request $request, Emplacement $emplacement)  : Response
    {
        $emplacements = $this->articleRepository->findAll();
        if (count($emplacements) === 0) {
            if ($this->isCsrfTokenValid('delete' . $emplacement->getId(), $request->request->get('_token'))) {
                $em = $this->getDoctrine()->getManager();
                $em->remove($emplacement);
                $em->flush();
            }
            return $this->redirectToRoute('emplacement_index');
        } else {
            return $this->redirect($_SERVER['HTTP_REFERER']);
        }
    }
}
