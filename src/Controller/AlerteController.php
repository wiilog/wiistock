<?php

namespace App\Controller;

use App\Entity\Alerte;
use App\Form\AlerteType;
use App\Repository\AlerteRepository;
use App\Repository\ArticleRepository;
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

    public function __construct(AlerteRepository $alerteRepository, ReferenceArticleRepository $referenceArticleRepository)
    {
        $this->alerteRepository = $alerteRepository;
        $this->referenceArticleRepository = $referenceArticleRepository;
    }

    
    /**
     * @Route("/create", name="createAlerte", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function createAlerte(Request $request) : Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $alerte = new Alerte();
            $em = $this->getDoctrine()->getEntityManager();
            $refArticle = $this->referenceArticleRepository->find($data["reference"]);
            $date = new \DateTime('now');
            $alerte
                ->setAlerteNumero('P-' . $date->format('YmdHis'))
                ->setAlerteNom($data["nom"])
                ->setAlerteRefArticle($refArticle)
                ->setAlerteSeuil($data["seuil"]);
            $em->persist($alerte);
            $em->flush();
            $data = json_encode($data);
            return new JsonResponse($data);
        }
        throw new XmlHttpException("404 not found");
    }



    /**
     * @Route("/", name="alerte_index", methods={"GET"})
     */
    public function index(Request $request) : Response
    {
        return $this->render('alerte/index.html.twig', ["references" => $this->referenceArticleRepository->findAll()]);
    }



    /**
     * @Route("/api", name="alerte_api", options={"expose"=true}, methods={"GET"})
     */
    public function alerteApi(\Swift_Mailer $mailer, Request $request) : Response
    {
        if ($request->isXmlHttpRequest()) //Si la requête est de type Xml
        {
            $alertes = $this->alerteRepository->findAll();

            /* Un tableau d'alertes qui sera envoyer au mailer */
            $alertesUser = []; 
            $rows = [];

            foreach ($alertes as $alerte) {
                
                $condition = $alerte->getAlerteRefArticle()->getQuantiteStock() > $alerte->getAlerteSeuil();

                /* Si le seuil est inférieur à la quantité, seuil atteint = false sinon true */
                $seuil = ($condition) ? false : true; 
                $alerte->setSeuilAtteint($seuil);

                if ($seuil) {
                    array_push($alertesUser, $alerte); /* Si seuil atteint est "true" alors on insère l'alerte dans le tableau */
                }

                $this->getDoctrine()->getManager()->flush();
                $url['edit'] = $this->generateUrl('alerte_edit', ['id' => $alerte->getId()] );
                $url['show'] = $this->generateUrl('alerte_show', ['id' => $alerte->getId()] );
                $rows[] = [
                    "id" => $alerte->getId(),
                    "Nom" => $alerte->getAlerteNom(),
                    "Code" => $alerte->getAlerteNumero(),
                    "Seuil" => ($condition ? "<p><i class='fas fa-check-circle fa-2x green'></i>" . $alerte->getAlerteRefArticle()->getQuantiteStock() . "/" . $alerte->getAlerteSeuil() . "</p>" :
                        "<p><i class='fas fa-exclamation-circle fa-2x red'></i>" . $alerte->getAlerteRefArticle()->getQuantiteStock() . "/" . $alerte->getAlerteSeuil() . " </p>"),
                    'Actions' => $this->renderView('alerte/datatableAlerteRow.html.twig', ['url' => $url, 'alerte' => $alerte]),
                ];
            }

            if (count($alertesUser) > 0) {
                $this->mailer($alertesUser, $mailer); /* On envoie le tableau d'alertes au mailer */
            }

            $data['data'] = $rows;
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
     
    }


    /**
     * @Route("/{id}", name="alerte_show", methods={"GET"})
     */
    public function show(Alerte $alerte) : Response
    {
        return $this->render('alerte/show.html.twig', [
            'alerte' => $alerte,
        ]);
    }



    /**
     * @Route("/{id}/edit", name="alerte_edit", methods={"GET","POST"})
     */
    public function edit(Request $request, Alerte $alerte) : Response
    {
        $form = $this->createForm(AlerteType::class, $alerte);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('alerte_index', [
                'id' => $alerte->getId(),
            ]);
        }

        return $this->render('alerte/edit.html.twig', [
            'alerte' => $alerte,
            'form' => $form->createView(),
        ]);
    }



    /**
     * @Route("/{id}", name="alerte_delete", methods={"DELETE"})
     */
    public function delete(Request $request, Alerte $alerte) : Response
    {
        if ($this->isCsrfTokenValid('delete' . $alerte->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($alerte);
            $entityManager->flush();
        }

        return $this->redirectToRoute('alerte_index');
    }


    
    /* Mailer */
    public function mailer($alertes, \Swift_Mailer $mailer)
    {
        $message = (new \Swift_Message('Alerte Email'))
            ->setFrom('contact@wiilog.com')
            ->setTo($this->getUser()->getEmail())
            ->setBody(
                $this->renderView(
                // templates/mailer/index.html.twig
                    'mailer/index.html.twig',
                    ['alertes' => $alertes]
                ),
                'text/html'
            )
        /*
         * If you also want to include a plaintext version of the message
        ->addPart(
            $this->renderView(
                'emails/registration.txt.twig',
                ['name' => $name]
            ),
            'text/plain'
        )
         */;

        $mailer->send($message);

        return $this->render('mailer/index.html.twig', [
            'alertes' => $alertes
        ]);
    }
}
