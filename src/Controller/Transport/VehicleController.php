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

#[Route('/vehicule')]
class VehicleController extends AbstractController
{

    #[Route('/liste', name: 'vehicle_index', methods: 'GET')]
    #[HasPermission([Menu::REFERENTIEL, Action::DISPLAY_VEHICLE])]
    public function index(): Response
    {
        return $this->render('vehicle/index.html.twig', [
            'newVehicle' => new Vehicle(),
        ]);
    }

    #[Route('/api', name: 'vehicle_api', options: ['expose' => true], methods: 'POST', condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::REFERENTIEL, Action::DISPLAY_VEHICLE], mode: HasPermission::IN_JSON)]
    public function api(Request $request, VehicleService $vehicleService): Response
    {
        $data = $vehicleService->getDataForDatatable($request->request);

        return $this->json($data);
    }

    #[Route('/new', name: 'vehicle_new', options: ['expose' => true], methods: 'POST', condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::REFERENTIEL, Action::CREATE], mode: HasPermission::IN_JSON)]
    public function new(Request $request, EntityManagerInterface $manager): Response
    {
        $data = json_decode($request->getContent(), true);

        $registrationNumber = $data['registrationNumber'];
        $existing = $manager->getRepository(Vehicle::class)->findOneBy(['registrationNumber' => $registrationNumber]);

        if ($existing) {
            return $this->json([
                'success' => false,
                'msg' => 'Un véhicule avec cette immatriculation existe déjà'
            ]);
        } else {
            $deliverer = isset($data['deliverer']) ? $manager->find(Utilisateur::class, $data['deliverer']) : null;
            $locations = $manager->getRepository(Emplacement::class)->findBy(['id' => $data['locations']]);
            $vehicle = (new Vehicle())
                ->setRegistrationNumber($registrationNumber)
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

        $registrationNumber = $data['registrationNumber'];
        $existing = $manager->getRepository(Vehicle::class)->findOneBy(['registrationNumber' => $registrationNumber]);
        if ($existing && $existing !== $vehicle) {
            return $this->json([
                'success' => false,
                'msg' => 'Un véhicule avec cette immatriculation existe déjà'
            ]);
        } else {
            $deliverer = isset($data['deliverer']) ? $manager->find(Utilisateur::class, $data['deliverer']) : null;
            $locations = $manager->getRepository(Emplacement::class)->findBy(['id' => $data['locations']]);
            $vehicle
                ->setRegistrationNumber($registrationNumber)
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

        $manager->remove($vehicle);
        $manager->flush();

        return $this->json([
            'success' => true,
            'msg' => 'Le véhicule a bien été supprimé'
        ]);
    }
}
