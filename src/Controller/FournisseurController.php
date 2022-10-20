<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Fournisseur;
use App\Entity\Menu;
use App\Service\CSVExportService;
use App\Service\FournisseurDataService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @Route("/fournisseur")
 */
class FournisseurController extends AbstractController {

    /**
     * @Route("/api", name="supplier_api", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::REFERENTIEL, Action::DISPLAY_FOUR}, mode=HasPermission::IN_JSON)
     */
    public function api(Request $request,
                        FournisseurDataService $fournisseurDataService): Response
    {
        $data = $fournisseurDataService->getFournisseurDataByParams($request->request);

        return $this->json($data);
    }

    /**
     * @Route("/", name="supplier_index", methods="GET")
     * @HasPermission({Menu::REFERENTIEL, Action::DISPLAY_FOUR})
     */
    public function index(): Response
    {
        return $this->render('fournisseur/index.html.twig');
    }

    /**
     * @Route("/creer", name="supplier_new", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::REFERENTIEL, Action::CREATE}, mode=HasPermission::IN_JSON)
     */
    public function new(Request $request,
                        EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);
            $codeAlreadyUsed = intval($fournisseurRepository->countByCode($data['code']));

			if ($codeAlreadyUsed) {
				return $this->json([
					'success' => false,
					'msg' => "Ce code fournisseur est déjà utilisé.",
				]);
			}

            $supplier = (new Fournisseur())
				->setNom($data["name"])
				->setCodeReference($data["code"])
                ->setPossibleCustoms($data["possibleCustoms"] ?? false)
                ->setUrgent($data["urgent"] ?? false);

            $entityManager->persist($supplier);
            $entityManager->flush();

			return $this->json([
			    'success' => true,
                'id' => $supplier->getId(),
                'text' => $supplier->getCodeReference()
            ]);
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/api-modifier", name="supplier_api_edit", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
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
     * @Route("/modifier", name="supplier_edit",  options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
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
                ->setPossibleCustoms($data['possibleCustoms'])
                ->setUrgent($data['urgent']);

            $entityManager->flush();
            return $this->json([
                'success' => true,
                'msg' => "Le fournisseur a bien été modifié"
            ]);
        }
        throw new BadRequestHttpException();
    }


    /**
     * @Route("/verification", name="supplier_check_delete", options={"expose"=true}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::REFERENTIEL, Action::DISPLAY_FOUR}, mode=HasPermission::IN_JSON)
     */
    public function checkSupplierCanBeDeleted(Request $request, EntityManagerInterface $entityManager): Response
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

            return $this->json(['delete' => $delete, 'html' => $html]);
        }
        throw new BadRequestHttpException();
    }

    private function isSupplierUsed(int $supplierId, EntityManagerInterface $entityManager): array
    {
    	$usedBy = [];
        $supplier = $entityManager->find(Fournisseur::class, $supplierId);
    	if (!$supplier->getArticlesFournisseur()->isEmpty()) {
            $usedBy[] = 'articles fournisseur';
        }

    	if (!$supplier->getReceptions()->isEmpty()) {
            $usedBy[] = 'réceptions';
        }

		if (!$supplier->getReceptionReferenceArticles()->isEmpty()) {
            $usedBy[] = 'lignes de réception';
        }

		if (!$supplier->getArrivages()->isEmpty()) {
            $usedBy[] = 'arrivages';
        }

        if (!$supplier->getEmergencies()->isEmpty()) {
            $usedBy[] = 'urgences';
        }

        return $usedBy;
    }


    /**
     * @Route("/supprimer", name="supplier_delete",  options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
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
     */
    public function getFournisseur(Request $request,
                                   EntityManagerInterface $entityManager): Response
    {
        $search = $request->query->get('term');

        $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);
        $fournisseur = $fournisseurRepository->getIdAndCodeBySearch($search);

        return $this->json(['results' => $fournisseur]);
    }

    /**
     * @Route("/get-label-fournisseur", name ="demande_label_by_fournisseur", options={"expose"=true})
     */
    public function getLabelsFournisseurs(Request $request, EntityManagerInterface $entityManager): Response
    {
        $search = $request->query->get('term');
        $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);

        $fournisseurs = $fournisseurRepository->getIdAndLabelseBySearch($search);
        return $this->json([
            'results' => $fournisseurs
        ]);
    }

    /**
     * @Route("/export", name="get_suppliers_csv", options={"expose"=true}, methods="GET")
     * @HasPermission({Menu::REFERENTIEL, Action::EXPORT})
     */
    public function export(EntityManagerInterface $manager,
                           CSVExportService $csvService): Response
    {
        $now = (new DateTime())->format("d-m-Y-H-i-s");

        $headers = [
            'Nom',
            'Code',
            'Possible douane',
            'Urgent'
        ];

        return $csvService->streamResponse(function ($output) use ($manager, $csvService) {
            $suppliers = $manager->getRepository(Fournisseur::class)->getForExport();

            foreach ($suppliers as $supplier) {
                $csvService->putLine($output, $supplier->serialize());
            }
        }, "fournisseurs_$now.csv", $headers);
    }
}
