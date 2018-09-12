<?php

namespace App\Controller;

use App\Entity\Parcs;
use App\Entity\Filiales;
use App\Entity\Marques;
use App\Entity\Sites;
use App\Entity\CategoriesVehicules;
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
use App\Service\FileUploader;
use Symfony\Component\Form\Extension\Core\Type\TextType;


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
            $current = $request->request->get('current');
            $rowCount = $request->request->get('rowCount');
            $searchPhrase = $request->request->get('searchPhrase');
            $sort = $request->request->get('sort');

            $parcs = $parcsRepository->findByStateSiteImmatriculation($statut, $site, $immat, $searchPhrase, $sort);

            if ($searchPhrase != "" || $statut || $site) {
                $count = count($parcs->getQuery()->getResult());
            } else {
                $count = count($parcsRepository->findAll());
            }

            if ($rowCount != -1) {
                $min = ($current - 1) * $rowCount;
                $max = $rowCount;

                $parcs->setMaxResults($max)
                    ->setFirstResult($min);
            }
            $parcs = $parcs->getQuery()->getResult();

            $rows = array();
            foreach ($parcs as $parc) {
                $row = [
                    "id" => $parc->getId(),
                    "n_parc" => $parc->getNParc(),
                    "etat" => $parc->getStatut(),
                    "n_serie" => (($parc->getNserie() != null) ? $parc->getNSerie() : $parc->getImmatriculation()),
                    "marque" => $parc->getMarque()->getNom(),
                    "site" => $parc->getSite()->getNom(),
                ];
                array_push($rows, $row);
            }

            $data = array(
                "current" => intval($current),
                "rowCount" => intval($rowCount),
                "rows" => $rows,
                "total" => intval($count)
            );
            
            return new JsonResponse($data);
        }

        $filiales = $filialesRepository->findAll();

        return $this->render('parc/index.html.twig', [
            'controller_name' => 'ParcController',
            'filiales' => $filiales,
        ]);
    }

    /**
     * @Route("/upload", name="parc_upload", methods="GET|POST")
     */
    public function upload(Request $request, FileUploader $fileUploader) : Response
    {
        if ($request->isXmlHttpRequest()) {
            $em = $this->getDoctrine()->getManager();
            $file = $request->files->get('file');
            $fileName = $fileUploader->upload($file);
            return new Response($fileName);
        }
        throw new NotFoundHttpException('404 Gwendal not found');
    }

    /**
     * @Route("/create", name="parc_create", methods="GET|POST")
     */
    public function create(Request $request, FileUploader $fileUploader) : Response
    {
        $parc = new Parcs();
        $form = $this->createForm(ParcsType::class, $parc);
        $form->add('url', TextType::class, array(
            "mapped" => false,
            "attr" => array(
                'class' => "hidden"
            ),
        ));

        $em = $this->getDoctrine()->getManager();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($form->get('validation')->isClicked()) {
                $parc = $form->getData();

                /* start upload */
                if ($parc->getImmatriculation()) {
                    $fileName = $form['url']->getData();
                    $parc->setImg($fileName);
                }
                /* end upload */

                $parc->setStatut("Demande création");
                $em->persist($parc);
                $em->flush();

                return $this->redirectToRoute('parc_list');
            }
        }

        return $this->render('parc/create.html.twig', [
            'controller_name' => 'CreateController',
            'form' => $form->createView(),
            'sites' => $this->getSites(),
            'sousCategories' => $this->getSousCategories(),
        ]);
    }

    private function getSites()
    {
        $em = $this->getDoctrine()->getManager();

        $filiales = $em->getRepository(Filiales::class)->findAll();
        $output = array();
        foreach ($filiales as $filiale) {
            $sites = $filiale->getSites();
            $sites_array = array();
            foreach ($sites as $site) {
                $sites_array[] = array(
                    'nom' => $site->getNom(),
                    'id' => $site->getId()
                );
            }
            $output[$filiale->getNom()] = $sites_array;
        }
        return $output;
    }

    private function getSousCategories()
    {
        $em = $this->getDoctrine()->getManager();

        $categories = $em->getRepository(CategoriesVehicules::class)->findAll();
        $output = array();
        foreach ($categories as $categorie) {
            $s_categories = $categorie->getSousCategoriesVehicules();
            $s_categories_array = array();
            foreach ($s_categories as $s_categorie) {
                $s_categories_array[] = array(
                    'nom' => $s_categorie->getNom(),
                    'id' => $s_categorie->getId()
                );
            }
            $output[$categorie->getNom()] = $s_categories_array;
        }
        return $output;
    }

    /**
     * @Route("/edit/{id}", name="parc_edit", methods="GET|POST")
     */
    public function edit(Request $request, Parcs $parc, FileUploader $fileUploader) : Response
    {
        $form = $this->createForm(ParcsType::class, $parc);
        $form->add('url', TextType::class, array(
            "mapped" => false,
            "attr" => array(
                'class' => "hidden"
            ),
        ));
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($form->get('validation')->isClicked()) {
                $parc = $form->getData();

                /* start upload */
                if ($parc->getImmatriculation()) {
                    $fileName = $form['url']->getData();
                    $parc->setImg($fileName);
                }
                /* end upload */

                if ($parc->getNParc()) {
                        $parc->setStatut("Actif");
                    if ($parc->getSortie()) {
                        $parc->setStatut("Demande sortie/transfert");
                    }
                    if ($parc->getEstSorti()) {
                        $parc->setStatut("Sorti");
                    }
                } else {
                    $parc->setStatut("Demande création");
                }

                $this->getDoctrine()->getManager()->flush();

                return $this->redirectToRoute('parc_list');
            }
        }

        return $this->render('parc/edit.html.twig', [
            'controller_name' => 'EditController',
            'parc' => $parc,
            'n_parc' => $parc->getNParc(),
            'form' => $form->createView(),
            'sites' => $this->getSites(),
            'sousCategories' => $this->getSousCategories(),
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
            $compteur = $parcsRepository->findLast();
            if ($compteur) {
                $compteur = $compteur->getId() + 1;
            } else {
                $compteur = 0;
            }
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
     * @Route("/ajax/immatriculation", name="parc_immatriculation_error", methods="GET|POST")
     */
    public function parc_immatriculation_error(Request $request) : Response
    {
        if ($request->isXmlHttpRequest()) {
            $em = $this->getDoctrine()->getManager();
            $immatriculation = $request->request->get('immatriculation');
            $immatriculation_init = $request->request->get('immatriculation_init');

            $parcs = $em->getRepository(Parcs::class)->findAll();
            foreach ($parcs as $parc) {
                if (!strcmp($immatriculation, $parc->getImmatriculation())
                    && $parc->getImmatriculation() != null
                    && $parc->getImmatriculation() != $immatriculation_init) {
                    return new JsonResponse(true);
                }
            }
            return new JsonResponse(false);
        }
        throw new NotFoundHttpException('404 Gwendal not found');
    }

    /**
     * @Route("/ajax/serie", name="parc_serie_error", methods="GET|POST")
     */
    public function parc_serie_error(Request $request) : Response
    {
        if ($request->isXmlHttpRequest()) {
            $em = $this->getDoctrine()->getManager();
            $serie = $request->request->get('serie');
            $serie_init = $request->request->get('serie_init');

            dump($serie_init);
            $parcs = $em->getRepository(Parcs::class)->findAll();
            foreach ($parcs as $parc) {
                if (!strcmp($serie, $parc->getNSerie())
                    && $parc->getNSerie() != null
                    && $parc->getNSerie() != $serie_init) {
                    return new JsonResponse(true);
                }
            }
            return new JsonResponse(false);
        }
        throw new NotFoundHttpException('404 Gwendal not found');
    }

    /**
     * @Route("/export/csv", name="export_csv", methods="GET|POST")
     */
    public function generateCsvAction(ParcsRepository $parcsRepository) : Response
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $normalizer = new ObjectNormalizer($classMetadataFactory);
        $serializer = new Serializer([$normalizer], [new CsvEncoder(';')]);

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
        $data = $serializer->serialize($org, 'csv', array('groups' => array('parc')));
        $data = str_replace("statut;n_parc;filiale.nom;site.nom;categorieVehicule.nom;sousCategorieVehicule.nom;sousCategorieVehicule.code;marque.nom;modele;poids;mise_en_circulation;commentaire;fournisseur;mode_acquisition;mise_en_service;n_serie;immatriculation;genre;ptac;ptr;puissance_fiscale;sortie;motif;commentaire_sortie",
            "Statut;Numéro de parc;Filiale;Site;Catégorie de véhicule;Sous-catégorie de véhicule;Code de sous-catégorie de véhicule;Marque;Modèle;Poids;Date de mise en circulation;Commentaire;Fournisseur;Mode d'acquisition;Date de mise en service;Numéro de série;Immatriculation;Genre;PTAC;PTR;Puissance fiscale;Date de sortie;Motif;Commentaire de sortie",
            $data
        );
        $fileName = "export_parcs_" . date("d_m_Y") . ".csv";
        $response = new Response($data);
        $response->setStatusCode(200);
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8; application/excel');
        $response->headers->set('Content-Disposition', 'attachment; filename=' . $fileName);
        echo "\xEF\xBB\xBF"; // UTF-8 with BOM
        return $response;
    }

    /**
     * @Route("/admin/parametrage", name="parc_parametrage", methods="GET|POST")
     */
    public function parc_parametrage(Request $request) : Response
    {
        $em = $this->getDoctrine()->getManager();

        return $this->render('parc/parametrage.html.twig', [
            'controller_name' => 'CreateController',
            'filiales' => $em->getRepository(Filiales::class)->findAll(),
            'sites' => $em->getRepository(Sites::class)->findAll(),
            'categories' => $em->getRepository(CategoriesVehicules::class)->findAll(),
            'sousCategories' => $em->getRepository(SousCategoriesVehicules::class)->findAll(),
            'marques' => $em->getRepository(Marques::class)->findAll(),
        ]);
    }
}
