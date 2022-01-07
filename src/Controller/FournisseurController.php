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

    /** @Required */
    public FournisseurDataService $fournisseurDataService;

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
    public function index(): Response
    {
        return $this->render('fournisseur/index.html.twig');
    }

    /**
     * @Route("/creer", name="fournisseur_new", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::REFERENTIEL, Action::CREATE}, mode=HasPermission::IN_JSON)
     */
    public function new(Request $request,
                        EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);
            $codeAlreadyUsed = intval($fournisseurRepository->countByCode($data['code']));

			if ($codeAlreadyUsed) {
				return new JsonResponse([
					'success' => false,
					'msg' => "Ce code fournisseur est déjà utilisé.",
				]);
			}

            $supplier = (new Fournisseur())
				->setNom($data["name"])
				->setCodeReference($data["code"])
                ->setIsPossibleCustoms($data["isPossibleCustoms"])
                ->setIsUrgent($data["isPossibleCustoms"]);

            $entityManager->persist($supplier);
            $entityManager->flush();

			return new JsonResponse([
			    'success' => true,
                'id' => $supplier->getId(),
                'text' => $supplier->getCodeReference()
            ]);
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/api-modifier", name="fournisseur_api_edit", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::REFERENTIEL, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function apiEdit(Request $request,
                            EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $supplier = $entityManager->find(Fournisseur::class, $data['id']);
            $json = $this->renderView('fournisseur/modalEditFournisseurContent.html.twig', [
                'supplier' => $supplier,
            ]);
            return $this->json($json);
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
            $supplier = $entityManager->find(Fournisseur::class, $data['id']);
            $supplier
                ->setNom($data['name'])
                ->setCodeReference($data['code'])
                ->setIsPossibleCustoms($data['isPossibleCustoms'])
                ->setIsUrgent($data['isUrgent']);

            $entityManager->flush();
            return $this->json([
                'success' => true,
                'msg' => "Le fournisseur a bien été modifié"
            ]);
        }
        throw new BadRequestHttpException();
    }


    /**
     * @Route("/verification", name="fournisseur_check_delete", options={"expose"=true}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::REFERENTIEL, Action::DISPLAY_FOUR}, mode=HasPermission::IN_JSON)
     */
    public function checkFournisseurCanBeDeleted(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($fournisseurId = json_decode($request->getContent(), true)) {
            $isUsedBy = $this->isSupplierUsed($fournisseurId, $entityManager);

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
     * @param int $supplierId
     * @return array
     */
    private function isSupplierUsed(int $supplierId, EntityManagerInterface $entityManager): array
    {
    	$usedBy = [];
        $articleFournisseurRepository = $entityManager->getRepository(ArticleFournisseur::class);
        $receptionReferenceArticleRepository = $entityManager->getRepository(ReceptionReferenceArticle::class);
        $arrivageRepository = $entityManager->getRepository(Arrivage::class);
        $receptionRepository = $entityManager->getRepository(Reception::class);

        $AF = $articleFournisseurRepository->countByFournisseur($supplierId);
    	if ($AF > 0) $usedBy[] = 'articles fournisseur';

    	$receptions = $receptionRepository->countByFournisseur($supplierId);
    	if ($receptions > 0) $usedBy[] = 'réceptions';

		$ligneReceptions = $receptionReferenceArticleRepository->countByFournisseurId($supplierId);
		if ($ligneReceptions > 0) $usedBy[] = 'lignes réception';

		$arrivages = $arrivageRepository->countByFournisseur($supplierId);
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
            $supplierId = $data['fournisseur'] ?? null;
            if ($supplierId) {
                $supplier = $entityManager->find(Fournisseur::class, $supplierId);

                $usedFournisseur = $this->isSupplierUsed($supplierId, $entityManager);

                if (!empty($usedFournisseur)) {
                    return $this->json(false);
                }

                $entityManager->remove($supplier);
                $entityManager->flush();
            }
            return $this->json([
                'success' => true,
                'msg' => "Le fournisseur a bien été supprimé"
            ]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/autocomplete", name="get_fournisseur", options={"expose"=true})
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
     * @Route("/get-label-fournisseur", name ="demande_label_by_fournisseur", options={"expose"=true})
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
