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
             
        if ($request->isXmlHttpRequest()) { //Si la requête est de type Xml
            
            
                // $user = $this->getUser()->getId();
                // $services = $this->serviceRepository->findByUser($user);
                $services = $this->serviceRepository->findAll();
        
            

            $rows = [];
                foreach ($services as $service) {
                $url['edit'] = $this->generateUrl('service_edit', ['id' => $service->getId()]);
               
                $rows[] = [
                    'id' => ($service->getId() ? $service->getId() : "Non défini"),
                    'Date'=> ($service->getDate() ? $service->getDate()->format('d/m/Y') : null),
                    'Demandeur'=> ($service->getDemandeur() ? $service->getDemandeur()->getUserName() : null),
                    'Libellé'=> ($service->getlibelle() ? $service->getLibelle() : null),
                    'Statut'=> ($service->getStatut()->getNom() ? ucfirst($service->getStatut()->getNom()) : null),
                    'Actions' => $this->renderView('service/datatableServiceRow.html.twig', [
                        'url' => $url,
                        'service' => $service,
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
     * @Route("/", name="service_index", methods={"GET", "POST"})
     */
    public function index(): Response
    {
        return $this->render('service/index.html.twig', [
            'emplacements' => $this->emplacementRepository->findAll(),
            'demandeurs' => $this->utilisateurRepository->findAll(),
            'statuts' => $this->statutRepository->findByCategorieName(Service::CATEGORIE),
        ]);
    }

    
    /**
     * @Route("/creation", name="creation_service", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function creationService(Request $request) : Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $em = $this->getDoctrine()->getEntityManager();
                      
            $status = $this->statutRepository->findOneByCategorieAndStatut(Service::CATEGORIE, Service::STATUT_A_TRAITER);
            $service = new Service();
            $date = new \DateTime('now');
            
            $service
                ->setDate($date)
                ->setLibelle($data['Libelle'])
                ->setEmplacement($this->emplacementRepository->find($data['Localité']))
                ->setStatut($status)
                ->setDemandeur($this->utilisateurRepository->find($data['demandeur']))
                ->setCommentaire($data['commentaire']);
           
            $em->persist($service);
            
            $em->flush();
            
            return new JsonResponse($data);
        }
        throw new XmlHttpException("404 not found");
    }
     
   
    /**
        * @Route("/editApi", name="service_edit_api", options={"expose"=true}, methods="GET|POST")
        */
    public function editApi(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $service = $this->serviceRepository->find($data);         
            $json = $this->renderView('service/modalEditServiceContent.html.twig', [
                'service' => $service,
                'utilisateurs'=>$this->utilisateurRepository->findAll(),
                'emplacements'=>$this->emplacementRepository->findAll(),
                'statut' => ($service->getStatut()->getNom() == Service::STATUT_A_TRAITER),
                'statuts'=>$this->statutRepository->findByCategorieName(Service::CATEGORIE),
            ]);
        
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/edit", name="service_edit", options={"expose"=true}, methods="GET|POST")
     */
    public function edit(Request $request) : Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $service = $this->serviceRepository->find($data['id']);
            dump($service);
            $statutLabel = ($data['statut'] == 1) ? Service::STATUT_TRAITE : Service::STATUT_A_TRAITER;
            $statut = $this->statutRepository->findOneByCategorieAndStatut(Service::CATEGORIE, $statutLabel);
            $service->setStatut($statut);
            $service
                ->setLibelle($data['Libelle'])
                ->setEmplacement($this->emplacementRepository->find($data['Localité']))
                // ->setStatut($this->statutRepository->find($data['statut']))
                ->setDemandeur($this->utilisateurRepository->find($data['demandeur']))
                ->setCommentaire($data['commentaire']);
            $em = $this->getDoctrine()->getManager();
            $em->flush();
            return new JsonResponse();
        }
        throw new NotFoundHttpException("404");
    }
}


