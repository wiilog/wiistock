<?php

namespace App\Controller;

use App\Entity\Box;
use App\Entity\BoxType;
use App\Entity\CategoryType;
use App\Entity\Client;
use App\Entity\DepositTicket;
use App\Entity\Emplacement;
use App\Entity\Group;
use App\Entity\IOT\Sensor;
use App\Entity\IOT\SensorWrapper;
use App\Entity\Location;
use App\Entity\Quality;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SelectController extends AbstractController {

    /**
     * @Route("/select/emplacement", name="ajax_select_locations", options={"expose": true})
     */
    public function locations(Request $request, EntityManagerInterface $manager): Response {
        $deliveryType = $request->query->get("deliveryType") ?? null;
        $collectType = $request->query->get("collectType") ?? null;
        $results = $manager->getRepository(Emplacement::class)->getForSelect(
            $request->query->get("term"),
            $deliveryType,
            $collectType
        );

        return $this->json([
            "results" => $results,
        ]);
    }

    /**
     * @Route("/select/types/services", name="ajax_select_handling_type", options={"expose": true})
     */
    public function handlingType(Request $request, EntityManagerInterface $manager): Response {
        $results = $manager->getRepository(Type::class)->getForSelect(
            CategoryType::DEMANDE_HANDLING,
            $request->query->get("term")
        );

        return $this->json([
            "results" => $results,
        ]);
    }

    /**
     * @Route("/select/types/livraisons", name="ajax_select_delivery_type", options={"expose": true})
     */
    public function deliveryType(Request $request, EntityManagerInterface $manager): Response {
        $results = $manager->getRepository(Type::class)->getForSelect(
            CategoryType::DEMANDE_LIVRAISON,
            $request->query->get("term")
        );

        return $this->json([
            "results" => $results,
        ]);
    }

    /**
     * @Route("/select/types/collectes", name="ajax_select_collect_type", options={"expose": true})
     */
    public function collectType(Request $request, EntityManagerInterface $manager): Response {
        $results = $manager->getRepository(Type::class)->getForSelect(
            CategoryType::DEMANDE_COLLECTE,
            $request->query->get("term")
        );

        return $this->json([
            "results" => $results,
        ]);
    }

    /**
     * @Route("/select/statuts", name="ajax_select_status", options={"expose": true})
     */
    public function status(Request $request, EntityManagerInterface $manager): Response {
        $type = $request->query->get("type") ?? $request->query->get("handlingType") ?? null;
        $results = $manager->getRepository(Statut::class)->getForSelect(
            $request->query->get("term"),
            $type
        );

        return $this->json([
            "results" => $results,
        ]);
    }

    /**
     * @Route("/select/references", name="ajax_select_references", options={"expose": true})
     */
    public function references(Request $request, EntityManagerInterface $manager): Response {
        $results = $manager->getRepository(ReferenceArticle::class)->getForSelect(
            $request->query->get("term"),
        );

        return $this->json([
            "results" => $results,
        ]);
    }

    /**
     * @Route("/select/capteurs-bruts", name="ajax_select_sensors", options={"expose": true})
     */
    public function sensors(Request $request, EntityManagerInterface $manager): Response {
        $results = $manager->getRepository(Sensor::class)->getForSelect($request->query->get("term"));

        return $this->json([
            "results" => $results,
        ]);
    }

    /**
     * @Route("/select/capteurs", name="ajax_select_sensor_wrappers", options={"expose"=true})
     */
    public function getSensorWrappers(Request $request, EntityManagerInterface $entityManager): Response {
        $results = $entityManager->getRepository(SensorWrapper::class)->getForSelect($request->query->get("term"));

        return $this->json([
            "results" => $results
        ]);
    }

}
