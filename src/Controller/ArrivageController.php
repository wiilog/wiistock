<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Arrivage;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\Colis;
use App\Entity\Litige;
use App\Entity\LitigeHistoric;
use App\Entity\Menu;
use App\Entity\ParamClient;
use App\Entity\PieceJointe;
use App\Entity\Urgence;
use App\Entity\Utilisateur;

use App\Repository\ArrivageRepository;
use App\Repository\ChampLibreRepository;
use App\Repository\ColisRepository;
use App\Repository\LitigeRepository;
use App\Repository\ChauffeurRepository;
use App\Repository\DimensionsEtiquettesRepository;
use App\Repository\FournisseurRepository;
use App\Repository\MouvementTracaRepository;
use App\Repository\NatureRepository;
use App\Repository\PieceJointeRepository;
use App\Repository\StatutRepository;
use App\Repository\TransporteurRepository;
use App\Repository\TypeRepository;
use App\Repository\UrgenceRepository;
use App\Repository\UtilisateurRepository;

use App\Service\AttachmentService;
use App\Service\SpecificService;
use App\Service\UserService;
use App\Service\MailerService;

use Doctrine\ORM\NonUniqueResultException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

use DateTime;

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

    /**
     * @var PieceJointeRepository
     */
    private $pieceJointeRepository;

    /**
     * @var SpecificService
     */
    private $specificService;

    /**
     * @var AttachmentService
     */
    private $attachmentService;

    /**
     * @var ChampLibreRepository
     */
    private $champLibreRepository;

    /**
     * @var LitigeRepository
     */
    private $litigeRepository;

    /**
     * @var ColisRepository
     */
    private $colisRepository;

    /**
     * @var MouvementTracaRepository
     */
    private $mouvementTracaRepository;

    /**
     * @var UrgenceRepository
     */
    private $urgenceRepository;
    /**
     * @var NatureRepository
     */
    private $natureRepository;

    public function __construct(UrgenceRepository $urgenceRepository, AttachmentService $attachmentService, NatureRepository $natureRepository, MouvementTracaRepository $mouvementTracaRepository, ColisRepository $colisRepository, PieceJointeRepository $pieceJointeRepository, LitigeRepository $litigeRepository, ChampLibreRepository $champsLibreRepository, SpecificService $specificService, MailerService $mailerService, DimensionsEtiquettesRepository $dimensionsEtiquettesRepository, TypeRepository $typeRepository, ChauffeurRepository $chauffeurRepository, TransporteurRepository $transporteurRepository, FournisseurRepository $fournisseurRepository, StatutRepository $statutRepository, UtilisateurRepository $utilisateurRepository, UserService $userService, ArrivageRepository $arrivageRepository)
    {
        $this->urgenceRepository = $urgenceRepository;
        $this->specificService = $specificService;
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
        $this->champLibreRepository = $champsLibreRepository;
        $this->litigeRepository = $litigeRepository;
        $this->pieceJointeRepository = $pieceJointeRepository;
        $this->colisRepository = $colisRepository;
        $this->mouvementTracaRepository = $mouvementTracaRepository;
        $this->attachmentService = $attachmentService;
        $this->natureRepository = $natureRepository;
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
            'fournisseurs' => $this->fournisseurRepository->findAllSorted(),
            'transporteurs' => $this->transporteurRepository->findAllSorted(),
            'chauffeurs' => $this->chauffeurRepository->findAllSorted(),
            'typesLitige' => $this->typeRepository->findByCategoryLabel(CategoryType::LITIGE),
            'natures' => $this->natureRepository->findAll(),
            'statuts' => [
                ['nom' => Arrivage::STATUS_CONFORME],
                ['nom' => Arrivage::STATUS_LITIGE]
            ]
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
                $url = $this->generateUrl('arrivage_show', [
                    'id' => $arrivage->getId(),
                ]);
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
                    'NbUM' => $this->arrivageRepository->countColisByArrivage($arrivage),
                    'Fournisseur' => $arrivage->getFournisseur() ? $arrivage->getFournisseur()->getNom() : '',
                    'Destinataire' => $arrivage->getDestinataire() ? $arrivage->getDestinataire()->getUsername() : '',
                    'Acheteurs' => implode(', ', $acheteursUsernames),
                    'Statut' => $arrivage->getStatus(),
                    'Date' => $arrivage->getDate() ? $arrivage->getDate()->format('d/m/Y H:i:s') : '',
                    'Utilisateur' => $arrivage->getUtilisateur() ? $arrivage->getUtilisateur()->getUsername() : '',
                    'Actions' => $this->renderView(
                        'arrivage/datatableArrivageRow.html.twig',
                        ['url' => $url, 'arrivage' => $arrivage]
                    ),
                    'urgent' => $arrivage->getIsUrgent() === true ? true : false,
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
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::ARRIVAGE, Action::CREATE_EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $post = $request->request;
            $em = $this->getDoctrine()->getManager();

            $date = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
            $numeroArrivage = $date->format('ymdHis');

            $arrivage = new Arrivage();
            $arrivage
                ->setIsUrgent(false)
                ->setDate($date)
                ->setUtilisateur($this->getUser())
                ->setNumeroArrivage($numeroArrivage)
                ->setCommentaire($post->get('commentaire') ?? null);

            if (!empty($fournisseur = $post->get('fournisseur'))) {
                $arrivage->setFournisseur($this->fournisseurRepository->find($fournisseur));
            }
            if (!empty($transporteur = $post->get('transporteur'))) {
                $arrivage->setTransporteur($this->transporteurRepository->find($transporteur));
            }
            if (!empty($chauffeur = $post->get('chauffeur'))) {
                $arrivage->setChauffeur($this->chauffeurRepository->find($chauffeur));
            }
            if (!empty($noTracking = $post->get('noTracking'))) {
                $arrivage->setNoTracking(substr($noTracking, 0, 64));
            }
            if (!empty($noBL = $post->get('noBL'))) {
                $arrivage->setNumeroBL(substr($noBL, 0, 64));

            }
            if (!empty($destinataire = $post->get('destinataire'))) {
                $arrivage->setDestinataire($this->utilisateurRepository->find($destinataire));
            }
            if (!empty($acheteurs = $post->get('acheteurs'))) {
                if (!empty($post->get('acheteurs'))) {
                    $acheteurs = explode(',', $post->get('acheteurs'));
                    foreach ($acheteurs as $acheteur) {
                        $arrivage->addAcheteur($this->utilisateurRepository->findOneByUsername($acheteur));
                    }
                }
            }

            $em->persist($arrivage);
            $em->flush();

            $this->attachmentService->addAttachements($request, $arrivage);
            if ($arrivage->getNumeroBL()) {
                $urgences = $this->urgenceRepository->findByArrivageData($arrivage);
                if (intval($urgences) > 0) {
                    $arrivage->setIsUrgent(true);
                }
            }
            $em->flush();
			$arrivageNum = $arrivage->getNumeroArrivage();

            $codes = [];

			$natures = json_decode($post->get('nature'), true);

			$checkNatures = $this->natureRepository->countAll();
			if ($checkNatures != 0) {
                foreach ($natures as $natureArray) {
                    $nature = $this->natureRepository->find($natureArray['id']);

                    for ($i = 0; $i < $natureArray['val']; $i++) {

                        $highestCode = $this->colisRepository->getHighestCodeByPrefix($arrivageNum);
                        if ($highestCode) {
                            $highestCodeArray = explode('-', $highestCode);
                            $highestCounter = $highestCodeArray ? $highestCodeArray[1] : 0;
                        } else {
                            $highestCounter = 0;
                        }

                        $newCounter = sprintf('%05u', $highestCounter + 1);

                        $colis = new Colis();
                        $code = $arrivageNum . '-' . $newCounter;
                        $colis
                            ->setCode($code)
                            ->setNature($nature)
                            ->setArrivage($arrivage);
                        $em->persist($colis);
                        $em->flush();
                        $codes[] = $code;
                    }
                }
            }

			$this->attachmentService->addAttachements($request, $arrivage);

			$em->persist($arrivage);
            $em->flush();

            $this->attachmentService->addAttachements($request, $arrivage);

            $printColis = null;
            $printArrivage = null;
            if ($post->get('printColis') === 'true') {
                $printColis = true;
            }
            if ($post->get('printArrivage') === 'true') {
                $printArrivage = true;
            }

            $data = [
                "redirect" => $this->generateUrl('arrivage_show', [
                    'id' => $arrivage->getId(),
                ]),
                'printColis' => $printColis,
                'printArrivage' => $printArrivage,
            ];
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404 not found');
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
                    'attachements' => $this->pieceJointeRepository->findBy(['arrivage' => $arrivage]),
                    'utilisateurs' => $this->utilisateurRepository->findAllSorted(),
                    'fournisseurs' => $this->fournisseurRepository->findAllSorted(),
                    'transporteurs' => $this->transporteurRepository->findAllSorted(),
                    'chauffeurs' => $this->chauffeurRepository->findAllSorted(),
                    'typesLitige' => $this->typeRepository->findByCategoryLabel(CategoryType::LITIGE)
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
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::ARRIVAGE, Action::LIST)) {
                return $this->redirectToRoute('access_denied');
            }
            $post = $request->request;
            $em = $this->getDoctrine()->getManager();

            $arrivage = $this->arrivageRepository->find($post->get('id'));

            if (!empty($commentaire = $post->get('commentaire'))) {
                $arrivage->setCommentaire($commentaire);
            }
            if (!empty($fournisseur = $post->get('fournisseur'))) {
                $arrivage->setFournisseur($this->fournisseurRepository->find($fournisseur));
            }
            if (!empty($transporteur = $post->get('transporteur'))) {
                $arrivage->setTransporteur($this->transporteurRepository->find($transporteur));
            }
            if (!empty($chauffeur = $post->get('chauffeur'))) {
                $arrivage->setChauffeur($this->chauffeurRepository->find($chauffeur));
            }
            if (!empty($noTracking = $post->get('noTracking'))) {
                $arrivage->setNoTracking(substr($noTracking, 0, 64));
            }
            if (!empty($noBL = $post->get('noBL'))) {
                $arrivage->setNumeroBL(substr($noBL, 0, 64));
            }
            if (!empty($destinataire = $post->get('destinataire'))) {
                $arrivage->setDestinataire($this->utilisateurRepository->find($destinataire));
            }
            if (!empty($acheteurs = $post->get('acheteurs'))) {
                // on détache les acheteurs existants...
                $existingAcheteurs = $arrivage->getAcheteurs();
                foreach ($existingAcheteurs as $acheteur) {
                    $arrivage->removeAcheteur($acheteur);
                }
                // ... et on ajoute ceux sélectionnés
                $listAcheteurs = explode(',', $acheteurs);
                foreach ($listAcheteurs as $acheteur) {
                    $arrivage->addAcheteur($this->utilisateurRepository->findOneByUsername($acheteur));
                }
            }

            $em->flush();

            $listAttachmentIdToKeep = $post->get('files');

            $attachments = $arrivage->getAttachements()->toArray();
            foreach ($attachments as $attachment) {
                /** @var PieceJointe $attachment */
                if (!in_array($attachment->getId(), $listAttachmentIdToKeep)) {
                    $this->attachmentService->removeAndDeleteAttachment($attachment, $arrivage);
                }
            }

            $this->attachmentService->addAttachements($request, $arrivage);

            $response = [
                'entete' => $this->renderView('arrivage/enteteArrivage.html.twig', [
                    'arrivage' => $arrivage,
                    'canBeDeleted' => $this->arrivageRepository->countLitigesUnsolvedByArrivage($arrivage) == 0,
                ]),
            ];
            return new JsonResponse($response);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/supprimer", name="arrivage_delete", options={"expose"=true},methods={"GET","POST"})
     */
    public function delete(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $arrivage = $this->arrivageRepository->find($data['arrivage']);

            if (!$this->userService->hasRightFunction(Menu::ARRIVAGE, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }

            $canBeDeleted = ($this->arrivageRepository->countLitigesUnsolvedByArrivage($arrivage) == 0);

            if ($canBeDeleted) {
                $entityManager = $this->getDoctrine()->getManager();
                foreach ($arrivage->getColis() as $colis) {
                    $litiges = $colis->getLitiges();
                    $entityManager->remove($colis);
                    foreach ($litiges as $litige) {
                        $entityManager->remove($litige);
                    }
                }
                foreach ($arrivage->getAttachements() as $attachement) {
                    $this->attachmentService->removeAndDeleteAttachment($attachement, $arrivage);
                }
                $entityManager->remove($arrivage);
                $entityManager->flush();
                $data = [
                    "redirect" => $this->generateUrl('arrivage_index')
                ];
            } else {
                $data = false;
            }
            return new JsonResponse($data);
        }

        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/depose-pj", name="arrivage_depose", options={"expose"=true}, methods="GET|POST")
     */
    public function depose(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            $em = $this->getDoctrine()->getManager();

            $fileNames = [];
            $path = "../public/uploads/attachements";

            $id = (int)$request->request->get('id');
            $arrivage = $this->arrivageRepository->find($id);

            for ($i = 0; $i < count($request->files); $i++) {
                $file = $request->files->get('file' . $i);
                if ($file) {
                    if ($file->getClientOriginalExtension()) {
                        $filename = uniqid() . "." . $file->getClientOriginalExtension();
                    } else {
                        $filename = uniqid();
                    }
                    $file->move($path, $filename);

                    $pj = new PieceJointe();
                    $pj
                        ->setFileName($filename)
                        ->setOriginalName($file->getClientOriginalName())
                        ->setArrivage($arrivage);
                    $em->persist($pj);

                    $fileNames[] = ['name' => $filename, 'originalName' => $file->getClientOriginalName()];
                }
            }
            $em->flush();

            $html = '';
            foreach ($fileNames as $fileName) {
                $html .= $this->renderView('arrivage/attachementLine.html.twig', [
                    'arrivage' => $arrivage,
                    'pjName' => $fileName['name'],
                    'originalName' => $fileName['originalName']
                ]);
            }

            return new JsonResponse($html);
        } else {
            throw new NotFoundHttpException('404');
        }
    }

    private function sendMailToAcheteurs(Litige $litige)
    {
        $acheteursEmail = $this->litigeRepository->getAcheteursByLitige($litige->getId());
        foreach ($acheteursEmail as $email) {
            $title = 'Un litige a été déclaré sur un arrivage vous concernant :';

            $this->mailerService->sendMail(
                'FOLLOW GT // Litige sur arrivage',
                $this->renderView('mails/mailLitiges.html.twig', [
                    'litiges' => [$litige],
                    'title' => $title,
                    'urlSuffix' => 'arrivage'
                ]),
                $email
            );
        }
    }

    /**
     * @Route("/ajoute-commentaire", name="add_comment",  options={"expose"=true}, methods="GET|POST")
     */
    public function addComment(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $response = '';

            // spécifique SAFRAN CERAMICS ajout de commentaire
            $isSafran = $this->specificService->isCurrentClientNameFunction(ParamClient::SAFRAN_CERAMICS);
            if ($isSafran) {
                $type = $this->typeRepository->find($data['typeLitigeId']);
                $response = $type->getDescription();
            }

            return new JsonResponse($response);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/lister-colis", name="arrivage_list_colis_api", options={"expose"=true})
     */
    public function listColisByArrivage(Request $request)
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $arrivage = $this->arrivageRepository->find($data['id']);

            $html = $this->renderView('arrivage/modalListColisContent.html.twig',
                ['arrivage' => $arrivage]);

            return new JsonResponse($html);

        } else {
            throw new NotFoundHttpException('404');
        }
    }

    /**
     * @Route("/api-etiquettes-arrivage", name="arrivage_get_data_to_print", options={"expose"=true})
     */
    public function getDataToPrintLabels(Request $request)
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {

            $arrivage = $data;
            $codeColis = $this->arrivageRepository->getColisByArrivage($arrivage);

            $dimension = $this->dimensionsEtiquettesRepository->findOneDimension();
            if ($dimension) {
                $response['height'] = $dimension->getHeight();
                $response['width'] = $dimension->getWidth();
                $response['exists'] = true;
            } else {
                $response['height'] = $response['width'] = 0;
                $response['exists'] = false;
            }
            $responseData = array('response' => $response, 'codeColis' => $codeColis);
            return new JsonResponse($responseData);

        } else {
            throw new NotFoundHttpException('404');
        }
    }

    /**
     * @Route("/api-etiquettes", name="get_print_data", options={"expose"=true})
     */
    public function getPrintData(Request $request)
    {
        if ($request->isXmlHttpRequest()) {
            $dimension = $this->dimensionsEtiquettesRepository->findOneDimension();
            if ($dimension) {
                $response['height'] = $dimension->getHeight();
                $response['width'] = $dimension->getWidth();
                $response['exists'] = true;
            } else {
                $response['height'] = $response['width'] = 0;
                $response['exists'] = false;
            }

            return new JsonResponse($response);

        } else {
            throw new NotFoundHttpException('404');
        }
    }

    /**
     * @Route("/garder-pj", name="garder_pj", options={"expose"=true}, methods="GET|POST")
     */
    public function displayAttachmentForNew(Request $request)
    {
        if ($request->isXmlHttpRequest()) {
            $em = $this->getDoctrine()->getManager();

            $fileNames = [];
            $html = '';
            $path = "../public/uploads/attachements/temp/";
            if (!file_exists($path)) {
                mkdir($path, 0777);
            }
            for ($i = 0; $i < count($request->files); $i++) {
                $file = $request->files->get('file' . $i);
                if ($file) {
                    if ($file->getClientOriginalExtension()) {
                        $filename = uniqid() . "." . $file->getClientOriginalExtension();
                    } else {
                        $filename = uniqid();
                    }
                    $fileNames[] = $filename;
                    $file->move($path, $filename);
                    $html .= $this->renderView('arrivage/attachementLine.html.twig', [
                        'pjName' => $filename,
                        'originalName' => $file->getClientOriginalName()
                    ]);
                    $pj = new PieceJointe();
                    $pj
                        ->setOriginalName($file->getClientOriginalName())
                        ->setFileName($filename);
                    $em->persist($pj);
                }
                $em->flush();
            }

            return new JsonResponse($html);
        } else {
            throw new NotFoundHttpException('404');
        }
    }

    /**
     * @Route("/arrivage-infos", name="get_arrivages_for_csv", options={"expose"=true}, methods={"GET","POST"})
     */
    public function getArrivageIntels(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $dateMin = $data['dateMin'] . ' 00:00:00';
            $dateMax = $data['dateMax'] . ' 23:59:59';
            $arrivages = $this->arrivageRepository->findByDates($dateMin, $dateMax);

            $headers = [];
            // en-têtes champs fixes
            $headers = array_merge($headers, ['n° arrivage', 'destinataire', 'fournisseur', 'transporteur', 'chauffeur', 'n° tracking transporteur',
                'n° commande/BL', 'acheteurs', 'nombre d\'UM', 'statut', 'commentaire', 'date', 'utilisateur']);

            $data = [];
            $data[] = $headers;

            foreach ($arrivages as $arrivage) {
                $arrivageData = [];

                $arrivageData[] = $arrivage->getNumeroArrivage();
                $arrivageData[] = $arrivage->getDestinataire()->getUsername();
                $arrivageData[] = $arrivage->getFournisseur()->getNom();
                $arrivageData[] = $arrivage->getTransporteur()->getLabel();
                $arrivageData[] = $arrivage->getChauffeur() ? $arrivage->getChauffeur()->getNom() . ' ' . $arrivage->getChauffeur()->getPrenom() : '';
                $arrivageData[] = $arrivage->getNoTracking() ? $arrivage->getNoTracking() : '';
                $arrivageData[] = $arrivage->getNumeroBL() ? $arrivage->getNumeroBL() : '';

                $acheteurs = $arrivage->getAcheteurs();
                $acheteurData = [];
                foreach ($acheteurs as $acheteur) {
                    $acheteurData[] = $acheteur->getUsername();
                }
                $arrivageData[] = implode(' / ', $acheteurData);
                $arrivageData[] = $arrivage->getStatus();
                $arrivageData[] = strip_tags($arrivage->getCommentaire());
                $arrivageData[] = $arrivage->getDate()->format('Y/m/d-H:i:s');
                $arrivageData[] = $arrivage->getUtilisateur()->getUsername();

                $data[] = $arrivageData;
            }
            return new JsonResponse($data);
        } else {
            throw new NotFoundHttpException('404');
        }
    }

    /**
	 * @param Arrivage $arrivage
	 * @Route("/voir/{id}/{printColis}/{printArrivage}", name="arrivage_show", options={"expose"=true}, methods={"GET", "POST"})
	 * @return JsonResponse
	 * @throws NonUniqueResultException
	 */
    public function show(Arrivage $arrivage, bool $printColis = false, bool $printArrivage = false): Response
    {
        if (!$this->userService->hasRightFunction(Menu::ARRIVAGE, Action::LIST_ALL)) {
            return $this->redirectToRoute('access_denied');
        }

        $acheteursNames = [];
        foreach ($arrivage->getAcheteurs() as $user) {
            $acheteursNames[] = $user->getUsername();
        }

        return $this->render("arrivage/show.html.twig",
            [
                'arrivage' => $arrivage,
                'typesLitige' => $this->typeRepository->findByCategoryLabel(CategoryType::LITIGE),
                'acheteurs' => $acheteursNames,
                'statusLitige' => $this->statutRepository->findByCategorieName(CategorieStatut::LITIGE_ARR, true),
                'allColis' => $arrivage->getColis(),
				'natures' => $this->natureRepository->findAll(),
				'printColis' => $printColis,
				'printArrivage' => $printArrivage,
				'canBeDeleted' => $this->arrivageRepository->countLitigesUnsolvedByArrivage($arrivage) == 0,
            ]);
    }

    /**
     * @Route("/creer-litige", name="litige_new", options={"expose"=true}, methods={"POST"})
     */
    public function newLitige(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::LITIGE, Action::CREATE)) {
                return $this->redirectToRoute('access_denied');
            }

            $post = $request->request;
            $em = $this->getDoctrine()->getManager();

            $litige = new Litige();
            $litige
                ->setStatus($this->statutRepository->find($post->get('statutLitige')))
                ->setType($this->typeRepository->find($post->get('typeLitige')))
                ->setCreationDate(new \DateTime('now'));

            if (!empty($colis = $post->get('colisLitige'))) {
                $listColisId = explode(',', $colis);
                foreach ($listColisId as $colisId) {
                    $litige->addColi($this->colisRepository->find($colisId));
                }
            }
            $statutinstance = $this->statutRepository->find($post->get('statutLitige'));
            $commentStatut = $statutinstance->getComment();

            $trimCommentStatut = trim($commentStatut);
            $userComment = trim($post->get('commentaire'));
            $nl = !empty($userComment) ? "\n" : '';
            $commentaire = $userComment . (!empty($trimCommentStatut) ? ($nl . $commentStatut) : '');
            if (!empty($commentaire)) {
                $histo = new LitigeHistoric();
                $histo
                    ->setDate(new \DateTime('now'))
                    ->setComment($commentaire)
                    ->setLitige($litige)
                    ->setUser($this->getUser());
                $em->persist($histo);
            }

            $em->persist($litige);
            $em->flush();

            $this->attachmentService->addAttachements($request, null, $litige);

            $this->sendMailToAcheteurs($litige);

            $arrivageResponse = $this->getResponseReloadArrivage($request->query->get('reloadArrivage'));
            $response = $arrivageResponse ? $arrivageResponse : [];

            return new JsonResponse($response);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/supprimer-litige", name="litige_delete", options={"expose"=true}, methods="GET|POST")
     */
    public function deleteLitige(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::LITIGE, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }

            $litige = $this->litigeRepository->find($data['litige']);

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($litige);
            $entityManager->flush();
            return new JsonResponse();
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/ajouter-colis", name="arrivage_add_colis", options={"expose"=true}, methods={"GET", "POST"})
     * @return JsonResponse
     * @throws NonUniqueResultException
     */
    public function addColis(Request $request)
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::ARRIVAGE, Action::CREATE_EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $em = $this->getDoctrine()->getManager();

            $arrivage = $this->arrivageRepository->find($data['arrivageId']);

            $codes = [];

            $natureKey = array_keys($data);
            foreach ($natureKey as $natureId) {
                if (gettype($natureId) === 'integer') {
                    $nature = $this->natureRepository->find($natureId);
                    for ($i = 0; $i < $data[$natureId]; $i++) {
                        $arrivageNum = $arrivage->getNumeroArrivage();
                        $highestCode = $this->colisRepository->getHighestCodeByPrefix($arrivageNum);
                        if ($highestCode) {
                            $highestCodeArray = explode('-', $highestCode);
                            $highestCounter = $highestCodeArray ? $highestCodeArray[1] : 0;
                        } else {
                            $highestCounter = 0;
                        }

                        $newCounter = sprintf('%05u', $highestCounter + 1);

                        $colis = new Colis();
                        $code = $arrivageNum . '-' . $newCounter;
                        $colis
                            ->setCode($code)
                            ->setNature($nature)
                            ->setArrivage($arrivage);
                        $em->persist($colis);
                        $em->flush();

                        $codes[] = $code;
                    }
                }
            }

            $response = [];
            $dimension = $this->dimensionsEtiquettesRepository->findOneDimension();
            if ($dimension && !empty($dimension->getHeight()) && !empty($dimension->getWidth())) {
                $response['height'] = $dimension->getHeight();
                $response['width'] = $dimension->getWidth();
                $response['exists'] = true;
            } else {
                $response['exists'] = false;
            }

            $response['codes'] = $codes;
            $response['arrivage'] = $arrivage->getNumeroArrivage();

            return new JsonResponse($response);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/litiges/api/{arrivage}", name="arrivageLitiges_api", options={"expose"=true}, methods="GET|POST")
     */
    public function apiArrivageLitiges(Request $request, Arrivage $arrivage): Response
    {
        if ($request->isXmlHttpRequest()) {

            /** @var Litige[] $litiges */
            $litiges = $this->litigeRepository->findByArrivage($arrivage);

            $rows = [];
            foreach ($litiges as $litige) {
                $rows[] = [
                    'firstDate' => $litige->getCreationDate()->format('d/m/Y'),
                    'status' => $litige->getStatus() ? $litige->getStatus()->getNom() : '',
                    'type' => $litige->getType() ? $litige->getType()->getLabel() : '',
                    'updateDate' => $litige->getUpdateDate() ? $litige->getUpdateDate()->format('d/m/Y') : '',
                    'Actions' => $this->renderView('arrivage/datatableLitigesRow.html.twig', [
                        'arrivageId' => $arrivage->getId(),
                        'url' => [
                            'edit' => $this->generateUrl('litige_api_edit', ['id' => $litige->getId()])
                        ],
                        'litigeId' => $litige->getId(),
                    ]),
                ];
            }

            $data['data'] = $rows;

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/api-modifier-litige", name="litige_api_edit", options={"expose"=true}, methods="GET|POST")
     */
    public function apiEditLitige(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {

            $litige = $this->litigeRepository->find($data['litigeId']);

            $colisCode = [];
            foreach ($litige->getColis() as $colis) {
                $colisCode[] = $colis->getId();
            }

            $arrivage = $this->arrivageRepository->find($data['arrivageId']);

            $html = $this->renderView('arrivage/modalEditLitigeContent.html.twig', [
                'litige' => $litige,
                'typesLitige' => $this->typeRepository->findByCategoryLabel(CategoryType::LITIGE),
                'statusLitige' => $this->statutRepository->findByCategorieName(CategorieStatut::LITIGE_ARR, true),
                'attachements' => $this->pieceJointeRepository->findBy(['litige' => $litige]),
                'colis' => $arrivage->getColis(),
            ]);

            return new JsonResponse(['html' => $html, 'colis' => $colisCode]);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/modifier-litige", name="litige_edit",  options={"expose"=true}, methods="GET|POST")
     */
    public function editLitige(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::LITIGE, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }
            $post = $request->request;
            $em = $this->getDoctrine()->getManager();

            $litige = $this->litigeRepository->find($post->get('id'));
            $typeBefore = $litige->getType()->getId();
            $typeBeforeName = $litige->getType()->getLabel();
            $typeAfter = (int)$post->get('typeLitige');
            $statutBefore = $litige->getStatus()->getId();
            $statutBeforeName = $litige->getStatus()->getNom();
            $statutAfter = (int)$post->get('statutLitige');
            $litige
                ->setUpdateDate(new \DateTime('now'))
                ->setType($this->typeRepository->find($post->get('typeLitige')))
                ->setStatus($this->statutRepository->find($post->get('statutLitige')));

            if (!empty($colis = $post->get('colis'))) {
                // on détache les colis existants...
                $existingColis = $litige->getColis();
                foreach ($existingColis as $coli) {
                    $litige->removeColi($coli);
                }
                // ... et on ajoute ceux sélectionnés
                $listColis = explode(',', $colis);
                foreach ($listColis as $colisId) {
                    $litige->addColi($this->colisRepository->find($colisId));
                }
            }

            $em->flush();

            $comment = '';
            $statutinstance = $this->statutRepository->find($post->get('statutLitige'));
            $commentStatut = $statutinstance->getComment();
            if ($typeBefore !== $typeAfter) {
                $comment .= "Changement du type : " . $typeBeforeName . " -> " . $litige->getType()->getLabel() . ".";
            }
            if ($statutBefore !== $statutAfter) {
                if (!empty($comment)) {
                    $comment .= "\n";
                }
                $comment .= "Changement du statut : " .
                    $statutBeforeName . " -> " . $litige->getStatus()->getNom() . "." .
                    (!empty($commentStatut) ? ("\n" . $commentStatut . ".") : '');
            }
            if ($post->get('commentaire')) {
                if (!empty($comment)) {
                    $comment .= "\n";
                }
                $comment .= trim($post->get('commentaire'));
            }

            if (!empty($comment)) {
                $histoLitige = new LitigeHistoric();
                $histoLitige
                    ->setLitige($litige)
                    ->setDate(new \DateTime('now'))
                    ->setUser($this->getUser())
                    ->setComment($comment);
                $em->persist($histoLitige);
                $em->flush();
            }

            $listAttachmentIdToKeep = $post->get('files') ?? [];

            $attachments = $litige->getAttachements()->toArray();
            foreach ($attachments as $attachment) {
                /** @var PieceJointe $attachment */
                if (!in_array($attachment->getId(), $listAttachmentIdToKeep)) {
                    $this->attachmentService->removeAndDeleteAttachment($attachment, null, $litige);
                }
            }

            $this->attachmentService->addAttachements($request, null, $litige);

            $response = $this->getResponseReloadArrivage($request->query->get('reloadArrivage'));

            return new JsonResponse($response);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/depose-pj-litige", name="litige_depose", options={"expose"=true}, methods="GET|POST")
     */
    public function deposeLitige(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            $em = $this->getDoctrine()->getManager();

            $fileNames = [];
            $path = "../public/uploads/attachements";

            $id = (int)$request->request->get('id');
            $litige = $this->litigeRepository->find($id);

            for ($i = 0; $i < count($request->files); $i++) {
                $file = $request->files->get('file' . $i);
                if ($file) {
                    if ($file->getClientOriginalExtension()) {
                        $filename = uniqid() . "." . $file->getClientOriginalExtension();
                    } else {
                        $filename = uniqid();
                    }
                    $file->move($path, $filename);

                    $pj = new PieceJointe();
                    $pj
                        ->setFileName($filename)
                        ->setOriginalName($file->getClientOriginalName())
                        ->setLitige($litige);
                    $em->persist($pj);

                    $fileNames[] = ['name' => $filename, 'originalName' => $file->getClientOriginalName()];
                }
            }
            $em->flush();

            $html = '';
            foreach ($fileNames as $fileName) {
                $html .= $this->renderView('arrivage/attachementLine.html.twig', [
                    'litige' => $litige,
                    'pjName' => $fileName['name'],
                    'originalName' => $fileName['originalName']
                ]);
            }

            return new JsonResponse($html);
        } else {
            throw new NotFoundHttpException('404');
        }
    }

    /**
     * @Route("/colis/api/{arrivage}", name="colis_api", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param Arrivage $arrivage
     * @return Response
     * @throws \Exception
     */
    public function apiColis(Request $request, Arrivage $arrivage): Response
    {
        if ($request->isXmlHttpRequest()) {
            $listColis = $arrivage->getColis()->toArray();

            $rows = [];
            foreach ($listColis as $colis) {
                /** @var $colis Colis */
                $mouvement = $this->mouvementTracaRepository->getLastByColis($colis->getCode());
                $rows[] = [
					'nature' => $colis->getNature() ? $colis->getNature()->getLabel() : '',
					'code' => $colis->getCode(),
                    'lastMvtDate' => $mouvement ? ($mouvement->getDatetime() ? $mouvement->getDatetime()->format('d/m/Y H:i') : '') : '',
                    'lastLocation' => $mouvement ? ($mouvement->getEmplacement() ? $mouvement->getEmplacement()->getLabel() : '') : '',
                    'operator' => $mouvement ? ($mouvement->getOperateur() ? $mouvement->getOperateur()->getUsername() : '') : '',
                    'actions' => $this->renderView('arrivage/datatableColisRow.html.twig', ['code' => $colis->getCode()]),
                ];
            }
            $data['data'] = $rows;

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

    private function getResponseReloadArrivage($reloadArrivageId): ?array
    {
        $response = null;
        if (isset($reloadArrivageId)) {
            $arrivageToReload = $this->arrivageRepository->find($reloadArrivageId);
            if ($arrivageToReload) {
                $response = [
                    'entete' => $this->renderView('arrivage/enteteArrivage.html.twig', [
                        'arrivage' => $arrivageToReload,
                        'canBeDeleted' => $this->arrivageRepository->countLitigesUnsolvedByArrivage($arrivageToReload) == 0,
                    ]),
                ];
            }
        }

        return $response;
    }

}
