<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Arrivage;
use App\Entity\ArticleFournisseur;
use App\Entity\Fournisseur;
use App\Entity\Menu;
use App\Entity\Reception;
use App\Entity\ReceptionReferenceArticle;
use App\Service\UserService;
use App\Service\FournisseurDataService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @Route("/fournisseur")
 */
class FournisseurController extends AbstractController
{

    /**
     * @var FournisseurDataService
     */
    private $fournisseurDataService;

    /**
     * @var UserService
     */
    private $userService;

    public function __construct(FournisseurDataService $fournisseurDataService,
                                UserService $userService) {
        $this->fournisseurDataService = $fournisseurDataService;
        $this->userService = $userService;
    }

    /**
     * @Route("/api", name="fournisseur_api", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::REFERENTIEL, Action::DISPLAY_FOUR}, mode=HasPermission::IN_JSON)
     */
    public function api(Request $request): Response
    {
        $data = $this->fournisseurDataService->getFournisseurDataByParams($request->request);

        return new JsonResponse($data);
    }

    /**
     * @Route("/", name="fournisseur_index", methods="GET")
     * @HasPermission({Menu::REFERENTIEL, Action::DISPLAY_FOUR})
     */
    public function index(EntityManagerInterface $entityManager): Response
    {
        $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);

        return $this->render('fournisseur/index.html.twig', [
            'fournisseur' => $fournisseurRepository->findAll()
        ]);
    }

    /**
     * @Route("/creer", name="fournisseur_new", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::REFERENTIEL, Action::CREATE}, mode=HasPermission::IN_JSON)
     */
    public function new(Request $request,
                        EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
			// unicité du code fournisseur
            $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);
            $codeAlreadyUsed = intval($fournisseurRepository->countByCode($data['Code']));

			if ($codeAlreadyUsed) {
				return new JsonResponse([
					'success' => false,
					'msg' => "Ce code fournisseur est déjà utilisé.",
				]);
			}

            $fournisseur = new Fournisseur();
            $fournisseur
				->setNom($data["Nom"])
				->setCodeReference($data["Code"]);
            $entityManager->persist($fournisseur);
            $entityManager->flush();

			return new JsonResponse([
			    'success' => true,
                'id' => $fournisseur->getId(),
                'text' => $fournisseur->getCodeReference()
            ]);
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/api-modifier", name="fournisseur_api_edit", options={"expose"=true},  methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::REFERENTIEL, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function apiEdit(Request $request,
                            EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);

            $fournisseur = $fournisseurRepository->find($data['id']);
            $json = $this->renderView('fournisseur/modalEditFournisseurContent.html.twig', [
                'fournisseur' => $fournisseur,
            ]);
            return new JsonResponse($json);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/modifier", name="fournisseur_edit",  options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::REFERENTIEL, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function edit(Request $request,
                         EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);
            $fournisseur = $fournisseurRepository->find($data['id']);
            $fournisseur
                ->setNom($data['nom'])
                ->setCodeReference($data['codeReference']);
            $em = $this->getDoctrine()->getManager();
            $em->flush();
            return new JsonResponse();
        }
        throw new BadRequestHttpException();
    }


    /**
     * @Route("/verification", name="fournisseur_check_delete", options={"expose"=true}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::REFERENTIEL, Action::DISPLAY_FOUR}, mode=HasPermission::IN_JSON)
     */
    public function checkFournisseurCanBeDeleted(Request $request): Response
    {
        if ($fournisseurId = json_decode($request->getContent(), true)) {
            $isUsedBy = $this->isFournisseurUsed($fournisseurId);

            if (empty($isUsedBy)) {
                $delete = true;
                $html = $this->renderView('fournisseur/modalDeleteFournisseurRight.html.twig');
            } else {
                $delete = false;
                $html = $this->renderView('fournisseur/modalDeleteFournisseurWrong.html.twig', [
                	'delete' => false,
					'isUsedBy' => $isUsedBy
				]);
            }

            return new JsonResponse(['delete' => $delete, 'html' => $html]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @param int $fournisseurId
     * @return array
     */
    private function isFournisseurUsed($fournisseurId)
    {
    	$usedBy = [];
        $articleFournisseurRepository = $this->getDoctrine()->getRepository(ArticleFournisseur::class);
        $receptionReferenceArticleRepository = $this->getDoctrine()->getRepository(ReceptionReferenceArticle::class);
        $arrivageRepository = $this->getDoctrine()->getRepository(Arrivage::class);
        $receptionRepository = $this->getDoctrine()->getRepository(Reception::class);

        $AF = $articleFournisseurRepository->countByFournisseur($fournisseurId);
    	if ($AF > 0) $usedBy[] = 'articles fournisseur';

    	$receptions = $receptionRepository->countByFournisseur($fournisseurId);
    	if ($receptions > 0) $usedBy[] = 'réceptions';

		$ligneReceptions = $receptionReferenceArticleRepository->countByFournisseurId($fournisseurId);
		if ($ligneReceptions > 0) $usedBy[] = 'lignes réception';

		$arrivages = $arrivageRepository->countByFournisseur($fournisseurId);
		if ($arrivages > 0) $usedBy[] = 'arrivages';

        return $usedBy;
    }


    /**
     * @Route("/supprimer", name="fournisseur_delete",  options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::REFERENTIEL, Action::DELETE}, mode=HasPermission::IN_JSON)
     */
    public function delete(Request $request,
                           EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);
            $fournisseurId = $data['fournisseur'] ?? null;
            if ($fournisseurId) {
                $fournisseur = $fournisseurRepository->find($fournisseurId);

                // on vérifie que le fournisseur n'est plus utilisé
                $usedFournisseur = $this->isFournisseurUsed($fournisseurId);

                if (!empty($usedFournisseur)) {
                    return new JsonResponse(false);
                }

                $entityManager->remove($fournisseur);
                $entityManager->flush();
            }
            return new JsonResponse();
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/autocomplete", name="get_fournisseur", options={"expose"=true}, condition="request.isXmlHttpRequest()")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return JsonResponse
     */
    public function getFournisseur(Request $request,
                                   EntityManagerInterface $entityManager)
    {
        $search = $request->query->get('term');

        $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);
        $fournisseur = $fournisseurRepository->getIdAndCodeBySearch($search);

        return new JsonResponse(['results' => $fournisseur]);
    }

    /**
     * @Route("get-label-fournisseur", name ="demande_label_by_fournisseur", options={"expose"=true}, condition="request.isXmlHttpRequest()")
     *
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function getLabelsFournisseurs(Request $request, EntityManagerInterface $entityManager): Response
    {
        $search = $request->query->get('term');
        $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);

        $fournisseurs = $fournisseurRepository->getIdAndLabelseBySearch($search);
        return new JsonResponse([
            'results' => $fournisseurs
        ]);
    }
}
