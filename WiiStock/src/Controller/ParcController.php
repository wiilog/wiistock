<?php

namespace App\Controller;

use App\Entity\Parcs;
use App\Entity\SousCategoriesVehicules;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use App\Form\ParcsType;
use App\Repository\ParcsRepository;
use App\Repository\FilialesRepository;
use App\Repository\SousCategoriesVehiculesRepository;
use App\Repository\SitesRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use Doctrine\Common\Annotations\AnnotationReader;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Serializer\Normalizer\PropertyNormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * @Route("/parc")
 */

class ParcController extends AbstractController
{

    /**
     * @Route("/list", name="parc_list")
     */
    public function index(ParcsRepository $parcsRepository, FilialesRepository $filialesRepository, Request $request)
    {

        if ($request->isXmlHttpRequest()) {
            $statut = $request->request->get('statut');
            $site = $request->request->get('site');
            $immat = $request->request->get('immat');
            if ($statut || $site || $immat) {
                $parcs = $parcsRepository->findByStateSiteImmatriculation($statut, $site, $immat);
            } else {
                $parcs = $parcsRepository->findAll();
            }
            $count = count($parcsRepository->findAll());

            $rows = array();
            foreach ($parcs as $parc) {
                $row = [
                    "id" => $parc->getId(),
                    "nparc" => $parc->getNParc(),
                    "etat" => $parc->getStatut(),
                    "nserie" => (($parc->getNserie() != null) ? $parc->getNSerie() : $parc->getImmatriculation()),
                    "marque" => $parc->getMarque()->getNom(),
                    "site" => $parc->getSite()->getNom(),
                ];
                array_push($rows, $row);
            }

            $current = $request->request->get('currentPage');
            $rowCount = $request->request->get('rowCount');
            $data = array(
                "current" => $current,
                "rowCount" => $rowCount,
                "rows" => $rows,
                "total" => $count
            );
            
            /*
            $encoders = array(new JsonEncoder());
            $normalizers = array(new ObjectNormalizer());

            $serializer = new Serializer($normalizers, $encoders);
            $jsonContent = $serializer->serialize($parcs, 'json', array('groups' => array('parc')));

            dump($data);
             */
            return new JsonResponse($data);

        }

        $filiales = $filialesRepository->findAll();


        return $this->render('parc/index.html.twig', [
            'controller_name' => 'ParcController',
            'filiales' => $filiales,
        ]);
    }

    /**
     * @Route("/create", name="parc_create", methods="GET|POST")
     */
    public function create(Request $request) : Response
    {
        $parc = new Parcs();
        $form = $this->createForm(ParcsType::class, $parc);

        $em = $this->getDoctrine()->getManager();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($form->get('validation')->isClicked()) {
                $parc = $form->getData();
                $parc->setStatut("Demande création");
                $em->persist($parc);
                $em->flush();

                return $this->redirectToRoute('parc_list');
            }
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
            'n_parc' => $parc->getNParc(),
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/ajax/generator", name="parc_number_generator", methods="GET|POST")
     */
    public function parc_number_generator(ParcsRepository $parcsRepository, Request $request) : Response
    {
        if ($request->isXmlHttpRequest()) {
            $em = $this->getDoctrine()->getManager();
            $s_categorie = $request->request->get('s_categorie');
            $m_acquisition = $request->request->get('m_acquisition');
            // $compteur = count($em->getRepository(Parcs::class)->findAll());
            $compteur = $parcsRepository->findLast()->getId() + 1;
            $code = str_pad($compteur, 4, "0", STR_PAD_LEFT);

            $s_code = strval($em->getRepository(SousCategoriesVehicules::class)->findOneBy(array('nom' => $s_categorie))->getCode());
            if (strcmp($m_acquisition, 'Achat neuf')) {
                $m_code = '0';
            } elseif (strcmp($m_acquisition, 'Achat d\'occasion')) {
                $m_code = '8';
            } else {
                $m_code = '9';
            }

            $n_parc = array();
            $n_parc = $s_code . $m_code . $code;
            return new JsonResponse($n_parc);
        }
        throw new NotFoundHttpException('404 Gwendal not found');
    }

    /**
     * @Route("/ajax/categories", name="parc_categories", methods="GET|POST")
     */
    public function parc_categories(SousCategoriesVehiculesRepository $repository, Request $request) : Response
    {
        if ($request->isXmlHttpRequest()) {
            $em = $this->getDoctrine()->getManager();
            $categorie = $request->request->get('categorie');

            $s_categories = $repository->findBySousCategory($categorie);
            foreach ($s_categories as $s_categorie) {
                $output[] = array('nom' => $s_categorie->getNom());
            }
            return new JsonResponse($output);
        }
        throw new NotFoundHttpException('404 Gwendal not found');
    }

    /**
     * @Route("/ajax/filiales", name="parc_filiales", methods="GET|POST")
     */
    public function parc_filiales(SitesRepository $repository, Request $request) : Response
    {
        if ($request->isXmlHttpRequest()) {
            $em = $this->getDoctrine()->getManager();
            $filiale = $request->request->get('filiale');

            $sites = $repository->findByFiliale($filiale);
            foreach ($sites as $site) {
                $output[] = array('nom' => $site->getNom());
            }
            return new JsonResponse($output);
        }
        throw new NotFoundHttpException('404 Gwendal not found');
    }

    /**
     * @Route("/export/csv", name="export_csv", methods="GET|POST")
     */
    public function generateCsvAction(ParcsRepository $parcsRepository) : Response
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $encoder = new CsvEncoder();
        $normalizer = new PropertyNormalizer($classMetadataFactory);
        $serializer = new Serializer(array($normalizer), array($encoder));

        $callback = function ($dateTime) {
            return $dateTime instanceof \DateTime
                ? $dateTime->format('d/m/y')
                : '';
        };

        $normalizer->setCallbacks(array(
            'sortie' => $callback,
            'mise_en_circulation' => $callback,
            'mise_en_service' => $callback,
            'incorporation' => $callback,
        ));

        $org = $parcsRepository->findAll();
        $data = $serializer->serialize($org, 'csv', ['groups' => ['parc']]);

        $data = str_replace(",", ";", $data);

        $fileName = "export_parcs_" . date("d_m_Y") . ".csv";
        $response = new Response($data);
        $response->setStatusCode(200);
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8; application/excel');
        $response->headers->set('Content-Disposition', 'attachment; filename=' . $fileName);
        echo "\xEF\xBB\xBF"; // UTF-8 with BOM
        return $response;
    }
}
