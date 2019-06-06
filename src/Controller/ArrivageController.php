<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Arrivage;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\Litige;
use App\Entity\Menu;
use App\Entity\Statut;
use App\Entity\Utilisateur;
use App\Repository\ArrivageRepository;
use App\Repository\ChauffeurRepository;
use App\Repository\FournisseurRepository;
use App\Repository\StatutRepository;
use App\Repository\TransporteurRepository;
use App\Repository\TypeRepository;
use App\Repository\UtilisateurRepository;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/arrivage")
 */
class ArrivageController extends AbstractController
{
    /**
     * @var UserService
     */
    private $userService;

    /**
     * @var ArrivageRepository
     */
    private $arrivageRepository;

    /**
     * @var UtilisateurRepository
     */
    private $utilisateurRepository;

    /**
     * @var StatutRepository
     */
    private $statutRepository;

    /**
     * @var FournisseurRepository
     */
    private $fournisseurRepository;

    /**
     * @var ChauffeurRepository
     */
    private $chauffeurRepository;

    /**
     * @var TransporteurRepository
     */
    private $transporteurRepository;

    /**
     * @var TypeRepository
     */
    private $typeRepository;

    public function __construct(TypeRepository $typeRepository, ChauffeurRepository $chauffeurRepository, TransporteurRepository $transporteurRepository, FournisseurRepository $fournisseurRepository, StatutRepository $statutRepository, UtilisateurRepository $utilisateurRepository, UserService $userService, ArrivageRepository $arrivageRepository)
    {
        $this->userService = $userService;
        $this->arrivageRepository = $arrivageRepository;
        $this->utilisateurRepository = $utilisateurRepository;
        $this->statutRepository = $statutRepository;
        $this->fournisseurRepository = $fournisseurRepository;
        $this->transporteurRepository = $transporteurRepository;
        $this->chauffeurRepository = $chauffeurRepository;
        $this->typeRepository = $typeRepository;
    }

    /**
     * @Route("/", name="arrivage_index")
     */
    public function index()
    {
        if (!$this->userService->hasRightFunction(Menu::ARRIVAGE, Action::LIST)) {
            return $this->redirectToRoute('access_denied');
        }

        return $this->render('arrivage/index.html.twig', [
            'utilisateurs' => $this->utilisateurRepository->findAllSorted(),
            'statuts' => $this->statutRepository->findByCategorieName(CategorieStatut::ARRIVAGE),
            'fournisseurs' => $this->fournisseurRepository->findAllSorted(),
            'transporteurs' => $this->transporteurRepository->findAllSorted(),
            'chauffeurs' => $this->chauffeurRepository->findAllSorted(),
            'typesLitige' => $this->typeRepository->findByCategoryLabel(CategoryType::LITIGE)
        ]);
    }

    /**
     * @Route("/api", name="arrivage_api", options={"expose"=true}, methods="GET|POST")
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::ARRIVAGE, Action::LIST)) {
                return $this->redirectToRoute('access_denied');
            }

            if ($this->userService->hasRightFunction(Menu::ARRIVAGE, Action::LIST_ALL)) {
                $arrivages = $this->arrivageRepository->findAll();
            } else {
                $currentUser = $this->getUser(); /** @var Utilisateur $currentUser */
                $arrivages = $currentUser->getArrivagesAcheteur();
            }

            $rows = [];
            foreach ($arrivages as $arrivage) {

                $rows[] = [
                    'id' => $arrivage->getId(),
                    'NumeroArrivage' => $arrivage->getNumeroArrivage() ? $arrivage->getNumeroArrivage() : '',
                    'Transporteur' => $arrivage->getTransporteur() ? $arrivage->getTransporteur()->getLabel() : '',
                    'NoTracking' => $arrivage->getNoTracking() ? $arrivage->getNoTracking() : '',
                    'NumeroBL' => $arrivage->getNumeroBL() ? $arrivage->getNumeroBL() : '',
                    'Fournisseur' => $arrivage->getFournisseur() ? $arrivage->getFournisseur()->getNom() : '',
                    'Destinataire' => $arrivage->getDestinataire() ? $arrivage->getDestinataire()->getUsername() : '',
                    'NbUM' => $arrivage->getNbUM() ? $arrivage->getNbUM() : '',
                    'Statut' => $arrivage->getStatut() ? $arrivage->getStatut()->getNom() : '',
                    'Date' => $arrivage->getDate() ? $arrivage->getDate()->format('d/m/Y') : '',
                    'Utilisateur' => $arrivage->getUtilisateur() ? $arrivage->getUtilisateur()->getUsername() : '',
                    'Actions' => $this->renderView('arrivage/datatableArrivageRow.html.twig', [
                        'arrivage' => $arrivage,
                        ])
                ];
            }

            $data['data'] = $rows;

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/creer", name="arrivage_new", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function new(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::ARRIVAGE, Action::CREATE_EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $em = $this->getDoctrine()->getEntityManager();

            $statutLabel = $data['statut'] === '1' ? Statut::CONFORME : Statut::ATTENTE_ACHETEUR;
            $statut = $this->statutRepository->findOneByCategorieAndStatut(CategorieStatut::ARRIVAGE, $statutLabel);
            $date = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
            $numeroArrivage = $date->format('ymdHis');

            $arrivage = new Arrivage();
            $arrivage
                ->setDate($date)
                ->setUtilisateur($this->getUser())
                ->setStatut($statut)
                ->setNumeroArrivage($numeroArrivage);

            if (isset($data['fournisseur'])) {
                $arrivage->setFournisseur($this->fournisseurRepository->find($data['fournisseur']));
            }
            if (isset($data['transporteur'])) {
                $arrivage->setTransporteur($this->transporteurRepository->find($data['transporteur']));
            }
            if (isset($data['chauffeur'])) {
                $arrivage->setChauffeur($this->chauffeurRepository->find($data['chauffeur']));
            }
            if (isset($data['noTracking'])) {
                $arrivage->setNoTracking(substr($data['noTracking'], 0, 64));
            }
            if (isset($data['noBL'])) {
                $arrivage->setNumeroBL(substr($data['noBL'], 0, 64));
            }
            if (isset($data['destinataire'])) {
                $arrivage->setDestinataire($this->utilisateurRepository->find($data['destinataire']));
            }
            if (isset($data['nbUM'])) {
                $arrivage->setNbUM((int)$data['nbUM']);
            }

            $em->persist($arrivage);

            if ($statutLabel == Statut::ATTENTE_ACHETEUR) {
                $litige = new Litige();
                $litige
                    ->setType($this->typeRepository->find($data['litigeType']))
                    ->setArrivage($arrivage)
                    ->setCommentaire($data['commentaire']);
                $em->persist($litige);
            }

            $em->flush();

            return new JsonResponse($data);
        }
        throw new XmlHttpException('404 not found');
    }


    /**
     * @Route("/api-modifier", name="arrivage_edit_api", options={"expose"=true}, methods="GET|POST")
     */
    public function editApi(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::ARRIVAGE, Action::LIST)) {
                return $this->redirectToRoute('access_denied');
            }
            $arrivage = $this->arrivageRepository->find($data['id']);
            $json = $this->renderView('arrivage/modalEditArrivageContent.html.twig', [
                'arrivage' => $arrivage,
                'conforme' => $arrivage->getStatut()->getNom() === Statut::CONFORME,
                'utilisateurs' => $this->utilisateurRepository->findAllSorted(),
                'statuts' => $this->statutRepository->findByCategorieName(CategorieStatut::ARRIVAGE),
                'fournisseurs' => $this->fournisseurRepository->findAllSorted(),
                'transporteurs' => $this->transporteurRepository->findAllSorted(),
                'chauffeurs' => $this->chauffeurRepository->findAllSorted(),
                'typesLitige' => $this->typeRepository->findByCategoryLabel(CategoryType::LITIGE)
            ]);

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }


    /**
     * @Route("/modifier", name="arrivage_edit", options={"expose"=true}, methods="GET|POST")
     */
    public function edit(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::ARRIVAGE, Action::LIST)) {
                return $this->redirectToRoute('access_denied');
            }

            $em = $this->getDoctrine()->getManager();

            $arrivage = $this->arrivageRepository->find($data['id']);

            if (isset($data['statut'])) {
                $statut = $this->statutRepository->find($data['statut']);
                $arrivage->setStatut($statut);
            }
            if (isset($data['fournisseur'])) {
                $arrivage->setFournisseur($this->fournisseurRepository->find($data['fournisseur']));
            }
            if (isset($data['transporteur'])) {
                $arrivage->setTransporteur($this->transporteurRepository->find($data['transporteur']));
            }
            if (isset($data['chauffeur'])) {
                $arrivage->setChauffeur($this->chauffeurRepository->find($data['chauffeur']));
            }
            if (isset($data['noTracking'])) {
                $arrivage->setNoTracking(substr($data['noTracking'], 0, 64));
            }
            if (isset($data['noBL'])) {
                $arrivage->setNumeroBL(substr($data['noBL'], 0, 64));
            }
            if (isset($data['destinataire'])) {
                $arrivage->setDestinataire($this->utilisateurRepository->find($data['destinataire']));
            }
            if (isset($data['nbUM'])) {
                $arrivage->setNbUM((int)$data['nbUM']);
            }

            if (isset($data['litigeType'])) {
                $litige = $arrivage->getLitige();
                $litige->setType($this->typeRepository->find($data['litigeType']));
                if (isset($data['commentaire'])) {
                    $litige->setCommentaire($data['commentaire']);
                }
            }

            $em->flush();

            return new JsonResponse();
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/supprimer", name="arrivage_delete", options={"expose"=true},methods={"GET","POST"})
     */
    public function delete(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $arrivage = $this->arrivageRepository->find($data['arrivage']);

            if (!$this->userService->hasRightFunction(Menu::ARRIVAGE, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($arrivage);
            $entityManager->flush();
            return new JsonResponse();
        }

        throw new NotFoundHttpException("404");
    }
}
