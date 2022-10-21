<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Customer;
use App\Entity\Menu;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;


#[Route("/clients")]
class CustomerController extends AbstractController
{
    #[Route("/", name: "customer_index", options: ["expose" => true], methods: "GET")]
    #[HasPermission([Menu::REFERENTIEL, Action::DISPLAY_CUSTOMER], mode: HasPermission::IN_JSON)]
    public function index(EntityManagerInterface $entityManager): Response
    {
        return $this->render('customer/index.html.twig', [
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
                'Customer' => $customer->getCode() ?? null,
                'Address' => $customer->getAddress() ?? null,
                'PhoneNumber' => $customer->getPhoneNumber() ?? null,
                'Email' => $customer->getEmail() ?? null,
                'Fax' => $customer->getFax() ?? null,
                'Delete' => $this->renderView('customer/datatableCustomerRow.html.twig', [
                    'customer' => $customer
                ]),
            ];
        }
        $data['data'] = $rows;

        return new JsonResponse($data);
    }
}
