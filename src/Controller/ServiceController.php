<?php

namespace App\Controller;

use App\Entity\Service;
use App\Repository\UtilisateurRepository;
use App\Repository\EmplacementRepository;
use App\Repository\ServiceRepository;
use App\Repository\StatutRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @Route("/service")
 */
class ServiceController extends AbstractController
{
   /**
     * @var ServiceRepository
     */
    private $serviceRepository;

   /**
     * @var EmplacementRepository
     */
    private $emplacementRepository;

    /**
     * @var StatutRepository
     */
    private $statutRepository;

    /**
    * @var UtilisateurRepository
    */
    private $utilisateurRepository;

    

    public function __construct(ServiceRepository $serviceRepository, EmplacementRepository $emplacementRepository, StatutRepository $statutRepository, UtilisateurRepository $utilisateurRepository)
    {
        $this->serviceRepository = $serviceRepository;
        $this->emplacementRepository = $emplacementRepository;
        $this->statutRepository = $statutRepository;
        $this->utilisateurRepository = $utilisateurRepository;
    }
   
 /**
     * @Route("/api", name="service_api", options={"expose"=true}, methods="GET|POST")
     */
    public function serviceApi(Request $request) : Response
    {
        if ($request->isXmlHttpRequest()) //Si la requête est de type Xml
        { 
            $services = $this->serviceRepository->findAll();
            // $emplacements = $this->emplacementRepository->findAll();
            $rows = [];
            
            foreach ($services as $service) {
                $url['edit'] = $this->generateUrl('service_edit', ['id' => $service->getId()]);
               
                $rows[] = [
                    'id' => ($service->getId() ? $service->getId() : "Non défini"),
                    'Date'=> ($service->getDate() ? $service->getDate()->format('d/m/Y') : null),
                    'Demandeur'=> ($service->getDemandeur() ? $service->getDemandeur()->getUserName() : null ),
                    'Libellé'=> ($service->getlibelle() ? $service->getLibelle() : null ),
                    'Statut'=> ($service->getStatut()->getNom() ? ucfirst($service->getStatut()->getNom()) : null),
                    'Actions' => $this->renderView('service/datatableServiceRow.html.twig', [
                        'url' => $url, 
                        'service' => $service,
                        // 'emplacements' => $emplacements,
                        'serviceId'=>$service->getId()
                        ])
                ];
            }
            $data['data'] = $rows;
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }



    /**
     * @Route("/", name="service_index", methods={"GET"})
     */
    public function index(ServiceRepository $serviceRepository, EmplacementRepository $emplacementRepository): Response
    {
        return $this->render('service/index.html.twig', [
            'services' => $serviceRepository->findAll(),
            'emplacements' => $emplacementRepository->findAll(),
        ]);
    }

    /**
     * @Route("/creation", name="creation_service", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function creationService(Request $request) : Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            dump($data);
            $em = $this->getDoctrine()->getEntityManager();
           
           
                       
            $service = new Service();
            $date = new \DateTime('now');
            $service
                ->setDate($date)
                ->setLibelle($data['Libelle'])
                ->setEmplacement($data['Localité'])
                // ->setStatut()
                ->setDemandeur($this->utilisateurRepository->find($data['demandeur']))
                ->setCommentaire($data['commentaire']);
           
            $em->persist($service);
            
            $em->flush();
            
            return new JsonResponse($data);
        }
        throw new XmlHttpException("404 not found");
    }
  





    /**
     * @Route("/new", name="service_new", methods={"GET","POST"})
     */
    public function new(Request $request): Response
    {
        $service = new Service();
        $form = $this->createForm(ServiceType::class, $service);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($service);
            $entityManager->flush();

            return $this->redirectToRoute('service_index');
        }

        return $this->render('service/new.html.twig', [
            'service' => $service,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="service_show", methods={"GET"})
     */
    public function show(Service $service): Response
    {
        return $this->render('service/show.html.twig', [
            'service' => $service,
        ]);
    }

    /**
     * @Route("/{id}/edit", name="service_edit", methods={"GET","POST"})
     */
    public function edit(Request $request, Service $service): Response
    {
        $form = $this->createForm(ServiceType::class, $service);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('service_index', [
                'id' => $service->getId(),
            ]);
        }

        return $this->render('service/edit.html.twig', [
            'service' => $service,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="service_delete", methods={"DELETE"})
     */
    public function delete(Request $request, Service $service): Response
    {
        if ($this->isCsrfTokenValid('delete'.$service->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($service);
            $entityManager->flush();
        }

        return $this->redirectToRoute('service_index');
    }
}
