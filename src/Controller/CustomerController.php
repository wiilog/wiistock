<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Customer;
use App\Entity\Import;
use App\Entity\Menu;
use App\Service\CSVExportService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;


#[Route("/clients")]
class CustomerController extends AbstractController
{
    #[Route("/", name: "customer_index", options: ["expose" => true], methods: "GET")]
    #[HasPermission([Menu::REFERENTIEL, Action::DISPLAY_CUSTOMER], mode: HasPermission::IN_JSON)]
    public function index(EntityManagerInterface $entityManager): Response
    {
        return $this->render('customer/index.html.twig', [
            "newCustomer" => new Customer(),
        ]);
    }


    #[Route("/api", name: "customer_api", options: ["expose" => true], methods: "GET|POST", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::REFERENTIEL, Action::DISPLAY_CUSTOMER], mode: HasPermission::IN_JSON)]
    public function api(EntityManagerInterface $entityManager): JsonResponse
    {
        $customerRepository = $entityManager->getRepository(Customer::class);
        $customers = $customerRepository->findAllSorted();
        $rows = [];
        foreach ($customers as $customer) {
            $rows[] = [
                'customer' => $customer->getName() ?? null,
                'address' => $customer->getAddress() ?? null,
                'recipient' => $customer->getRecipient() ?? null,
                'phoneNumber' => $customer->getPhoneNumber() ?? null,
                'email' => $customer->getEmail() ?? null,
                'fax' => $customer->getFax() ?? null,
                'actions' => $this->renderView('customer/datatableCustomerAction.html.twig', [
                    'customer' => $customer
                ]),
            ];
        }
        $data['data'] = $rows;

        return new JsonResponse($data);
    }

    #[Route('/new', name: 'customer_new', options: ['expose' => true], methods: 'POST', condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::REFERENTIEL, Action::CREATE], mode: HasPermission::IN_JSON)]
    public function new(Request $request, EntityManagerInterface $manager): Response
    {
        $data = $request->request->all();

        $existing = $manager->getRepository(Customer::class)->findOneBy(['name' =>  $nameExisting = $data['name']]);
        if ($existing) {
            return $this->json([
                'success' => false,
                'msg' => 'Un client avec ce nom existe déjà'
            ]);
        } else {
            $customer = (new Customer())
                ->setName($data['name'])
                ->setAddress($data['address'] ?? null)
                ->setRecipient($data['recipient'] ?? null)
                ->setPhoneNumber($data['phone-number'] ?? null)
                ->setEmail($data['email'] ?? null)
                ->setFax($data['fax'] ?? null);

            $manager->persist($customer);
            $manager->flush();

            return $this->json([
                'success' => true,
                'msg' => 'Le client a bien été créé'
            ]);
        }
    }

    #[Route('/edit-api', name: 'customer_edit_api', options: ['expose' => true], methods: 'POST', condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::REFERENTIEL, Action::EDIT], mode: HasPermission::IN_JSON)]
    public function editApi(EntityManagerInterface $manager,
                            Request                $request): Response
    {
        $data = json_decode($request->getContent(), true);
        $customer = $manager->find(Customer::class, $data['id']);
        $content = $this->renderView('customer/modal/form.html.twig', [
            'customer' => $customer,
        ]);
        return $this->json($content);
    }

    #[Route('/edit', name: 'customer_edit', options: ['expose' => true], methods: 'POST', condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::REFERENTIEL, Action::EDIT], mode: HasPermission::IN_JSON)]
    public function edit(Request $request, EntityManagerInterface $manager) : Response
    {
        $data = $request->request->all();
        $customerRepository = $manager->getRepository(Customer::class);
        $customer = $customerRepository->find($data['id']);

        $existing = $customerRepository->findOneBy(['name' => $data['name']]);
        if ($existing && $existing !== $customer) {
            return $this->json([
                'success' => false,
                'msg' => 'Un client avec ce code existe déjà'
            ]);
        }
        else {
            $customer->setName($data['name']);

            if (array_key_exists('address', $data)) {
                $customer->setAddress($data['address']);
            } elseif ($customer->getAddress() !== null) {
                $customer->setAddress(null);
            }

            if (array_key_exists('recipient', $data)) {
                $customer->setRecipient($data['recipient']);
            } elseif ($customer->getRecipient() !== null) {
                $customer->setRecipient(null);
            }

            if (array_key_exists('phone-number', $data)) {
                $customer->setPhoneNumber($data['phone-number']);
            } elseif ($customer->getPhoneNumber() !== null) {
                $customer->setPhoneNumber(null);
            }

            if (array_key_exists('email', $data)) {
                $customer->setEmail($data['email']);
            } elseif ($customer->getEmail() !== null) {
                $customer->setEmail(null);
            }

            if (array_key_exists('fax', $data)) {
                $customer->setFax($data['fax']);
            } elseif ($customer->getFax() !== null) {
                $customer->setFax(null);
            }

            $manager->persist($customer);
            $manager->flush();

            return $this->json([
                'success' => true,
                'msg' => 'Le client a bien été modifiée'
            ]);
        }
    }

    #[Route('/delete/{customer}', name: 'customer_delete', options: ['expose' => true], methods: 'DELETE')]
    #[HasPermission([Menu::REFERENTIEL, Action::DELETE], mode: HasPermission::IN_JSON)]
    public function delete(Customer $customer, EntityManagerInterface $manager): Response
    {
        $manager->remove($customer);
        $manager->flush();

        return $this->json([
            "success" => true,
            "msg" => "Le client a bien été supprimé"
        ]);
    }

    /**
     * @Route("/csv", name="get_customers_csv", options={"expose"=true}, methods={"GET"})
     */
    public function getCustomersCSV(CSVExportService $CSVExportService, EntityManagerInterface $entityManager): Response {
        $csvHeader = [
            "Client",
            "Adresse",
            "Destinataire",
            "Téléphone",
            "Email",
            "Fax",
        ];

        $today = new DateTime();
        $today = $today->format("d-m-Y-H-i-s");

        return $CSVExportService->streamResponse(function ($output) use ($entityManager, $CSVExportService) {
            $customers = $entityManager->getRepository(Customer::class)->iterateAll();

            foreach ($customers as $customer) {
                $CSVExportService->putLine($output, $customer->serialize());
            }
        }, "export-customers-$today.csv", $csvHeader);
    }
}
