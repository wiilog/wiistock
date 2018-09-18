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
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Finder\Finder;

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
        $session = $request->getSession();

        if ($request->isXmlHttpRequest()) {
            if (!$request->request->get('start')) {
                $statut = $session->get('statut');
                $site = $session->get('site');
                $immat = $session->get('immat');
            } else {
                $statut = $request->request->get('statut');
                $immat = $request->request->get('immat');
                $site = $request->request->get('site');
            }
            $session->set('statut', $request->request->get('statut'));
            $session->set('site', $request->request->get('site'));
            $session->set('immat', $request->request->get('immat'));

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
                    "sous_cat" => $parc->getSousCategorieVehicule()->getNom(),
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
            'date' => date("d_m_Y"),
        ]);
    }

    /**
     * @Route("/upload", name="parc_upload", methods="GET|POST")
     */
    public function upload(Request $request, FileUploader $fileUploader) : Response
    {
        if ($request->isXmlHttpRequest()) {
            //$em = $this->getDoctrine()->getManager();
            $file = $request->files->get('file');
            $fileName = $fileUploader->upload($file);
            return new Response($fileName);
        }
        throw new NotFoundHttpException('404 Gwendal not found');
    }

    /**
     * @Route("/{id}", name="parc_delete", methods="DELETE")
     */
    public function delete(Request $request, Parcs $parc) : Response
    {
        if ($this->isCsrfTokenValid('delete' . $parc->getId(), $request->request->get('_token'))) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($parc);
            $em->flush();
            $session = $request->getSession();
            $session->getFlashBag()->add('success', 'Félicitations ! Le véhicule a été supprimé avec succès !');
        }

        return $this->redirectToRoute('parc_list');
    }

    /**
     * @Route("/create", name="parc_create", methods="GET|POST")
     */
    public function create(Request $request, FileUploader $fileUploader) : Response
    {
        $parc = new Parcs();
        $form = $this->createForm(ParcsType::class, $parc);
        $form->add('url', HiddenType::class, array(
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
                    $file = $form['img']->getData();
                    $fileName = $form['url']->getData();
                    $parc->setImg($fileName);
                    $parc->setImgOrigine($file->getClientOriginalName());
                }
                /* end upload */

                $parc->setStatut("Demande création");
                $parc->setLastEdit($this->getUser()->getEmail());
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
        $statut = $parc->getStatut();
        $form = $this->createForm(ParcsType::class, $parc);
        $form->add('url', HiddenType::class, array(
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

                if (in_array('ROLE_PARC_ADMIN', $this->getUser()->getRoles())) {
                    $parc->setStatut($statut);
                    if ($parc->getNParc()) {
                        $parc->setStatut("Actif");
                    }
                    if ($parc->getSortie()) {
                        $parc->setStatut("Demande sortie/transfert");
                    }
                    if ($parc->getEstSorti()) {
                        $parc->setStatut("Sorti");
                    }
                } else {
                    $parc->setStatut($statut);
                    if ($statut == "Actif" && $parc->getSortie()) {
                        $parc->setStatut("Demande sortie/transfert");
                    }
                    if ($statut == "Demande sortie/transfert" && !$parc->getSortie()) {
                        $parc->setStatut("Actif");
                    }
                }

                $parc->setLastEdit($this->getUser()->getEmail());
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
            $parcs = $em->getRepository(Parcs::class)->findAll();
            $max = 0;
            foreach ($parcs as $parc) {
                $nparc = $parc->getNParc();
                $val = intval(substr($nparc, -4));
                dump($val);
                if ($val > $max) {
                    $max = $val;
                }
            }
            $compteur = $max + 1;
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
    public function generateCsvAction(ParcsRepository $parcsRepository, Request $request) : Response
    {
        if ($request->isXmlHttpRequest()) {
            $statut = $request->request->get('statut');
            $site = $request->request->get('site');
            $immatriculation = $request->request->get('immatriculation');
            $searchPhrase = $request->request->get('searchPhrase');
            $sort = null;

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
            ));

            $org = $parcsRepository->findByStateSiteImmatriculation($statut, $site, $immatriculation, $searchPhrase, $sort)->getQuery()->getResult();
            $data = $serializer->serialize($org, 'csv', array('groups' => array('parc')));
            $data = str_replace(
                "statut;n_parc;filiale.nom;site.nom;categorieVehicule.nom;sousCategorieVehicule.nom;sousCategorieVehicule.code;marque.nom;modele;poids;mise_en_circulation;commentaire;fournisseur;mode_acquisition;mise_en_service;n_serie;immatriculation;genre;ptac;ptr;puissance_fiscale;sortie;motif;commentaire_sortie",
                "Statut;Numéro de parc;Filiale;Site;Catégorie de véhicule;Sous-catégorie de véhicule;Code de sous-catégorie de véhicule;Marque;Modèle;Poids;Date de mise en circulation;Commentaire;Fournisseur;Mode d'acquisition;Date de mise en service;Numéro de série;Immatriculation;Genre;PTAC;PTR;Puissance fiscale;Date de sortie;Motif;Commentaire de sortie",
                $data
            );
            $response = new JsonResponse($data);
            return $response;
        }
        throw new NotFoundHttpException('404 Gwendal not found');
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

    /**
     * @Route("/admin/fixtures", name="parc_fixtures")
     */
    public function parc_fixtures(Request $request) : Response
    {
        $em = $this->getDoctrine()->getManager();

        // $file = file_get_contents('../public/download/flotte.csv');
        // dump($file);
        $row = 1;
        if (($handle = fopen('../public/download/flotte.csv', "r")) !== false) {
            while (($data = fgetcsv($handle, 1000, ";")) !== false) {
                $num = count($data);
                echo "<p> $num champs à la ligne $row: <br /></p>\n";
                $row++;
                for ($c = 0; $c < $num; $c++) {
                    echo $data[$c] . "<br />\n";
                    // TODO
                }
            }
            fclose($handle);
        }
        return new Response($handle);
    }
}
