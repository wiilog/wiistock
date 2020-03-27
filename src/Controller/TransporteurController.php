<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Chauffeur;
use App\Entity\Transporteur;
use App\Entity\Menu;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/transporteur")
 */
class TransporteurController extends AbstractController
{

	/**
	 * @var UserService
	 */
    private $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * @Route("/api", name="transporteur_api", options={"expose"=true}, methods="GET|POST")
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @return Response
     */
    public function api(EntityManagerInterface $entityManager,
                        Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::REFERENTIEL, Action::DISPLAY_TRAN)) {
                return $this->redirectToRoute('access_denied');
            }

            $transporteurRepository = $entityManager->getRepository(Transporteur::class);
            $chauffeurRepository = $entityManager->getRepository(Chauffeur::class);

            $transporteurs = $transporteurRepository->findAll();

            $rows = [];
            foreach ($transporteurs as $transporteur) {

                $rows[] = [
                    'Label' => $transporteur->getLabel() ? $transporteur->getLabel() : null,
                    'Code' => $transporteur->getCode() ? $transporteur->getCode(): null,
                    'Nombre_chauffeurs' => $chauffeurRepository->countByTransporteur($transporteur) ,
                    'Actions' => $this->renderView('transporteur/datatableTransporteurRow.html.twig', [
                        'transporteur' => $transporteur
                    ]),
                ];
            }
            $data['data'] = $rows;

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/", name="transporteur_index", methods={"GET"})
     * @param EntityManagerInterface $entityManager
     * @return Response
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
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @return Response
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function new(EntityManagerInterface $entityManager,
                        Request $request): Response
    {
		if (!$this->userService->hasRightFunction(Menu::REFERENTIEL, Action::CREATE)) {
			return $this->redirectToRoute('access_denied');
		}

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
     * @Route("/api-modifier", name="transporteur_edit_api", options={"expose"=true}, methods="GET|POST")
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @return Response
     */
    public function editApi(EntityManagerInterface $entityManager,
                            Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::REFERENTIEL, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $transporteurRepository = $entityManager->getRepository(Transporteur::class);
            $transporteur = $transporteurRepository->find($data['id']);

            $json = $this->renderView('transporteur/modalEditTransporteurContent.html.twig', [
                'transporteur' => $transporteur,
            ]);

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/modifier", name="transporteur_edit", options={"expose"=true}, methods={"GET","POST"})
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @return Response
     */
    public function edit(EntityManagerInterface $entityManager,
                         Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::REFERENTIEL, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $transporteurRepository = $entityManager->getRepository(Transporteur::class);
            $transporteur = $transporteurRepository->find($data['id']);

            $transporteur
                ->setLabel($data['label'])
                ->setCode($data['code']);
            $entityManager->flush();

            return new JsonResponse();
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/supprimer", name="transporteur_delete", options={"expose"=true}, methods={"GET","POST"})
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @return Response
     */
    public function delete(EntityManagerInterface $entityManager,
                           Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::REFERENTIEL, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }

            $transporteurRepository = $entityManager->getRepository(Transporteur::class);
            $transporteur = $transporteurRepository->find($data['transporteur']);

            $entityManager->remove($transporteur);
            $entityManager->flush();
            return new JsonResponse();
        }

        throw new NotFoundHttpException("404");
    }
}
