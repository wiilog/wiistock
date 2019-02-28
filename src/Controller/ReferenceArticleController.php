<?php

namespace App\Controller;

use App\Entity\ReferenceArticle;
use App\Form\ReferenceArticleType;

use App\Repository\ReferenceArticleRepository;
use App\Repository\ChampsLibreRepository;
use App\Repository\TypeRepository;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Entity\ChampPersonnalise;

use App\Service\FileUploader;

/**
 * @Route("/reference_article")
 */
class ReferenceArticleController extends Controller
{

     /**
     * @var ReferenceArticleRepository
     */
    private $referenceArticleRepository;
     
    /**
     * @var TypeRepository
     */
    private $typeRepository;
    
    /**
     * @var ChampslibreRepository
     */
    private $champsLibreRepository;

    public function __construct(ReferenceArticleRepository $referenceArticleRepository, Typerepository  $typeRepository, ChampsLibreRepository $champsLibreRepository)
    {
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->champsLibreRepository = $champsLibreRepository;
        $this->typeRepository = $typeRepository;
    }


    /**
     * @Route("/refArticleAPI", name="ref_article_api", options={"expose"=true}, methods="GET|POST")
     */
    public function refArticleApi(Request $request) : Response
    {
        if ($request->isXmlHttpRequest()) //Si la requête est de type Xml
        {
            $refs = $this->referenceArticleRepository->findAll();
            $rows = [];
            foreach ($refs as $refArticle) {
                $url['edit'] = $this->generateUrl('reference_article_edit', ['id' => $refArticle->getId()] );
                $url['show'] = $this->generateUrl('reference_article_show', ['id' => $refArticle->getId()] );
               
                $rows[] = [
                    "id" => $refArticle->getId(),
                    "Libellé" => $refArticle->getLibelle(),
                    "Référence" => $refArticle->getReference(),
                    'Actions' => $this->renderView('reference_article/datatableReferenceArticleRow.html.twig', [
                        'url' => $url,
                        'idRefArticle'=> $refArticle->getId()
                        ]),
                ];
            }
            $data['data'] = $rows;
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/nouveau", name="reference_article_new", options={"expose"=true}, methods="GET|POST")
     */
    public function new(Request $request) : Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
           dump($data);
            $refArticle = new ReferenceArticle();
            $refArticle
                ->setLibelle($data['libelle'])
                ->setReference($data['reference']);
            $em = $this->getDoctrine()->getManager();
            $em->persist($refArticle);
            $em->flush();
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/", name="reference_article_index", methods="GET")
     */
    public function index(Request $request) : Response
    {
        return $this->render('reference_article/index.html.twig', [
            'types'=> $this->typeRepository->findAll(),
            'champsLibres'=> $this->champsLibreRepository->findAll()
        ]);
    }

    /**
     * @Route("/{id}", name="reference_article_show", methods="GET")
     */
    public function show(ReferenceArticle $referenceArticle) : Response
    {
        return $this->render('reference_article/show.html.twig', ['reference_article' => $referenceArticle]);
    }

    /**
     * @Route("/{id}/edit", name="reference_article_edit", methods="GET|POST")
     */
    public function edit(Request $request, ReferenceArticle $referenceArticle) : Response
    {
        $form = $this->createForm(ReferenceArticleType::class, $referenceArticle);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('reference_article_index');
        }

        return $this->render('reference_article/edit.html.twig', [
            'reference_article' => $referenceArticle,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/supprimerRefArticle", name="reference_article_delete", options={"expose"=true}, methods="GET|POST")
     */
    public function delete(Request $request) : Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {          
            $refArticle = $this->referenceArticleRepository->find($data['refArticle']);
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($refArticle);
            $entityManager->flush();
            return new JsonResponse();
        }
        throw new NotFoundHttpException("404");
    }

}
