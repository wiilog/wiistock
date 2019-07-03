<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Arrivage;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\Colis;
use App\Entity\Litige;
use App\Entity\Menu;
use App\Entity\Statut;
use App\Entity\Utilisateur;
use App\Repository\ArrivageRepository;
use App\Repository\ChauffeurRepository;
use App\Repository\DimensionsEtiquettesRepository;
use App\Repository\FournisseurRepository;
use App\Repository\StatutRepository;
use App\Repository\TransporteurRepository;
use App\Repository\TypeRepository;
use App\Repository\UtilisateurRepository;
use App\Service\UserService;
use App\Service\MailerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Tests\Fixtures\Countable;

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
     * @var DimensionsEtiquettesRepository
     */
    private $dimensionsEtiquettesRepository;

    /**
     * @var MailerService
     */
    private $mailerService;

    /**
     * @var TypeRepository
     */
    private $typeRepository;

    public function __construct(MailerService $mailerService, DimensionsEtiquettesRepository $dimensionsEtiquettesRepository, TypeRepository $typeRepository, ChauffeurRepository $chauffeurRepository, TransporteurRepository $transporteurRepository, FournisseurRepository $fournisseurRepository, StatutRepository $statutRepository, UtilisateurRepository $utilisateurRepository, UserService $userService, ArrivageRepository $arrivageRepository)
    {
        $this->dimensionsEtiquettesRepository = $dimensionsEtiquettesRepository;
        $this->userService = $userService;
        $this->arrivageRepository = $arrivageRepository;
        $this->utilisateurRepository = $utilisateurRepository;
        $this->statutRepository = $statutRepository;
        $this->fournisseurRepository = $fournisseurRepository;
        $this->transporteurRepository = $transporteurRepository;
        $this->chauffeurRepository = $chauffeurRepository;
        $this->typeRepository = $typeRepository;
        $this->mailerService = $mailerService;
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
                $currentUser = $this->getUser();
                /** @var Utilisateur $currentUser */
                $arrivages = $currentUser->getArrivagesAcheteur();
            }

            $rows = [];
            foreach ($arrivages as $arrivage) {
                $acheteursUsernames = [];
                foreach ($arrivage->getAcheteurs() as $acheteur) {
                    $acheteursUsernames[] = $acheteur->getUsername();
                }

                $rows[] = [
                    'id' => $arrivage->getId(),
                    'NumeroArrivage' => $arrivage->getNumeroArrivage() ? $arrivage->getNumeroArrivage() : '',
                    'Transporteur' => $arrivage->getTransporteur() ? $arrivage->getTransporteur()->getLabel() : '',
                    'Chauffeur' => $arrivage->getChauffeur() ? $arrivage->getChauffeur()->getPrenomNom() : '',
                    'NoTracking' => $arrivage->getNoTracking() ? $arrivage->getNoTracking() : '',
                    'NumeroBL' => $arrivage->getNumeroBL() ? $arrivage->getNumeroBL() : '',
                    'Fournisseur' => $arrivage->getFournisseur() ? $arrivage->getFournisseur()->getNom() : '',
                    'Destinataire' => $arrivage->getDestinataire() ? $arrivage->getDestinataire()->getUsername() : '',
                    'NbUM' => $arrivage->getNbUM() ? $arrivage->getNbUM() : '',
                    'Acheteurs' => implode(', ', $acheteursUsernames),
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
                ->setNumeroArrivage($numeroArrivage)
                ->setCommentaire($data['commentaire']);

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
            if (isset($data['acheteurs'])) {
                foreach ($data['acheteurs'] as $acheteur) {
                    $arrivage->addAcheteur($this->utilisateurRepository->findOneByUsername($acheteur));
                }
            }
            $path = "../public/uploads/attachements/temporary";
            $parent = "../public/uploads/attachements";
            if (is_dir($path)) {
                foreach(scandir($path) as $file) {
                    if ('.' === $file) continue;
                    if ('..' === $file) continue;
                    $arrivage->addPiecesJointes($file);
                    copy($path . '/' . $file, $parent . '/' . $file);
                }
                $this->delete_files($path);
            }
            if (isset($data['nbUM'])) {
                $arrivage->setNbUM((int)$data['nbUM']);

                for ($i = 0; $i < $data['nbUM']; $i++) {
                    $colis = new Colis();
                    $colis
                        ->setCode($numeroArrivage . '-' . $i)
                        ->setArrivage($arrivage);
                    $em->persist($colis);
                }
            }

            $em->persist($arrivage);

            if ($statutLabel == Statut::ATTENTE_ACHETEUR) {
                $litige = new Litige();
                $litige
                    ->setType($this->typeRepository->find($data['litigeType']))
                    ->setArrivage($arrivage);

                $em->persist($litige);

                $this->sendMailToAcheteurs($arrivage, $litige, true);
            }

            $em->flush();
            $response = [];
            $response['refs'] = [];
            $dimension = $this->dimensionsEtiquettesRepository->findOneDimension();
            if ($dimension && !empty($dimension->getHeight()) && !empty($dimension->getWidth())) {
                $response['height'] = $dimension->getHeight();
                $response['width'] = $dimension->getWidth();
                $response['arrivage'] = $numeroArrivage;
                $response['exists'] = true;
                $response['nbUm'] = $data['nbUM'];
                $response['printUm'] = $data['printUM'];
                $response['printArrivage'] = $data['printArrivage'];
            } else {
                $response['exists'] = false;
            }
            return new JsonResponse($response);
        }
        throw new XmlHttpException('404 not found');
    }


    /**
     * @Route("/api-modifier", name="arrivage_edit_api", options={"expose"=true}, methods="GET|POST")
     */
    public function editApi(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::ARRIVAGE, Action::LIST)) {
                return $this->redirectToRoute('access_denied');
            }
            $arrivage = $this->arrivageRepository->find($data['id']);

            // construction de la chaîne de caractères pour alimenter le select2
            $acheteursUsernames = [];
            foreach ($arrivage->getAcheteurs() as $acheteur) {
                $acheteursUsernames[] = $acheteur->getUsername();
            }

            if ($this->userService->hasRightFunction(Menu::ARRIVAGE, Action::CREATE_EDIT)) {
                $html = $this->renderView('arrivage/modalEditArrivageContent.html.twig', [
                    'arrivage' => $arrivage,
                    'conforme' => $arrivage->getStatut()->getNom() === Statut::CONFORME,
                    'utilisateurs' => $this->utilisateurRepository->findAllSorted(),
                    'statuts' => $this->statutRepository->findByCategorieName(CategorieStatut::ARRIVAGE),
                    'fournisseurs' => $this->fournisseurRepository->findAllSorted(),
                    'transporteurs' => $this->transporteurRepository->findAllSorted(),
                    'chauffeurs' => $this->chauffeurRepository->findAllSorted(),
                    'typesLitige' => $this->typeRepository->findByCategoryLabel(CategoryType::LITIGE)
                ]);
            } elseif (in_array($this->getUser()->getUsername(), $acheteursUsernames)) {
                $html = $this->renderView('arrivage/modalEditArrivageContentLitige.html.twig', [
                    'arrivage' => $arrivage,
                ]);
            } else {
                $html = '';
            }

            return new JsonResponse(['html' => $html, 'acheteurs' => $acheteursUsernames]);
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

            if (isset($data['commentaire'])) {
                $arrivage->setCommentaire($data['commentaire']);
            }

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
            if (isset($data['acheteurs'])) {
                // on détache les acheteurs existants...
                $existingAcheteurs = $arrivage->getAcheteurs();
                foreach ($existingAcheteurs as $acheteur) {
                    $arrivage->removeAcheteur($acheteur);
                }
                // ... et on ajoute ceux sélectionnés
                foreach ($data['acheteurs'] as $acheteur) {
                    $arrivage->addAcheteur($this->utilisateurRepository->findOneByUsername($acheteur));
                }
            }
            if (isset($data['nbUM'])) {
                $arrivage->setNbUM((int)$data['nbUM']);
            }
            if (isset($data['statutAcheteur'])) {
                $statutName = $data['statutAcheteur'] ? Statut::TRAITE_ACHETEUR : Statut::ATTENTE_ACHETEUR;
                $arrivage->setStatut($this->statutRepository->findOneByCategorieAndStatut(CategorieStatut::ARRIVAGE, $statutName));
            }

            // traitement de l'éventuel litige
            $litige = $arrivage->getLitige();

            // non conforme : on enregistre le litige et/ou on le modifie
            $statutLabel = $arrivage->getStatut()->getNom();
            if ($statutLabel != Statut::CONFORME) {
                if (empty($litige)) {
                    $litige = new Litige();
                    $litige->setArrivage($arrivage);
                    $em->persist($litige);
                }

                if (isset($data['litigeType'])) {
                    $litige->setType($this->typeRepository->find($data['litigeType']));
                }

                // si le statut repasse en 'attente acheteur', on envoie un mail aux acheteurs
                if ($statutLabel == Statut::ATTENTE_ACHETEUR) {
                    //TODO CG ajouter protection si statut inchangé
                    $this->sendMailToAcheteurs($arrivage, $litige, false);
                }

                // conforme : on supprime l'éventuel litige
            } else {
                if (!empty($litige)) {
                    $em->remove($litige);
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
            foreach ($arrivage->getColis() as $colis) {
                $entityManager->remove($colis);
            }
            $entityManager->remove($arrivage);
            $entityManager->flush();
            return new JsonResponse();
        }

        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/depose-pj", name="arrivage_depose", options={"expose"=true}, methods="GET|POST")
     */
    public function depose(Request $request): Response
    {
//        dump($request);
        if ($request->isXmlHttpRequest()) {
            $em = $this->getDoctrine()->getManager();

            $fileNames = [];
            $path = "../public/uploads/attachements";

            $id = (int)$request->request->get('id');
            $arrivage = $this->arrivageRepository->find($id);

            for ($i = 0; $i < count($request->files); $i++) {
                $file = $request->files->get('file' . $i);
                if ($file) {
                    // generate a random name for the file but keep the extension
                    $filename = uniqid() . "." . $file->getClientOriginalExtension();
                    $file->move($path, $filename); // move the file to a path

                    $arrivage->addPiecesJointes($filename);
                    $fileNames[] = $filename;
                }
            }
            $em->flush();

            $html = '';
            foreach ($fileNames as $fileName) {
                $html .= $this->renderView('arrivage/attachementLine.html.twig', ['arrivage' => $arrivage, 'pj' => $fileName]);
            }

            return new JsonResponse($html);
        } else {
            throw new NotFoundHttpException('404');
        }
    }

    /**
     * @Route("/supprime-pj", name="arrivage_delete_attachement", options={"expose"=true}, methods="GET|POST")
     */
    public function deleteAttachement(Request $request)
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {

            $arrivageId = (int)$data['arrivageId'];

            $arrivage = $this->arrivageRepository->find($arrivageId);
            if ($arrivage) {
                $arrivage->removePieceJointe($data['pj']);
                $this->getDoctrine()->getManager()->flush();
                $response = true;
            } else {
                $response = false;
            }

            return new JsonResponse($response);
        } else {
            throw new NotFoundHttpException('404');
        }
    }

    private function sendMailToAcheteurs($arrivage, $litige, $newLitige)
    {
        foreach ($arrivage->getAcheteurs() as $acheteur) {
            $this->mailerService->sendMail(
                'FOLLOW GT // Litige sur arrivage',
                $this->renderView('mails/mailLitige.html.twig', [
                    'litige' => $litige,
                    'newLitige' => $newLitige
                ]),
                $acheteur->getEmail()
            );
        }
    }

    /**
     * @Route("/garder-pj", name="garder_pj", options={"expose"=true}, methods="GET|POST")
     */
    public function keepAttachmentForNew(Request $request)
    {
        if ($request->isXmlHttpRequest()) {

            $fileNames = [];
            $html = '';
            $path = "../public/uploads/attachements/temporary";
            for ($i = 0; $i < count($request->files); $i++) {
                $file = $request->files->get('file' . $i);
                if ($file) {
                    $filename = uniqid() . "." . $file->getClientOriginalExtension();
                    $fileNames[] = $filename;
                    $file->move($path, $filename);
                    $html .= $this->renderView('arrivage/attachementLine.html.twig', [
                        'arrivage' => null,
                        'pj' => $filename,
                        'isNew' => true
                    ]);
                }
            }

            return new JsonResponse($html);
        } else {
            throw new NotFoundHttpException('404');
        }
    }

    /**
     * @Route("/enlever-pj", name="remove_kept_pj", options={"expose"=true}, methods="GET|POST")
     */
    public function deleteAttachmentForNew(Request $request)
    {
        if ($request->isXmlHttpRequest()) {
            $path = "../public/uploads/attachements/temporary";
            $this->delete_files($path);
            return new JsonResponse();
        }
        throw new NotFoundHttpException('404');
    }

    function delete_files($target)
    {
        if (is_dir($target)) {
            array_map('unlink', glob("$target/*.*"));
            rmdir($target);
        }
    }

}
