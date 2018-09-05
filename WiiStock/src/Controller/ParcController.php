<?php

namespace App\Controller;

use App\Entity\Parcs;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use App\Form\ParcsType;
use App\Repository\ParcsRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use App\Entity\SousCategoriesVehicules;

/**
 * @Route("/parc")
 */

class ParcController extends AbstractController
{
    
	/**
 	 * @Route("/list", name="parc_list")
 	 */    
    public function index(ParcsRepository $parcsRepository, Request $request)
    {

        if ($request->isXmlHttpRequest()) {
            $statut = $request->request->get('statut');
            if ($statut) {
                $parcs = $parcsRepository->findByStatut($statut);
            } else {
                $parcs = $parcsRepository->findAll();
            }
            $count = count($parcsRepository->findAll());

            $rows = array();
            foreach ($parcs as $parc) {
                $row = ["nparc" => $parc->getNParc(),
                        "etat" => $parc->getStatut(),
                        "nserie" => (($parc->getChariots() != null) ? $parc->getChariots()->getNSerie() : $parc->getVehicules()->getImmatriculation() ),
                        "marque" => $parc->getMarque()->getNom(),
                        "site" => $parc->getSite()->getNom(),
                    ];
                array_push($rows, $row);
            }

            $data = array("current" => 1, // getCurrent
                        "rowCount" => 10, // getRowCount
                        "rows" => $rows,
                        "total" => $count
            );


            
            /*$encoders = array(new JsonEncoder());
            $normalizers = array(new ObjectNormalizer());

            $serializer = new Serializer($normalizers, $encoders);
            $jsonContent = $serializer->serialize($parcs, 'json', array('groups' => array('parc')));
            */
            dump($data);
            return new JsonResponse($data);

        }

        return $this->render('parc/index.html.twig', [
            'controller_name' => 'ParcController',
        ]);
    }

    /**
    * @Route("/index_ajax", name="parc_index_ajax")
    */
    public function index_ajax(Request $request) {
        if ($request->isXmlHttpRequest()) {

            /*$criteria = array('status' => $status, 'site' => $site, 'vehicules.immatriculation' => $immat, 'chariots.n_serie' => $nserie);
            $parcs = $parcsRepository->findBy($criteria);*/
            $parcs = $parcsRepository->findAll();
            /*$rows = array();
            foreach ($parcs as $parc) {
                $row = ["nparc" => "parc",
                        "etat" => "state",
                        "nserie" => "chariot serie",
                        "marque" => "marque",
                        "site" => "site",
                    ];
                array_push($rows, $row);
            }*/

/*            $count = count($parcsRepository->findAll());*/
            $data = array("current" => 1,
                        "rowCount" => 1,
                        "rows" => [
                            ["nparc" => "parc",
                             "etat" => "state",
                             "nserie" => "chariot serie",
                             "marque" => "marque",
                             "site" => "site"],
                        ],
                        "total" => 1
            );

            dump($data);
            return new JsonResponse($data);

        }
    }

    /**
     * @Route("/create", name="parc_create", methods="GET|POST")
     */
    public function create(Request $request) : Response
    {
        $parc = new Parcs();
        $form = $this->createForm(ParcsType::class, $parc);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($form->get('validation')->isClicked()) {
                $parc = $form->getData();
                $parc->setStatut("Demande création");
                $em = $this->getDoctrine()->getManager();
                $em->persist($parc);
                $em->flush();
            }

            return $this->redirectToRoute('parc_list');
        }

        return $this->render('parc/create.html.twig', [
            'controller_name' => 'CreateController',
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/edit/{id}", name="parc_edit", methods="GET|POST")
     */
    public function edit(Request $request, Parcs $parc) : Response
    {
        $form = $this->createForm(ParcsType::class, $parc);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('parc_list');
        }

        return $this->render('parc/edit.html.twig', [
            'controller_name' => 'EditController',
            'parc' => $parc,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/generator", name="parc_number_generator", methods="GET|POST")
     */
    public function parc_number_generator(Request $request) : Response
    {
        if ($request->isXmlHttpRequest()) {
            $em = $this->getDoctrine()->getManager();
            $s_categorie = $request->request->get('s_categorie');
            $m_acquisition = $request->request->get('m_acquisition');
            $compteur = '0000' + count($em->getRepository(Parcs::class)->findAll());

            $s_code = $em->getRepository(SousCategoriesVehicules::class)->findOneBy(array('nom' => $s_categorie))->getCode();
            if (strcmp($m_acquisition, 'Achat neuf')) {
                $m_code = '0';
            } elseif (strcmp($m_acquisition, 'Achat d\'occasion')) {
                $m_code = '8';
            } else {
                $m_code = '9';
            }

            dump($compteur, $s_code, $m_code);

            $n_parc = $s_code + $m_code + $compteur;

            dump($n_parc);

            return new JsonResponse($n_parc);
        }
    }
}
