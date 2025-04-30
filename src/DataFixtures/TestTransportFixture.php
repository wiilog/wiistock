<?php

namespace App\DataFixtures;

use App\Entity\CategorieStatut;
use App\Entity\Statut;
use App\Entity\Transport\TransportCollectRequest;
use App\Entity\Transport\TransportDeliveryRequest;
use App\Entity\Transport\TransportOrder;
use App\Entity\Transport\TransportRequest;
use App\Entity\Transport\TransportRequestContact;
use App\Entity\Type\CategoryType;
use App\Entity\Type\Type;
use App\Entity\Utilisateur;
use App\Repository\StatutRepository;
use App\Repository\TypeRepository;
use App\Repository\UtilisateurRepository;
use DateTime;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class TestTransportFixture extends Fixture implements FixtureGroupInterface {

    private TypeRepository $typeRepository;
    private StatutRepository $statusRepository;
    private UtilisateurRepository $userRepository;

    public function load(ObjectManager $manager) {
        $this->typeRepository = $manager->getRepository(Type::class);
        $this->statusRepository = $manager->getRepository(Statut::class);
        $this->userRepository = $manager->getRepository(Utilisateur::class);

        $delivery = $this->typeRepository->findByCategoryLabels([CategoryType::DELIVERY_TRANSPORT]);
        $collect = $this->typeRepository->findByCategoryLabels([CategoryType::COLLECT_TRANSPORT]);

        $user = $this->userRepository->find(1);

        $this->createOngoingDelivery($manager, $delivery, $user);
        $this->createOngoingCollect($manager, $collect, $user);
        $this->createFinishedDelivery($manager, $delivery, $user);
        $this->createFinishedCollect($manager, $collect, $user);
        $this->createSubcontractedDelivery($manager, $delivery, $user);

        $manager->flush();
    }

    public static function getGroups(): array {
        return ["test-transport"];
    }

    public function createOngoingDelivery(ObjectManager $manager, array $types, Utilisateur $user) {
        $type = $this->randomElementInArray($types);
        $ongoingRequest = $this->statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSPORT_REQUEST_DELIVERY, TransportRequest::STATUS_ONGOING);
        $ongoingOrder = $this->statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSPORT_ORDER_DELIVERY, TransportOrder::STATUS_ONGOING);

        $request = new TransportDeliveryRequest();
        $request->setType($type);
        $request->setStatus($ongoingRequest);
        $request->setCreatedAt($this->randomDate("past"));
        $request->setCreatedBy($user);
        $request->setNumber($request->getCreatedAt()->format("Ymd") . random_int(1, 9999) . "T");
        $request->setExpectedAt($this->randomDate("future"));
        $request->setContact((new TransportRequestContact())
            ->setName("Oui")
            ->setPersonToContact("Quelqu'un")
            ->setAddress("6 rue de la paix")
            ->setContact("aaaaa")
            ->setObservation("oui")
            ->setFileNumber("F3872804782")
        );

        $request->setValidationDate($this->randomDate("past"));

        $order = new TransportOrder();
        $order->setRequest($request);
        $order->setCreatedAt((clone $request->getCreatedAt())->modify("+1 day"));
        $order->setStatus($ongoingOrder);
        $order->setStartedAt((clone $order->getCreatedAt())->modify("+1 hour"));
        $order->setSubcontracted(false);

        $manager->persist($request);
    }

    private function createOngoingCollect(ObjectManager $manager, array $types, Utilisateur $user) {
        $type = $this->randomElementInArray($types);
        $ongoingRequest = $this->statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSPORT_REQUEST_COLLECT, TransportRequest::STATUS_ONGOING);
        $ongoingOrder = $this->statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSPORT_ORDER_COLLECT, TransportOrder::STATUS_ONGOING);

        $request = new TransportCollectRequest();
        $request->setType($type);
        $request->setStatus($ongoingRequest);
        $request->setCreatedAt($this->randomDate("past"));
        $request->setCreatedBy($user);
        $request->setNumber($request->getCreatedAt()->format("Ymd") . random_int(1, 9999) . "T");
        $request->setExpectedAt($this->randomDate("future"));
        $request->setContact((new TransportRequestContact())
            ->setName("Oui")
            ->setPersonToContact("Quelqu'un")
            ->setAddress("6 rue de la paix")
            ->setContact("aaaaa")
            ->setObservation("oui")
            ->setFileNumber("F3872804782")
        );

        $request->setValidationDate($this->randomDate("past"));

        $order = new TransportOrder();
        $order->setRequest($request);
        $order->setCreatedAt((clone $request->getCreatedAt())->modify("+1 day"));
        $order->setStatus($ongoingOrder);
        $order->setStartedAt((clone $order->getCreatedAt())->modify("+1 hour"));
        $order->setSubcontracted(false);

        $manager->persist($request);
    }

    private function createFinishedDelivery(ObjectManager $manager, array $types, Utilisateur $user) {
        $type = $this->randomElementInArray($types);
        $finishedRequest = $this->statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSPORT_REQUEST_DELIVERY, TransportRequest::STATUS_FINISHED);
        $finishedOrder = $this->statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSPORT_ORDER_DELIVERY, TransportOrder::STATUS_FINISHED);

        $request = new TransportDeliveryRequest();
        $request->setType($type);
        $request->setStatus($finishedRequest);
        $request->setCreatedAt($this->randomDate("past"));
        $request->setCreatedBy($user);
        $request->setNumber($request->getCreatedAt()->format("Ymd") . random_int(1, 9999) . "T");
        $request->setExpectedAt($this->randomDate("future"));
        $request->setContact((new TransportRequestContact())
            ->setName("Oui")
            ->setPersonToContact("Quelqu'un")
            ->setAddress("6 rue de la paix")
            ->setContact("aaaaa")
            ->setObservation("oui")
            ->setFileNumber("F3872804782")
        );

        $request->setValidationDate($this->randomDate("past"));

        $order = new TransportOrder();
        $order->setRequest($request);
        $order->setCreatedAt((clone $request->getCreatedAt())->modify("+1 day"));
        $order->setStatus($finishedOrder);
        $order->setStartedAt((clone $order->getCreatedAt())->modify("+1 hour"));
        $order->setSubcontracted(false);

        $manager->persist($request);
    }

    private function createFinishedCollect(ObjectManager $manager, array $types, Utilisateur $user) {
        $type = $this->randomElementInArray($types);
        $finishedRequest = $this->statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSPORT_REQUEST_COLLECT, TransportRequest::STATUS_FINISHED);
        $finishedOrder = $this->statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSPORT_ORDER_COLLECT, TransportOrder::STATUS_FINISHED);

        $request = new TransportCollectRequest();
        $request->setType($type);
        $request->setStatus($finishedRequest);
        $request->setCreatedAt($this->randomDate("past"));
        $request->setCreatedBy($user);
        $request->setNumber($request->getCreatedAt()->format("Ymd") . random_int(1, 9999) . "T");
        $request->setExpectedAt($this->randomDate("future"));
        $request->setContact((new TransportRequestContact())
            ->setName("Oui")
            ->setPersonToContact("Quelqu'un")
            ->setAddress("6 rue de la paix")
            ->setContact("aaaaa")
            ->setObservation("oui")
            ->setFileNumber("F3872804782")
        );

        $request->setValidationDate($this->randomDate("past"));

        $order = new TransportOrder();
        $order->setRequest($request);
        $order->setCreatedAt((clone $request->getCreatedAt())->modify("+1 day"));
        $order->setStatus($finishedOrder);
        $order->setStartedAt((clone $order->getCreatedAt())->modify("+1 hour"));
        $order->setSubcontracted(false);

        $manager->persist($request);
    }

    private function createSubcontractedDelivery(ObjectManager $manager, array $types, Utilisateur $user) {
        $type = $this->randomElementInArray($types);
        $subcontractedRequest = $this->statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSPORT_REQUEST_DELIVERY, TransportRequest::STATUS_SUBCONTRACTED);
        $subcontractedOrder = $this->statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSPORT_ORDER_DELIVERY, TransportOrder::STATUS_SUBCONTRACTED);

        $request = new TransportDeliveryRequest();
        $request->setType($type);
        $request->setStatus($subcontractedRequest);
        $request->setCreatedAt($this->randomDate("past"));
        $request->setCreatedBy($user);
        $request->setNumber($request->getCreatedAt()->format("Ymd") . random_int(1, 9999) . "T");
        $request->setExpectedAt($this->randomDate("future"));
        $request->setContact((new TransportRequestContact())
            ->setName("Oui")
            ->setPersonToContact("Quelqu'un")
            ->setAddress("6 rue de la paix")
            ->setContact("aaaaa")
            ->setObservation("oui")
            ->setFileNumber("F3872804782")
        );

        $request->setValidationDate($this->randomDate("past"));

        $order = new TransportOrder();
        $order->setRequest($request);
        $order->setCreatedAt((clone $request->getCreatedAt())->modify("+1 day"));
        $order->setStatus($subcontractedOrder);
        $order->setStartedAt((clone $order->getCreatedAt())->modify("+1 hour"));
        $order->setSubcontracted(true);

        $manager->persist($request);
    }

    private function randomDate(string $when = "future"): DateTime {
        if($when === "past") {
            return new DateTime("now -" . rand(1, 10) . " days");
        } else {
            return new DateTime("now +" . rand(1, 10) . " days");
        }
    }

    private function randomElementInArray(array $array) {
        return $array[array_rand($array)];
    }

}
