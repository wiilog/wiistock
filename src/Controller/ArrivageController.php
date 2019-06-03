<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Menu;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/arrivage")
 */
class ArrivageController extends AbstractController
{
    /**
     * @Route("/", name="arrivage_index")
     */
    public function index()
    {
        if (!$this->userService->hasRightFunction(Menu::ARRIVAGE, Action::LIST)) {
            return $this->redirectToRoute('access_denied');
        }

        return $this->render('arrivage/index.html.twig', [
//            'utilisateurs' => $this->utilisateurRepository->findAll(),
//            'statuts' => $this->statutRepository->findByCategorieName(Service::CATEGORIE),

        ]);
    }

//    /**
//     * @Route("/api", name="arrivage_api", options={"expose"=true}, methods="GET|POST")
//     */
//    public function api(Request $request): Response
//    {
//        if ($request->isXmlHttpRequest()) {
//            if (!$this->userService->hasRightFunction(Menu::MANUT, Action::LIST)) {
//                return $this->redirectToRoute('access_denied');
//            }
//
//            $services = $this->serviceRepository->findAll();
//
//            $rows = [];
//            foreach ($services as $service) {
//                $url['edit'] = $this->generateUrl('service_edit', ['id' => $service->getId()]);
//
//                $rows[] = [
//                    'id' => ($service->getId() ? $service->getId() : 'Non défini'),
//                    'Date' => ($service->getDate() ? $service->getDate()->format('d/m/Y') : null),
//                    'Demandeur' => ($service->getDemandeur() ? $service->getDemandeur()->getUserName() : null),
//                    'Libellé' => ($service->getlibelle() ? $service->getLibelle() : null),
//                    'Statut' => ($service->getStatut()->getNom() ? $service->getStatut()->getNom() : null),
//                    'Actions' => $this->renderView('service/datatableServiceRow.html.twig', [
//                        'url' => $url,
//                        'service' => $service,
//                        'idService' => $service->getId(),
//                    ]),
//                ];
//            }
//            $data['data'] = $rows;
//
//            return new JsonResponse($data);
//        }
//        throw new NotFoundHttpException('404');
//    }

}
