<?php

namespace App\Controller;

use App\Entity\Acheminements;
use App\Entity\Action;
use App\Entity\Arrivage;
use App\Entity\CategorieStatut;
use App\Entity\Menu;
use App\Entity\Utilisateur;

use App\Repository\AcheminementsRepository;
use App\Repository\StatutRepository;
use App\Repository\UtilisateurRepository;
use App\Repository\DimensionsEtiquettesRepository;

use App\Service\SpecificService;
use App\Service\UserService;
use App\Service\MailerService;
use App\Service\AcheminementsService;

use Doctrine\ORM\NonUniqueResultException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/acheminements")
 */
Class AcheminementsController extends AbstractController
{
    /**
     * @var StatutRepository
     */
    private $statutRepository;

    /**
     * @var UserService
     */
    private $userService;

    /**
     * @var UtilisateurRepository
     */
    private $utilisateurRepository;

    /**
     * @var AcheminementsRepository
     */
    private $acheminementsRepository;

    /**
     * @var AcheminementsService
     */
    private $acheminementsService;

    /**
     * @var DimensionsEtiquettesRepository
     */
    private $dimensionsEtiquettesRepository;

    public function __construct(AcheminementsRepository $acheminementsRepository, UserService $userService, UtilisateurRepository $utilisateurRepository, AcheminementsService $acheminementsService, StatutRepository $statutRepository, DimensionsEtiquettesRepository $dimensionsEtiquettesRepository)
    {
        $this->userService = $userService;
        $this->utilisateurRepository = $utilisateurRepository;
        $this->acheminementsRepository = $acheminementsRepository;
        $this->acheminementsService = $acheminementsService;
        $this->statutRepository = $statutRepository;
        $this->dimensionsEtiquettesRepository = $dimensionsEtiquettesRepository;
    }


    /**
     * @Route("/", name="acheminements_index")
     */
    public function index()
    {
        if (!$this->userService->hasRightFunction(Menu::ARRIVAGE, Action::LIST)) {
            return $this->redirectToRoute('access_denied');
        }

        return $this->render('acheminements/index.html.twig', [
            'utilisateurs' => $this->utilisateurRepository->findAll(),
            'statuts' => [
                ['nom' => Acheminements::STATUT_A_TRAITER],
                ['nom' => Acheminements::STATUT_TRAITE]
            ]
        ]);
    }

    /**
     * @Route("/api", name="acheminements_api", options={"expose"=true}, methods="GET|POST")
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {

            if (!$this->userService->hasRightFunction(Menu::ARRIVAGE, Action::LIST)) {
                return $this->redirectToRoute('access_denied');
            }
            $data = $this->acheminementsService->getDataForDatatable($request->request);

            return new JsonResponse($data);
        } else {
            throw new NotFoundHttpException('404');
        }
    }

    /**
     * @Route("/creer", name="acheminements_new", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function new(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $status = $this->statutRepository->findOneByCategorieNameAndStatutName(CategorieStatut::ACHEMINEMENT, Acheminements::STATUT_A_TRAITER);
            $acheminements = new Acheminements();
            $date = new \DateTime('now', new \DateTimeZone('Europe/Paris'));

            $acheminements
                ->setDate($date)
                ->setDate($date)
                ->setStatut($status)
                ->setRequester($this->utilisateurRepository->find($data['demandeur']))
                ->setReceiver($this->utilisateurRepository->find($data['destinataire']))
                ->setLocationDrop($data['depose'])
                ->setLocationTake($data['prise'])
                ->setColis($data['colis']);

            $em = $this->getDoctrine()->getEntityManager();
            $em->persist($acheminements);

            $em->flush();

            $dimension = $this->dimensionsEtiquettesRepository->findOneDimension();
            if ($dimension && !empty($dimension->getHeight()) && !empty($dimension->getWidth())) {
                $response['height'] = $dimension->getHeight();
                $response['width'] = $dimension->getWidth();
                $response['exists'] = true;
            } else {
                $response['exists'] = false;
            }

            $response['codes'] = $data['colis'];
            $response['acheminements'] = $acheminements->getId();
            return new JsonResponse($response);
        }
        throw new XmlHttpException('404 not found');
    }

    /**
     * @Route("/api-new", name="acheminements_new_api", options={"expose"=true}, methods="GET|POST")
     */
    public function newApi(): Response
    {
            $json = $this->renderView('acheminements/modalNewContentAcheminements.html.twig', [
                'utilisateurs' => $this->utilisateurRepository->findAll(),
            ]);

            return new JsonResponse($json);
    }

    /**
     * @Route("/modifier", name="acheminement_edit", options={"expose"=true}, methods="GET|POST")
     */
    public function edit(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $acheminement = $this->acheminementsRepository->find($data['id']);
            $statutLabel = (intval($data['statut']) === 1) ? Acheminements::STATUT_A_TRAITER : Acheminements::STATUT_TRAITE;
            dump($statutLabel);
            $statut = $this->statutRepository->findOneByCategorieNameAndStatutName(CategorieStatut::ACHEMINEMENT, $statutLabel);
            $acheminement->setStatut($statut);
            $date = new \DateTime('now', new \DateTimeZone('Europe/Paris'));

            $acheminement
                ->setDate($date)
                ->setDate($date)
                ->setRequester($this->utilisateurRepository->find($data['demandeur']))
                ->setReceiver($this->utilisateurRepository->find($data['destinataire']))
                ->setLocationDrop($data['depose'])
                ->setLocationTake($data['prise'])
                ->setColis($data['colis']);
            $em = $this->getDoctrine()->getManager();
            $em->flush();

//            if ($statutLabel == Acheminements::STATUT_TRAITE) {
//                $this->mailerService->sendMail(
//                    'FOLLOW GT // Acheminement effectuée',
//                    $this->renderView('mails/mailAcheminementDone.html.twig', [
//                        'manut' => $acheminement,
//                        'title' => 'Votre demande d\'acheminement a bien été effectuée.',
//                    ]),
//                    $acheminement->getRequester()->getEmail()
//                );
//            }

            $dimension = $this->dimensionsEtiquettesRepository->findOneDimension();
            if ($dimension && !empty($dimension->getHeight()) && !empty($dimension->getWidth())) {
                $response['height'] = $dimension->getHeight();
                $response['width'] = $dimension->getWidth();
                $response['exists'] = true;
            } else {
                $response['exists'] = false;
            }

            $response['codes'] = $data['colis'];
            $response['acheminements'] = $acheminement->getId();

            return new JsonResponse($response);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/api-modifier", name="acheminement_edit_api", options={"expose"=true}, methods="GET|POST")
     */
    public function editApi(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $acheminement = $this->acheminementsRepository->find($data['id']);
            $json = $this->renderView('acheminements/modalEditContentAcheminements.html.twig', [
                'acheminement' => $acheminement,
                'utilisateurs' => $this->utilisateurRepository->findAll(),
                'statut' => (($acheminement->getStatut()->getNom() === Acheminements::STATUT_A_TRAITER) ? 1 : 0),
                'statuts' => $this->statutRepository->findByCategorieName(Acheminements::CATEGORIE),
            ]);

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/supprimer", name="acheminement_delete", options={"expose"=true},methods={"GET","POST"})
     */
    public function delete(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {

            $acheminements = $this->acheminementsRepository->find($data['acheminements']);

                $entityManager = $this->getDoctrine()->getManager();
                $entityManager->remove($acheminements);
                $entityManager->flush();
                $response = true;

            return new JsonResponse($response);
        }

        throw new NotFoundHttpException("404");
    }
}