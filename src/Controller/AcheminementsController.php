<?php

namespace App\Controller;

use App\Entity\Acheminements;
use App\Entity\Action;
use App\Entity\CategorieStatut;
use App\Entity\Menu;

use App\Entity\Statut;
use App\Repository\UtilisateurRepository;
use App\Repository\DimensionsEtiquettesRepository;

use App\Service\PDFGeneratorService;
use App\Service\UserService;
use App\Service\AcheminementsService;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * @Route("/acheminements")
 */
Class AcheminementsController extends AbstractController
{
    /**
     * @var UserService
     */
    private $userService;

    /**
     * @var UtilisateurRepository
     */
    private $utilisateurRepository;

    /**
     * @var AcheminementsService
     */
    private $acheminementsService;

    /**
     * @var DimensionsEtiquettesRepository
     */
    private $dimensionsEtiquettesRepository;

    public function __construct(UserService $userService, UtilisateurRepository $utilisateurRepository, AcheminementsService $acheminementsService, DimensionsEtiquettesRepository $dimensionsEtiquettesRepository)
    {
        $this->userService = $userService;
        $this->utilisateurRepository = $utilisateurRepository;
        $this->acheminementsService = $acheminementsService;
        $this->dimensionsEtiquettesRepository = $dimensionsEtiquettesRepository;
    }


    /**
     * @Route("/", name="acheminements_index")
     * @param EntityManagerInterface $entityManager
     * @return RedirectResponse|Response
     */
    public function index(EntityManagerInterface $entityManager)
    {
        if (!$this->userService->hasRightFunction(Menu::TRACA, Action::DISPLAY_ACHE)) {
            return $this->redirectToRoute('access_denied');
        }

        $statutRepository = $entityManager->getRepository(Statut::class);

        return $this->render('acheminements/index.html.twig', [
            'utilisateurs' => $this->utilisateurRepository->findAll(),
			'statuts' => $statutRepository->findByCategorieName(CategorieStatut::ACHEMINEMENT),
        ]);
    }

    /**
     * @Route("/api", name="acheminements_api", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @return Response
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {

            if (!$this->userService->hasRightFunction(Menu::TRACA, Action::DISPLAY_ACHE)) {
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
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws NonUniqueResultException
     */
    public function new(Request $request,
                        EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $statutRepository = $entityManager->getRepository(Statut::class);
            $status = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::ACHEMINEMENT, Acheminements::STATUT_A_TRAITER);
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

            $em = $this->getDoctrine()->getManager();
            $em->persist($acheminements);

            $em->flush();

            $response['acheminement'] = $acheminements->getId();
            return new JsonResponse($response);
        }
        throw new NotFoundHttpException('404 not found');
    }

    /**
     * @Route("/{acheminement}/etat", name="print_acheminement_state_sheet", options={"expose"=true}, methods="GET")
     * @param Acheminements $acheminement
     * @param PDFGeneratorService $PDFGenerator
     * @return PdfResponse
     * @throws LoaderError
     * @throws NoResultException
     * @throws NonUniqueResultException
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function printAcheminementStateSheet(Acheminements $acheminement,
                                                PDFGeneratorService $PDFGenerator): PdfResponse
    {
        $colis = $acheminement->getColis();
        $now = new \DateTime('now', new \DateTimeZone('Europe/Paris'));

        $fileName = 'Etat_acheminement_' . $acheminement->getId() . '.pdf';
        return new PdfResponse(
            $PDFGenerator->generatePDFStateSheet(
                $fileName,
                array_map(
                    function (string $colis) use ($acheminement, $now) {
                        return [
                            'title' => 'Acheminement n°' . $acheminement->getId(),
                            'code' => $colis,
                            'content' => [
                                'Date d\'acheminement' => $now->format('d/m/Y H:i'),
                                'Demandeur' => $acheminement->getRequester()->getUsername(),
                                'Destinataire' => $acheminement->getReceiver()->getUsername(),
                                'Emplacement de dépose' => $acheminement->getLocationDrop(),
                                'Emplacement de prise' => $acheminement->getLocationTake()
                            ]
                        ];
                    },
                    $colis
                )
            ),
            $fileName
        );
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
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws NonUniqueResultException
     */
    public function edit(Request $request,
                         EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $acheminement = $this->acheminementsRepository->find($data['id']);

            $statutRepository = $entityManager->getRepository(Statut::class);
            $statutLabel = (intval($data['statut']) === 1) ? Acheminements::STATUT_A_TRAITER : Acheminements::STATUT_TRAITE;
            $statut = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::ACHEMINEMENT, $statutLabel);

            $acheminement->setStatut($statut);
            $date = new \DateTime('now', new \DateTimeZone('Europe/Paris'));

            $acheminement
                ->setDate($date)
                ->setRequester($this->utilisateurRepository->find($data['demandeur']))
                ->setReceiver($this->utilisateurRepository->find($data['destinataire']))
                ->setLocationDrop($data['depose'])
                ->setLocationTake($data['prise'])
                ->setColis($data['colis']);
            $em = $this->getDoctrine()->getManager();
            $em->flush();

            $response['acheminement'] = $acheminement->getId();

            return new JsonResponse($response);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/api-modifier", name="acheminement_edit_api", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function editApi(Request $request,
                            EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $statutRepository = $entityManager->getRepository(Statut::class);
            $acheminement = $this->acheminementsRepository->find($data['id']);
            $json = $this->renderView('acheminements/modalEditContentAcheminements.html.twig', [
                'acheminement' => $acheminement,
                'utilisateurs' => $this->utilisateurRepository->findAll(),
                'statut' => (($acheminement->getStatut()->getNom() === Acheminements::STATUT_A_TRAITER) ? 1 : 0),
                'statuts' => $statutRepository->findByCategorieName(Acheminements::CATEGORIE),
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
