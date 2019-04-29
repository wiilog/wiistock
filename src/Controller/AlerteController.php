<?php

namespace App\Controller;

use App\Entity\Alerte;
use App\Entity\Menu;
use App\Repository\AlerteRepository;
use App\Repository\UtilisateurRepository;
use App\Repository\ReferenceArticleRepository;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Service\SeuilAlerteService;

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

    /**
     * @var SeuilAlerteService
     */
    private $seuilAlerteService;

    /**
     * @var UserService
     */
    private $userService;


    public function __construct(SeuilAlerteService $seuilAlerteService, AlerteRepository $alerteRepository, UtilisateurRepository $utilisateurRepository, ReferenceArticleRepository $referenceArticleRepository, UserService $userService)
    {
        $this->alerteRepository = $alerteRepository;
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->utilisateurRepository = $utilisateurRepository;
        $this->seuilAlerteService = $seuilAlerteService;
        $this->userService = $userService;
    }

    /**
     * @Route("/api", name="alerte_api", options={"expose"=true}, methods="GET|POST")
     */
    public function alerteApi(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::PARAM)) {
                return $this->redirectToRoute('access_denied');
            }

            $alertes = $this->alerteRepository->findAll();
            $seuilAtteint = $this->seuilAlerteService->thresholdReaches();
            $rows = [];

            foreach ($alertes as $alerte) {
                $rows[] = [
                    'id' => $alerte->getId(),
                    'Code' => $alerte->getAlerteNumero(),
                    'Seuil limite' => $alerte->getAlerteSeuil(),
                    'Seuil' => ($alerte->getSeuilAtteint() ? "<i class='fas fa-exclamation' style='color:red'></i>" : "<i class='fas fa-check' style='color:green'></i>"),
                    'Article Référence' => $alerte->getAlerteRefArticle()->getLibelle(),
                    'Quantité en stock' => $alerte->getAlerteRefArticle()->getQuantiteStock(),
                    'Utilisateur' => $alerte->getAlerteUtilisateur()->getUsername(),
                    'Actions' => $this->renderView('alerte/datatableAlerteRow.html.twig', [
                        'alerteId' => $alerte->getId(),
                    ]),
                ];
            }
            $data['data'] = $rows;

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/", name="alerte_index", methods="GET")
     */
    public function index(): Response
    {
        if (!$this->userService->hasRightFunction(Menu::PARAM)) {
            return $this->redirectToRoute('access_denied');
        }

        return $this->render('alerte/index.html.twig');
    }

    /**
     * @Route("/creer", name="alerte_new", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function new(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM)) {
                return $this->redirectToRoute('access_denied');
            }

            $em = $this->getDoctrine()->getEntityManager();

            $refArticle = $this->referenceArticleRepository->find($data['AlerteArticleReference']);

            $alerte = new Alerte();
            $date = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
            $alerte
                ->setAlerteNumero('P-' . $date->format('YmdHis'))
                ->setAlerteSeuil($data['AlerteSeuil'])
                ->setAlerteUtilisateur($this->utilisateurRepository->find($data['utilisateur']))
                ->setAlerteRefArticle($refArticle);

            $em->persist($alerte);
            $em->flush();

            return new JsonResponse($data);
        }
        throw new XmlHttpException('404 not found');
    }

    /**
     * @Route("/api-modifier", name="alerte_api_edit", options={"expose"=true}, methods="GET|POST")
     */
    public function editApi(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM)) {
                return $this->redirectToRoute('access_denied');
            }

            $alerte = $this->alerteRepository->find($data['id']);
            $json = $this->renderView('alerte/modalEditAlerteContent.html.twig', [
                'alerte' => $alerte,
            ]);

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/modifier", name="alerte_edit", options={"expose"=true}, methods="GET|POST")
     */
    public function edit(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM)) {
                return $this->redirectToRoute('access_denied');
            }

            $alerte = $this->alerteRepository->find($data['id']);
            $alerte
                ->setAlerteSeuil($data["seuil"]);
            $em = $this->getDoctrine()->getManager();
            $em->flush();

            return new JsonResponse();
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/supprimer", name="alerte_delete", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function delete(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM)) {
                return $this->redirectToRoute('access_denied');
            }

            $alerte = $this->alerteRepository->find($data['alerte']);
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($alerte);
            $entityManager->flush();

            return new JsonResponse();
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/verifier", name="check")
     */
    public function check()
    {
        if (!$this->userService->hasRightFunction(Menu::PARAM)) {
            return $this->redirectToRoute('access_denied');
        }

        $this->seuilAlerteService->warnUsers();

        return $this->redirectToRoute('alerte_index');
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
