<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\CategoryType;
use App\Entity\Emplacement;
use App\Entity\FiltreRef;
use App\Entity\Menu;
use App\Entity\Role;
use App\Entity\Type;
use App\Entity\Utilisateur;

use App\Entity\VisibilityGroup;
use App\Service\CSVExportService;
use App\Service\PasswordService;
use App\Service\UserService;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use WiiCommon\Helper\Stream;


/**
 * TODO WIIS-6693 delete
 * @Route("/admin/utilisateur")
 */
class UserController extends AbstractController
{

    /**
     * @Route("/autocomplete", name="get_user", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function getUserAutoComplete(Request $request,
                                        EntityManagerInterface $entityManager): Response
    {
        $search = $request->query->get('term');

        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
        $results = $utilisateurRepository->getIdAndLibelleBySearch($search);
        return new JsonResponse(['results' => $results]);
    }

    /**
     * @Route("/recherches", name="update_user_searches", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function updateSearches(Request $request,
                                   EntityManagerInterface $entityManager) {
        $data = $request->get("searches");
        if ($data && is_array($data)) {
            /** @var Utilisateur $currentUser */
            $currentUser = $this->getUser();
            $currentUser->setRecherche($data);

            $entityManager->flush();
            $res = [
                "success" => true,
                "msg" => "Recherche rapide sauvegardée avec succès."
            ];
        }
        else {
            $res = [
                "success" => false,
                "msg" => "Vous devez sélectionner au moins un champ."
            ];
        }
        return $this->json($res);
    }

    /**
     * @Route("/recherchesArticle", name="update_user_searches_for_article", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function updateSearchesArticle(Request $request, EntityManagerInterface $entityManager) {
        if ($data = $request->request->get("searches")) {
            /** @var Utilisateur $user */
            $user = $this->getUser();

            $user->setRechercheForArticle($data);
            $entityManager->flush();

            return $this->json([
                "success" => true
            ]);
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/taille-page-arrivage", name="update_user_page_length_for_arrivage", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function updateUserPageLengthForArrivage(Request $request)
    {
        if ($data = json_decode($request->getContent(), true)) {
            /** @var Utilisateur $user */
            $user = $this->getUser();
            $user->setPageLengthForArrivage($data);
            $em = $this->getDoctrine()->getManager();
            $em->flush();
        }
        return new JsonResponse();
    }


    /**
     * @Route("/set-columns-order", name="set_columns_order", methods="POST", options={"expose"=true}, condition="request.isXmlHttpRequest()")
     */
    public function setColumnsOrder(Request $request, EntityManagerInterface $manager): JsonResponse {
        $data = $request->request->all();

        /** @var Utilisateur $loggedUser */
        $loggedUser = $this->getUser();

        $columnsOrder = $loggedUser->getColumnsOrder();
        $columnsOrder[$data['page']] = $data['order'];

        $loggedUser->setColumnsOrder($columnsOrder);

        $manager->flush();

        return $this->json([
            'success' => true
        ]);
    }

    /**
     * @Route("/get-columns-order", name="get_columns_order", methods="GET", options={"expose"=true}, condition="request.isXmlHttpRequest()")
     */
    public function getColumnsOrder(Request $request): JsonResponse {
        $page = $request->query->get('page');

        /** @var Utilisateur $loggedUser */
        $loggedUser = $this->getUser();

        $columnsOrder = $loggedUser->getColumnsOrder();

        return $this->json([
            'success' => true,
            'order' => $columnsOrder[$page] ?? []
        ]);
    }

}
