<?php

namespace App\Controller;

use App\Entity\Alerte;
use App\Repository\AlerteRepository;
use App\Repository\UtilisateurRepository;
use App\Repository\ReferenceArticleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @Route("/alerte")
 */
class AlerteController extends AbstractController
{
    /**
     * @var AlerteRepository
     */
    private $alerteRepository;

    /**
     * @var ReferenceArticleRepository
     */
    private $referenceArticleRepository;
    /**
    * @var UtilisateurRepository
    */
    private $utilisateurRepository;

    public function __construct(AlerteRepository $alerteRepository, UtilisateurRepository $utilisateurRepository, ReferenceArticleRepository $referenceArticleRepository)
    {
        $this->alerteRepository = $alerteRepository;
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->utilisateurRepository = $utilisateurRepository;
    }
  

    /**
     * @Route("/api", name="alerte_api", options={"expose"=true}, methods="GET|POST")
     */
    public function alerteApi(Request $request) : Response
    {
        if ($request->isXmlHttpRequest()) { //Si la requête est de type Xml
            $alertes = $this->alerteRepository->findAll();
            $rows = [];
            
            foreach ($alertes as $alerte) {
                $url['edit'] = $this->generateUrl('alerte_edit', ['id' => $alerte->getId()]);
                $url['show'] = $this->generateUrl('alerte_show', ['id' => $alerte->getId()]);
                $rows[] = [
                    'id' => $alerte->getId(),
                    'Nom' => $alerte ->getAlerteNom(),
                    'Code' => $alerte->getAlerteNumero(),
                    'Seuil' => $alerte->getAlerteSeuil(),
                    'Article Référence' => $alerte->getAlerteRefArticle()->getLibelle(),
                    'Utilisateur' => $alerte->getAlerteUtilisateur()->getUsername(),
                    'Actions' => $this->renderView('alerte/datatableAlerteRow.html.twig', [
                        'url' => $url,
                        'alerteId'=>$alerte->getId(),
                        ]),
                ];
            }
            $data['data'] = $rows;
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/api", name="alerte_api", options={"expose"=true}, methods={"GET"})
     */
    // public function alerteApi(\Swift_Mailer $mailer, Request $request) : Response
    // {
    //     if ($request->isXmlHttpRequest()) //Si la requête est de type Xml
    //     {
    //         $alertes = $this->alerteRepository->findAll();

    //         /* Un tableau d'alertes qui sera envoyer au mailer */
    //         $alertesUser = [];
    //         $rows = [];

    //         foreach ($alertes as $alerte) {
                
    //             $condition = $alerte->getAlerteRefArticle()->getQuantiteStock() > $alerte->getAlerteSeuil();

    //             /* Si le seuil est inférieur à la quantité, seuil atteint = false sinon true */
    //             $seuil = ($condition) ? false : true;
    //             $alerte->setSeuilAtteint($seuil);

    //             if ($seuil) {
    //                 array_push($alertesUser, $alerte); /* Si seuil atteint est "true" alors on insère l'alerte dans le tableau */
    //             }

    //             $this->getDoctrine()->getManager()->flush();
    //             $url['edit'] = $this->generateUrl('alerte_edit', ['id' => $alerte->getId()] );
    //             $url['show'] = $this->generateUrl('alerte_show', ['id' => $alerte->getId()] );
    //             $rows[] = [
    //                 "id" => $alerte->getId(),
    //                 "Nom" => $alerte->getAlerteNom(),
    //                 "Code" => $alerte->getAlerteNumero(),
    //                 "Seuil" => ($condition ? "<p><i class='fas fa-check-circle fa-2x green'></i>" . $alerte->getAlerteRefArticle()->getQuantiteStock() . "/" . $alerte->getAlerteSeuil() . "</p>" :
    //                     "<p><i class='fas fa-exclamation-circle fa-2x red'></i>" . $alerte->getAlerteRefArticle()->getQuantiteStock() . "/" . $alerte->getAlerteSeuil() . " </p>"),
    //                 'Actions' => $this->renderView('alerte/datatableAlerteRow.html.twig', ['url' => $url, 'alerte' => $alerte]),
    //             ];
    //         }

    //         if (count($alertesUser) > 0) {
    //             $this->mailer($alertesUser, $mailer); /* On envoie le tableau d'alertes au mailer */
    //         }

    //         $data['data'] = $rows;
    //         return new JsonResponse($data);
    //     }
    //     throw new NotFoundHttpException("404");
     
    // }


    /**
     * @Route("/", name="alerte_index", methods="GET")
     */
    public function index(Request $request) : Response
    {
        return $this->render('alerte/index.html.twig', [
            "references" => $this->referenceArticleRepository->findAll(),
            'alerte' => $this->alerteRepository->findAll(),
            'utilisateurs'=>$this->utilisateurRepository->findAll(),
        ]);
    }

    /**
     * @Route("/creation/alerte", name="creation_alerte", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function creationAlerte(Request $request) : Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $em = $this->getDoctrine()->getEntityManager();
           
            $refArticle = $this->referenceArticleRepository->find($data["AlerteArticleReference"]);
            
            $alerte = new Alerte();
            $date = new \DateTime('now');
            $alerte
                ->setAlerteNumero('P-' . $date->format('YmdHis'))
                ->setAlerteNom($data['AlerteNom'])
                ->setAlerteSeuil($data['AlerteSeuil'])
                ->setAlerteUtilisateur($this->utilisateurRepository->find($data['utilisateur']))
                ->setAlerteRefArticle($refArticle);
           
            $em->persist($alerte);
            $em->flush();
            
            return new JsonResponse($data);
        }
        throw new XmlHttpException("404 not found");
    }
  

    /**
     * @Route("/show", name="alerte_show", options={"expose"=true},  methods="GET|POST")
     */
    public function show(Request $request) : Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $alerte = $this->alerteRepository->find($data);
            // $utilisateur = $this->UtilisateurRepository->getByUsername()->getId();
            $json =$this->renderView('alerte/modalShowAlerteContent.html.twig', [
                'alerte' => $alerte
                // 'utilisateur' => $utilisateur
                ]);
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/editApi", name="alerte_edit_api", options={"expose"=true}, methods="GET|POST")
     */
    public function editApi(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $alerte = $this->alerteRepository->find($data);
            $json = $this->renderView('alerte/modalEditAlerteContent.html.twig', [
                'alerte' => $alerte,
                "references" => $this->referenceArticleRepository->findAll(),
                'utilisateurs'=>$this->utilisateurRepository->findAll(),
            ]);
        
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/edit", name="alerte_edit", options={"expose"=true}, methods="GET|POST")
     */
    public function edit(Request $request) : Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $alerte = $this->alerteRepository->find($data['id']);
            $alerte
                ->setAlerteNom($data["Nom"])
                ->setAlerteSeuil($data["Seuil"]);            
            $em = $this->getDoctrine()->getManager();
            $em->flush();
            return new JsonResponse();
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/supprimerAlerte", name="alerte_delete", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function deleteAlerte(Request $request)  : Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $alerte= $this->alerteRepository->find($data['alerte']);
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($alerte);
            $entityManager->flush();
            return new JsonResponse();
        }
        throw new NotFoundHttpException("404");
    }

    
    // /* Mailer */
    // public function mailer($alertes, \Swift_Mailer $mailer)
    // {
    //     $message = (new \Swift_Message('Alerte Email'))
    //         ->setFrom('contact@wiilog.com')
    //         ->setTo($this->getUser()->getEmail())
    //         ->setBody(
    //             $this->renderView(
    //             // templates/mailer/index.html.twig
    //                 'mailer/index.html.twig',
    //                 ['alertes' => $alertes]
    //             ),
    //             'text/html'
    //         )
    //     /*
    //      * If you also want to include a plaintext version of the message
    //     ->addPart(
    //         $this->renderView(
    //             'emails/registration.txt.twig',
    //             ['name' => $name]
    //         ),
    //         'text/plain'
    //     )
    //      */;

    //     $mailer->send($message);

    //     return $this->render('mailer/index.html.twig', [
    //         'alertes' => $alertes
    //     ]);
    // }
}
