<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Emplacement;
use App\Entity\LocationGroup;
use App\Entity\Menu;
use App\Service\LocationGroupService;

use Doctrine\ORM\EntityManagerInterface;
use App\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/emplacements/groupes")
 */
class LocationGroupController extends AbstractController {

    /**
     * @Route("/api", name="location_group_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::REFERENTIEL, Action::DISPLAY_EMPL}, mode=HasPermission::IN_JSON)
     */
    public function api(Request $request, LocationGroupService $groupService): Response {
        return $this->json($groupService->getDataForDatatable($request->request));
    }

    /**
     * @Route("/creer", name="location_group_new", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::REFERENTIEL, Action::CREATE}, mode=HasPermission::IN_JSON)
     */
    public function new(Request $request, EntityManagerInterface $manager): Response {
        $data = json_decode($request->getContent(), true);

        $locationRepository = $manager->getRepository(Emplacement::class);
        $locationGroupRepository = $manager->getRepository(LocationGroup::class);

        $sameLabel = $locationGroupRepository->findOneBy(["label" => $data["label"]]);
        $sameLocationLabel = $locationRepository->findOneBy(["label" => $data["label"]]);
        if ($sameLabel) {
            return $this->json([
                "success" => false,
                "msg" => "Un groupe d'emplacements avec le même nom existe déjà",
            ]);
        } elseif ($sameLocationLabel) {
            return $this->json([
                "success" => false,
                "msg" => "Un emplacement avec le même nom existe déjà",
            ]);
        }

        $locations = $locationRepository->findBy(["id" => $data["locations"]]);

        $group = (new LocationGroup())
            ->setLabel($data["label"])
            ->setDescription($data["description"] ?? null)
            ->setActive($data["active"])
            ->setLocations($locations);

        $manager->persist($group);
        $manager->flush();

        return $this->json([
            "success" => true,
            "msg" => "Le groupe d'emplacements {$group->getLabel()} a bien été créé",
        ]);
    }

    /**
     * @Route("/api-modifier", name="location_group_edit_api", options={"expose"=true}, methods="GET|POST")
     * @HasPermission({Menu::REFERENTIEL, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function editApi(Request $request, EntityManagerInterface $manager): Response {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $locationGroupRepository = $manager->getRepository(LocationGroup::class);
            $group = $locationGroupRepository->find($data['id']);

            return $this->json($this->renderView("location_group/edit_content.html.twig", [
                'group' => $group
            ]));
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/modifier", name="location_group_edit", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::REFERENTIEL, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function edit(Request $request, EntityManagerInterface $manager): Response {
        $data = json_decode($request->getContent(), true);

        $locationRepository = $manager->getRepository(Emplacement::class);
        $locationGroupRepository = $manager->getRepository(LocationGroup::class);

        $group = $locationGroupRepository->find($data["id"]);
        if ($group) {
            $locations = $locationRepository->findBy(["id" => $data["locations"]]);

            $group->setLabel($data["label"])
                ->setDescription($data["description"] ?? null)
                ->setActive($data["active"])
                ->setLocations($locations);

            $manager->flush();

            return $this->json([
                "success" => true,
                "msg" => "Le groupe d'emplacements {$group->getLabel()} a bien été modifié",
            ]);
        }

        throw new NotFoundHttpException();
    }

    /**
     * @Route("/api-supprimer", name="location_group_delete_api", options={"expose"=true}, methods="GET|POST")
     * @HasPermission({Menu::TRACA, Action::DELETE}, mode=HasPermission::IN_JSON)
     */
    public function deleteApi(Request $request, EntityManagerInterface $manager): Response {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $packRepository = $manager->getRepository(LocationGroup::class);
            $group = $packRepository->find($data["id"]);

            return $this->json($this->renderView("location_group/delete_content.html.twig", [
                "group" => $group
            ]));
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/supprimer", name="location_group_delete", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::TRACA, Action::DELETE}, mode=HasPermission::IN_JSON)
     */
    public function delete(Request $request, EntityManagerInterface $manager): Response {
        $data = json_decode($request->getContent(), true);

        $locationGroupRepository = $manager->getRepository(LocationGroup::class);

        $group = $locationGroupRepository->find($data["id"]);

        if(!$group->getUsers()->isEmpty()) {
            return $this->json([
                "success" => false,
                "msg" => "Ce groupe d'emplacements est utilisé en tant que dropzone sur un ou plusieurs utilisateurs, vous ne pouvez pas le supprimer.",
            ]);
        } else {
            $manager->remove($group);
            $manager->flush();

            return $this->json([
                "success" => true,
                "msg" => "Groupe d'emplacements supprimé avec succès",
            ]);
        }
    }

}
