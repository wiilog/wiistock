<?php

namespace App\Controller;

use App\Entity\Acheminements;
use App\Entity\Action;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\ChampLibre;
use App\Entity\Emplacement;
use App\Entity\Menu;

use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\Utilisateur;

use App\Service\AttachmentService;
use App\Service\FreeFieldService;
use App\Service\PDFGeneratorService;
use App\Service\UserService;
use App\Service\AcheminementsService;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Exception;
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
     * @var AcheminementsService
     */
    private $acheminementsService;

    public function __construct(UserService $userService,
                                AcheminementsService $acheminementsService)
    {
        $this->userService = $userService;
        $this->acheminementsService = $acheminementsService;
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
        $type = $entityManager->getRepository(Type::class);
        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $champLibreRepository = $entityManager->getRepository(ChampLibre::class);

        $listTypes = $typeRepository->findByCategoryLabel(CategoryType::DEMANDE_ACHEMINEMENT);

        $typeChampLibre = [];

        $freeFieldsGroupedByTypes = [];
        foreach ($listTypes as $type) {
            $champsLibres = $champLibreRepository->findByTypeAndCategorieCLLabel($type, CategorieCL::DEMANDE_ACHEMINEMENT);
            $typeChampLibre[] = [
                'typeLabel' => $type->getLabel(),
                'typeId' => $type->getId(),
                'champsLibres' => $champsLibres,
            ];
            $freeFieldsGroupedByTypes[$type->getId()] = $champsLibres;
        }

        return $this->render('acheminements/index.html.twig', [
            'utilisateurs' => $utilisateurRepository->findAll(),
			'statuts' => $statutRepository->findByCategorieName(CategorieStatut::ACHEMINEMENT),
            'typeChampsLibres' => $typeChampLibre,
            'freeFieldsGroupedByTypes' => $freeFieldsGroupedByTypes,
            'types' => $typeRepository->findByCategoryLabel(CategoryType::DEMANDE_ACHEMINEMENT)
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
     * @param FreeFieldService $freeFieldService
     * @param AttachmentService $attachmentService
     * @param AcheminementsService $acheminementsService
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws Exception
     */
    public function new(Request $request,
                        FreeFieldService $freeFieldService,
                        AttachmentService $attachmentService,
                        AcheminementsService $acheminementsService,
                        EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest()) {
            $post = $request->request;
            $statutRepository = $entityManager->getRepository(Statut::class);
            $typeRepository = $entityManager->getRepository(Type::class);
            $emplacementRepository = $entityManager->getRepository(Emplacement::class);
            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
            $acheminements = new Acheminements();
            $date = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
            $fileBag = $request->files->count() > 0 ? $request->files : null;

            $acheminementNumber = $acheminementsService->createAcheminementNumber($entityManager, $date);

            $acheminements
                ->setDate($date)
                ->setDate($date)
                ->setStatut($statutRepository->find($post->get('statut')))
                ->setType($typeRepository->find($post->get('type')))
                ->setRequester($utilisateurRepository->find($post->get('demandeur')))
                ->setReceiver($utilisateurRepository->find($post->get('destinataire')))
                ->setLocationFrom($emplacementRepository->find($post->get('prise')))
                ->setLocationTo($emplacementRepository->find($post->get('depose')))
                ->setCommentaire($post->get('commentaire') ?? null)
                ->setNumeroAcheminement($acheminementNumber);

            $freeFieldService->manageFreeFields($acheminements, $post->all(), $entityManager);

            if (isset($fileBag)) {
                $fileNames = [];
                foreach ($fileBag->all() as $file) {
                    $fileNames = array_merge(
                        $fileNames,
                        $attachmentService->saveFile($file)
                    );
                }
                $attachments = $attachmentService->createAttachements($fileNames);
                foreach ($attachments as $attachment) {
                    $entityManager->persist($attachment);
                    $acheminements->addAttachement($attachment);
                }
            }

            $entityManager->persist($acheminements);

            $entityManager->flush();

            $response['acheminement'] = $acheminements->getId();
            return new JsonResponse($response);
        }
        throw new NotFoundHttpException('404 not found');
    }

    /**
     * @Route("/voir/{id}", name="acheminement-show", options={"expose"=true}, methods="GET|POST")
     * @param Acheminements $acheminement
     * @param AcheminementsService $acheminementService
     * @return RedirectResponse|Response
     */
    public function show(Acheminements $acheminement, AcheminementsService $acheminementService)
    {
        if (!$this->userService->hasRightFunction(Menu::TRACA, Action::DISPLAY_ACHE)) {
            return $this->redirectToRoute('access_denied');
        }

        return $this->render('acheminements/show.html.twig', [
            'acheminement' => $acheminement,
            'detailsConfig' => $acheminementService->createHeaderDetailsConfig($acheminement),
            'modifiable' => ($acheminement->getStatut()->getNom() == Acheminements::STATUT_A_TRAITER)
        ]);
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
     * @throws Exception
     */
    public function printAcheminementStateSheet(Acheminements $acheminement,
                                                PDFGeneratorService $PDFGenerator): PdfResponse
    {
        $packs = $acheminement->getPacks();
        $now = new \DateTime('now', new \DateTimeZone('Europe/Paris'));

        $fileName = 'Etat_acheminement_' . $acheminement->getId() . '.pdf';
        return new PdfResponse(
            $PDFGenerator->generatePDFStateSheet(
                $fileName,
                array_map(
                    function (string $pack) use ($acheminement, $now) {
                        return [
                            'title' => 'Acheminement n°' . $acheminement->getId(),
                            'code' => $pack,
                            'content' => [
                                'Date d\'acheminement' => $now->format('d/m/Y H:i'),
                                'Demandeur' => $acheminement->getRequester()->getUsername(),
                                'Destinataire' => $acheminement->getReceiver()->getUsername(),
                                'Emplacement de dépose' => $acheminement->getLocationTo() ? $acheminement->getLocationTo()->getLabel() : '',
                                'Emplacement de prise' => $acheminement->getLocationFrom() ? $acheminement->getLocationFrom()->getLabel() : ''
                            ]
                        ];
                    },
                    $packs
                )
            ),
            $fileName
        );
    }

    /**
     * @Route("/modifier", name="acheminement_edit", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws NonUniqueResultException
     * @throws Exception
     */
    public function edit(Request $request,
                         EntityManagerInterface $entityManager): Response {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $statutRepository = $entityManager->getRepository(Statut::class);
            $acheminementsRepository = $entityManager->getRepository(Acheminements::class);
            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);

            $acheminement = $acheminementsRepository->find($data['id']);

            $statutLabel = (intval($data['statut']) === 1) ? Acheminements::STATUT_A_TRAITER : Acheminements::STATUT_TRAITE;
            $statut = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::ACHEMINEMENT, $statutLabel);

            $acheminement->setStatut($statut);
            $date = new \DateTime('now', new \DateTimeZone('Europe/Paris'));

            $acheminement
                ->setDate($date)
                ->setRequester($utilisateurRepository->find($data['demandeur']))
                ->setReceiver($utilisateurRepository->find($data['destinataire']))
                ->setLocationDrop($data['depose'])
                ->setLocationTake($data['prise'])
                ->setPacks(is_array($data['colis']) ? $data['colis'] : [$data['colis']]);

            $entityManager->flush();

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
            $acheminementsRepository = $entityManager->getRepository(Acheminements::class);
            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);

            $acheminement = $acheminementsRepository->find($data['id']);
            $json = $this->renderView('acheminements/modalEditContentAcheminements.html.twig', [
                'acheminement' => $acheminement,
                'utilisateurs' => $utilisateurRepository->findBy([], ['username' => 'ASC']),
                'statut' => (($acheminement->getStatut()->getNom() === Acheminements::STATUT_A_TRAITER) ? 1 : 0),
                'statuts' => $statutRepository->findByCategorieName(Acheminements::CATEGORIE),
            ]);

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/supprimer", name="acheminement_delete", options={"expose"=true},methods={"GET","POST"})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function delete(Request $request,
                           EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $acheminementsRepository = $entityManager->getRepository(Acheminements::class);
            $acheminements = $acheminementsRepository->find($data['acheminements']);
            $entityManager->remove($acheminements);
            $entityManager->flush();

            return new JsonResponse(true);
        }

        throw new NotFoundHttpException("404");
    }
}
