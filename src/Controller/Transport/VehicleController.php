<?php

namespace App\Controller\Transport;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Emplacement;
use App\Entity\Menu;
use App\Entity\Transport\Vehicle;
use App\Entity\Utilisateur;
use App\Service\Transport\VehicleService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/vehicle')]
class VehicleController extends AbstractController
{

    #[Route('/liste', name: 'vehicle_index', options: ['expose' => true], methods: 'GET')]
    //TODO Ajouter HasPermission
    public function index(): Response
    {
        return $this->render('vehicle/index.html.twig', [
            'newVehicle' => new Vehicle(),
        ]);
    }

    #[Route('/api', name: 'vehicle_api', options: ['expose' => true], methods: 'POST', condition: 'request.isXmlHttpRequest()')]
    //TODO Ajouter HasPermission
    public function api(Request $request, VehicleService $vehicleService): Response
    {
        $data = $vehicleService->getDataForDatatable($request->request);

        return $this->json($data);
    }

    #[Route('/new', name: 'vehicle_new', options: ['expose' => true], methods: 'POST', condition: 'request.isXmlHttpRequest()')]
    public function new(Request $request, EntityManagerInterface $manager): Response
    {
        $data = json_decode($request->getContent(), true);

        $registration = $data['registration'];
        $existing = $manager->getRepository(Vehicle::class)->findOneBy(['registration' => $registration]);

        if ($existing) {
            return $this->json([
                'success' => false,
                'msg' => 'Un véhicule avec cette immatriculation existe déjà'
            ]);
        } else {
            $deliverer = $manager->find(Utilisateur::class, $data['deliverer']);
            $locations = $manager->getRepository(Emplacement::class)->findBy(['id' => $data['locations']]);
            $vehicle = (new Vehicle())
                ->setRegistration($registration)
                ->setDeliverer($deliverer)
                ->setLocations($locations);

            $manager->persist($vehicle);
            $manager->flush();

            return $this->json([
                'success' => true,
                'msg' => 'Le véhicule a bien été créé'
            ]);
        }
    }

    #[Route('/edit-api', name: 'vehicle_edit_api', options: ['expose' => true], methods: 'POST', condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::REFERENTIEL, Action::EDIT], mode: HasPermission::IN_JSON)]
    public function editApi(EntityManagerInterface $manager,
                            Request                $request): Response
    {
        $data = json_decode($request->getContent(), true);
        $vehicle = $manager->find(Vehicle::class, $data['id']);

        $content = $this->renderView('vehicle/modal/form.html.twig', [
            'vehicle' => $vehicle,
        ]);
        return $this->json($content);
    }

    #[Route('/edit', name: 'vehicle_edit', options: ['expose' => true], methods: 'POST', condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::REFERENTIEL, Action::EDIT], mode: HasPermission::IN_JSON)]
    public function edit(Request $request, EntityManagerInterface $manager)
    {
        $data = json_decode($request->getContent(), true);
        $vehicle = $manager->find(Vehicle::class, $data['id']);

        $registration = $data['registration'];
        $existing = $manager->getRepository(Vehicle::class)->findOneBy(['registration' => $registration]);
        if ($existing && $existing !== $vehicle) {
            return $this->json([
                'success' => false,
                'msg' => 'Un véhicule avec cette immatriculation existe déjà'
            ]);
        } else {
            $deliverer = $manager->find(Utilisateur::class, $data['deliverer']);
            $locations = $manager->getRepository(Emplacement::class)->findBy(['id' => $data['locations']]);
            $vehicle
                ->setRegistration($registration)
                ->setDeliverer($deliverer)
                ->setLocations($locations);

            $manager->flush();

            return $this->json([
                'success' => true,
                'msg' => 'Le véhicule a bien été modifié'
            ]);
        }
    }

    #[Route('/delete-check', name: 'vehicle_check_delete', options: ['expose' => true], methods: 'POST', condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::REFERENTIEL, Action::DELETE], mode: HasPermission::IN_JSON)]
    public function checkVehicleCanBeDeleted(Request $request, EntityManagerInterface $manager): Response
    {
        $id = json_decode($request->getContent(), true);
        $vehicle = $manager->find(Vehicle::class, $id);

        $isPaired = !$vehicle->getPairings()->isEmpty();

        return $this->json([
            'delete' => !$isPaired,
            'html' => !$isPaired
                ? '<span>Voulez-vous réellement supprimer ce véhicule ?</span>'
                : '<span>Ce véhicule est lié à une ou plusieurs associations IoT, vous ne pouvez pas le supprimer</span>'
        ]);
    }

    #[Route('/delete', name: 'vehicle_delete', options: ['expose' => true], methods: 'POST', condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::REFERENTIEL, Action::DELETE], mode: HasPermission::IN_JSON)]
    public function delete(Request $request, EntityManagerInterface $manager): Response
    {
        $data = json_decode($request->getContent(), true);
        $vehicle = $manager->find(Vehicle::class, $data['id']);

        /*$vehicle->setLocations([]);*/

        $manager->remove($vehicle);
        $manager->flush();

        return $this->json([
            'success' => true,
            'msg' => 'Le véhicule a bien été supprimé'
        ]);
    }
}
