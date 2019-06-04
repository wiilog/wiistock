<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Arrivage;
use App\Entity\Menu;
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
     * @Route("/", name="arrivage_index")
     */
    public function index()
    {
//        if (!$this->userService->hasRightFunction(Menu::ARRIVAGE, Action::LIST)) {
//            return $this->redirectToRoute('access_denied');
//        }

        return $this->render('arrivage/depose.html.twig');
//        return $this->render('arrivage/index.html.twig', [
////            'utilisateurs' => $this->utilisateurRepository->findAll(),
////            'statuts' => $this->statutRepository->findByCategorieName(Service::CATEGORIE),
//
//        ]);
    }

    /**
     * @Route("/depose-pj", name="arrivage_depose", options={"expose"=true}, methods="GET|POST")
     */
    public function depose(Request $request, Arrivage $arrivage): Response
    {
        if ($request->isXmlHttpRequest()) {
            $em = $this->getDoctrine()->getManager();
            for ($i = 0; $i < count($request->files); $i++) {
                $file = $request->files->get('file' . $i);
                if ($file) {
                    // generate a random name for the file but keep the extension
                    $filename = uniqid() . "." . $file->getClientOriginalExtension();
                    $path = "../public/uploads/pieces-jointes";
                    $file->move($path, $filename); // move the file to a path
                    $arrivage->addPiecesJointes($filename);
                }
            }
            $em->flush();
            return new JsonResponse();
        } else {
            throw new NotFoundHttpException('404');
        }
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
