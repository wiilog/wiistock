<?php

namespace App\Controller;

use App\Entity\Alerte;
use App\Entity\Utilisateurs;
use App\Form\AlerteType;
use App\Repository\AlerteRepository;
use App\Repository\ReferencesArticlesRepository;
use App\Repository\UtilisateursRepository;
use App\Repository\ArticlesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/alerte")
 */
class AlerteController extends AbstractController
{
    /**
     * @Route("/", name="alerte_index", methods={"GET"})
     */
    public function index(AlerteRepository $alerteRepository, UtilisateursRepository $utilisateursRepository): Response
    {
        $alerteRepo = $alerteRepository->findAlerteByUser($this->getUser()->getId());
        $userRepo = $utilisateursRepository->find($this->getUser());

        foreach($alerteRepo as $alerte)
        {
            if($alerte->getAlerteRefArticle()->getQuantity() > $alerte->getAlerteSeuil())
            {
                /* En cours */
            }
        }

        return $this->render('alerte/index.html.twig', [
            'alertes' => $alerteRepo,
            'utilisateur' => $userRepo
        ]);
    }

    /**
     * @Route("/new", name="alerte_new", methods={"GET","POST"})
     */
    public function new(Request $request): Response
    {
        $alerte = new Alerte();
        
        $form = $this->createForm(AlerteType::class, $alerte);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            //Le numéro d'alerte est générer avec la date, l'heure, la minute et la seconde actuelle.
            $date = new \DateTime("now");
            $alerte->setAlerteNumero("A-" . $date->format('YmdHis'));

            //On détermine l'utilisateur
            $alerte->setAlerteUtilisateur($this->getUser());

            //Connexion bdd et envoi
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($alerte);
            $entityManager->flush();

            return $this->redirectToRoute('alerte_index');
        }

        return $this->render('alerte/new.html.twig', [
            'alerte' => $alerte,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="alerte_show", methods={"GET"})
     */
    public function show(Alerte $alerte): Response
    {
        return $this->render('alerte/show.html.twig', [
            'alerte' => $alerte,
        ]);
    }

    /**
     * @Route("/{id}/edit", name="alerte_edit", methods={"GET","POST"})
     */
    public function edit(Request $request, Alerte $alerte): Response
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
    public function delete(Request $request, Alerte $alerte): Response
    {
        if ($this->isCsrfTokenValid('delete'.$alerte->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($alerte);
            $entityManager->flush();
        }

        return $this->redirectToRoute('alerte_index');
    }

    /**
     * @Route("/{mail}", name="mailer")
     */

     
    /* public function index($name, \Swift_Mailer $mailer)
    { */
    /* $message = (new \Swift_Message('Hello Email'))
        ->setFrom('contact@wiilog.fr')
        ->setTo($utilisateurs)
        ->setBody(
            $this->renderView(
                templates/emails/registration.html.twig
                'alerte/registration.html.twig',
                ['utilisateurs' => $utilisateurs]
            ),
            'text/html'
        ) */
        /*
         * If you also want to include a plaintext version of the message
        ->addPart(
            $this->renderView(
                'emails/registration.txt.twig',
                ['name' => $name]
            ),
            'text/plain'
        )
        */
    /* ; */

    /* $mailer->send($message);

    return $this->render(...);
    } */
}
