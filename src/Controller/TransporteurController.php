<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Chauffeur;
use App\Entity\Transporteur;
use App\Entity\Menu;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/transporteur")
 */
class TransporteurController extends AbstractController
{

    /**
     * @Route("/api", name="transporteur_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::REFERENTIEL, Action::DISPLAY_TRAN}, mode=HasPermission::IN_JSON)
     */
    public function api(EntityManagerInterface $entityManager): Response
    {
        $transporteurRepository = $entityManager->getRepository(Transporteur::class);
        $chauffeurRepository = $entityManager->getRepository(Chauffeur::class);

        $transporteurs = $transporteurRepository->findAll();

        $rows = [];
        foreach ($transporteurs as $transporteur) {

            $rows[] = [
                'Label' => $transporteur->getLabel() ?: null,
                'Code' => $transporteur->getCode() ?: null,
                'Nombre_chauffeurs' => $chauffeurRepository->countByTransporteur($transporteur) ,
                'Actions' => $this->renderView('transporteur/datatableTransporteurRow.html.twig', [
                    'transporteur' => $transporteur
                ]),
            ];
        }
        $data['data'] = $rows;

        return new JsonResponse($data);
    }

    /**
     * @Route("/", name="transporteur_index", methods={"GET"})
     */
    public function index(EntityManagerInterface $entityManager): Response
    {
        $transporteurRepository = $entityManager->getRepository(Transporteur::class);
        return $this->render('transporteur/index.html.twig', [
            'transporteurs' => $transporteurRepository->findAll(),
        ]);
    }

    /**
     * @Route("/creer", name="transporteur_new", options={"expose"=true}, methods={"GET","POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::REFERENTIEL, Action::CREATE}, mode=HasPermission::IN_JSON)
     */
    public function new(EntityManagerInterface $entityManager,
                        Request $request): Response
    {
        $transporteurRepository = $entityManager->getRepository(Transporteur::class);

        $data = json_decode($request->getContent(), true);

		$code = $data['code'];
		$label = $data['label'];

		// unicité du code et du nom transporteur
		$codeAlreadyUsed = intval($transporteurRepository->countByCode($code));
		$labelAlreadyUsed = intval($transporteurRepository->countByLabel($label));

		if ($codeAlreadyUsed + $labelAlreadyUsed) {
			$msg = 'Ce ' . ($codeAlreadyUsed ? 'code ' : 'nom ') . 'de transporteur est déjà utilisé.';
			return new JsonResponse([
				'success' => false,
				'msg' => $msg,
			]);
		}

		$transporteur = new Transporteur();
		$transporteur
			->setLabel($label)
			->setCode($code);
		$entityManager->persist($transporteur);
		$entityManager->flush();

		return new JsonResponse([
			'success' => true,
			'id' => $transporteur->getId(),
			'text' => $transporteur->getLabel()
		]);
    }

    /**
     * @Route("/api-modifier", name="transporteur_edit_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::REFERENTIEL, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function editApi(EntityManagerInterface $entityManager,
                            Request $request): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $transporteurRepository = $entityManager->getRepository(Transporteur::class);
            $transporteur = $transporteurRepository->find($data['id']);

            $json = $this->renderView('transporteur/modalEditTransporteurContent.html.twig', [
                'transporteur' => $transporteur,
            ]);

            return new JsonResponse($json);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/modifier", name="transporteur_edit", options={"expose"=true}, methods={"GET","POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::REFERENTIEL, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function edit(EntityManagerInterface $entityManager,
                         Request $request): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $transporteurRepository = $entityManager->getRepository(Transporteur::class);
            $transporteur = $transporteurRepository->find($data['id']);

            $transporteur
                ->setLabel($data['label'])
                ->setCode($data['code']);
            $entityManager->flush();

            return new JsonResponse();
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/supprimer", name="transporteur_delete", options={"expose"=true}, methods={"GET","POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::REFERENTIEL, Action::DELETE}, mode=HasPermission::IN_JSON)
     */
    public function delete(EntityManagerInterface $entityManager,
                           Request $request): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $transporteurRepository = $entityManager->getRepository(Transporteur::class);
            $transporteur = $transporteurRepository->find($data['transporteur']);

            if(!$transporteur->getEmergencies()->isEmpty()) {
                return $this->json([
                    'success' => false,
                    'msg' => "Ce transporteur est lié à une ou plusieurs urgences, vous ne pouvez pas le supprimer"
                ]);
            }

            $entityManager->remove($transporteur);
            $entityManager->flush();

            $name = $transporteur->getLabel();
            return $this->json([
                'success' => true,
                'msg' => "Le transporteur $name a bien été créé"
            ]);
        }

        throw new BadRequestHttpException();
    }
}
