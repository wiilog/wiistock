<?php

namespace App\Controller;

use App\Entity\Ordres;
use App\Entity\Articles;
use App\Entity\ReferencesArticles;
use App\Entity\Receptions;
use App\Entity\Entrees;
use App\Entity\Transferts;
use App\Entity\Sorties;
use App\Entity\Preparations;
use App\Entity\Historiques;
use App\Entity\CommandesFournisseurs;
use App\Entity\CommandesClients;
use App\Entity\Entrepots;
use App\Entity\Fournisseurs;
use App\Entity\Zones;
use App\Entity\Contenu;

use App\Form\ReceptionsType;
use App\Form\FournisseursType;
use App\Form\ReferencesArticlesType;
use App\Form\ArticlesType;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use App\Repository\OrdresRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Tests\Fixtures\Reference;
use Symfony\Component\HttpFoundation\Session\Session;

use Doctrine\Common\Annotations\AnnotationReader;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Serializer\Serializer;
use App\Entity\Emplacements;

/**
 * @Route("/stock/ordre")
 */
class OrdreController extends Controller
{
    /**
     * @Route("/workflow", name="ordre_workflow")
     */
    public function workflow(OrdresRepository $ordresRepository, Request $request)
    {
        $session = $request->getSession();

        if ($request->isXmlHttpRequest()) {
            if (!$request->request->get('start')) {
                $type = $session->get('type');
                $auteur = $session->get('auteur');
                $from = $session->get('from');
                $to = $session->get('to');
            } else {
                $type = $request->request->get('type');
                $auteur = $request->request->get('auteur');
                $from = $request->request->get('from');
                $to = $request->request->get('to');
                $session->set('type', $request->request->get('type'));
                $session->set('auteur', $request->request->get('auteur'));
                $session->set('from', $request->request->get('from'));
                $session->set('to', $request->request->get('to'));
            }

            $from_datetime = \DateTime::createFromFormat("d/m/Y H:i:s", $from . " 00:00:00");
            $to_datetime = \DateTime::createFromFormat("d/m/Y H:i:s", $to . " 23:59:59");
            $ordres = $ordresRepository->findOrdresByFilters($type, $auteur, $from_datetime, $to_datetime);

            $data = array();
            foreach ($ordres as $ordre) {
                $list = array();
                $ents = $ordre->getTransferts();
                if ($ents->isEmpty()) {
                    $ents = $ordre->getReceptions();
                }
                if ($ents->isEmpty()) {
                    $ents = $ordre->getPreparations();
                }
                foreach ($ents as $ent) {
                    array_push($list, $ent->getId());
                }
                $row = [
                    "id" => $ordre->getId(),
                    "statut" => $ordre->getStatut(),
                    "dateOrdre" => $ordre->getDateOrdre(),
                    "type" => $ordre->getType(),
                    "auteur" => $ordre->getAuteur()->getUsername(),
                    "list" => $list,
                ];
                array_push($data, $row);
            }
            return new JsonResponse($data);
        }

        return $this->render('ordre/workflow.html.twig', [
            'controller_name' => 'OrdreController',
            "f_type" => $session->get('type'),
            "f_auteur" => $session->get('auteur'),
            "f_from" => $session->get('from'),
            "f_to" => $session->get('to')
        ]);
    }

    /**
     * @Route("/detail", name="ordre_details")
     */
    public function details(OrdresRepository $ordresRepository, Request $request)
    {
        if ($request->isXmlHttpRequest()) {
            $id = $request->request->get('id');
            $type = $request->request->get('type');

            if (strcmp($type, "reception") == 0) {
                $detail = $this->getDoctrine()->getRepository(Receptions::class)->findOneBy(['id' => $id]);
            }
            if (strcmp($type, "preparation") == 0) {
                $detail = $this->getDoctrine()->getRepository(Preparations::class)->findOneBy(['id' => $id]);
            }
            if (strcmp($type, "transfert") == 0) {
                $detail = $this->getDoctrine()->getRepository(Transferts::class)->findOneBy(['id' => $id]);
            }
            if (strcmp($type, "sortie") == 0) {
                $detail = $this->getDoctrine()->getRepository(Sorties::class)->findOneBy(['id' => $id]);
            }
            if (strcmp($type, "entree") == 0) {
                $detail = $this->getDoctrine()->getRepository(Entrees::class)->findOneBy(['id' => $id]);
            }

            $data = array();
            $contenus = $detail->getContenus();
            foreach ($contenus as $contenu) {
                $c_emplacement = $contenu->getEmplacement();
                if ($c_emplacement) {
                    $c_rack = $c_emplacement->getRacks();
                    $c_travee = $c_rack->getTravees();
                    $c_allee = $c_travee->getAllees();
                    $c_entrepot = $c_allee->getEntrepots();
                } else {
                    $c_rack = $c_travee = $c_allee = $c_entrepot = $c_emplacement;
                }

                $article = $contenu->getArticles();
                $emplacement = $article->getEmplacement();
                if ($emplacement) {
                    $rack = $emplacement->getRacks();
                    $travee = $rack->getTravees();
                    $allee = $travee->getAllees();
                    $entrepot = $allee->getEntrepots();
                } else {
                    $rack = $travee = $allee = $entrepot = $emplacement;
                }
                $row = [
                    "id" => $article->getId(),
                    "libelle" => $article->getLibelleCEA(),
                    "reference" => $article->getReferenceCEA(),
                    "quantite" => $article->getQuantite(),
                    "statut" => $article->getStatut(),
                    $init = [
                        "emplacement" => $emplacement,
                        "rack" => $rack,
                        "travee" => $travee,
                        "allee" => $allee,
                        "entrepot" => $entrepot,
                        "quai" => $article->getQuai(),
                        "zone" => $article->getZone(),
                    ],
                    $final = [
                        "emplacement" => json_encode($c_emplacement),
                        "rack" => $c_rack,
                        "travee" => $c_travee,
                        "allee" => $c_allee,
                        "entrepot" => $c_entrepot,
                        "quai" => $contenu->getQuai(),
                        "zone" => $contenu->getZone(),
                    ],
                ];
                array_push($data, $row);
            }
            return new JsonResponse($data);
        }
    }

    /**
     * @Route("/creation", name="ordre_creation")
     */
    public function creation()
    {
        $form_ref = $this->createForm(ReferencesArticlesType::class, new ReferencesArticles());
        $form_art = $this->createForm(ArticlesType::class, new Articles());

        return $this->render('ordre/creation.html.twig', [
            'controller_name' => 'OrdreController',
            'form_ref' => $form_ref->createView(),
            'form_art' => $form_art->createView(),
            'emplacements' => $this->getEmplacements(),
        ]);
    }

    /**
     * @Route("/reception", name="ordre_reception")
     */
    public function reception()
    {
        $form_rec = $this->createForm(ReceptionsType::class, new Receptions());
        $form_fou = $this->createForm(FournisseursType::class, new Fournisseurs());
        $form_ref = $this->createForm(ReferencesArticlesType::class, new ReferencesArticles());
        $form_art = $this->createForm(ArticlesType::class, new Articles());

        return $this->render('ordre/ordre_reception.html.twig', [
            'controller_name' => 'OrdreController',
            'form_rec' => $form_rec->createView(),
            'form_fou' => $form_fou->createView(),
            'form_ref' => $form_ref->createView(),
            'form_art' => $form_art->createView(),
            'emplacements' => $this->getEmplacements(),
        ]);
    }

    /**
     * @Route("/preparation", name="ordre_preparation")
     */
    public function preparation()
    {
        $form_ref = $this->createForm(ReferencesArticlesType::class, new ReferencesArticles());
        $form_art = $this->createForm(ArticlesType::class, new Articles());

        return $this->render('ordre/ordre_preparation.html.twig', [
            'controller_name' => 'OrdreController',
            'form_ref' => $form_ref->createView(),
            'form_art' => $form_art->createView(),
            'emplacements' => $this->getEmplacements(),
        ]);
    }

    /**
     * @Route("/transfert", name="ordre_transfert")
     */
    public function transfert()
    {
        $form_ref = $this->createForm(ReferencesArticlesType::class, new ReferencesArticles());
        $form_art = $this->createForm(ArticlesType::class, new Articles());

        return $this->render('ordre/ordre_transfert.html.twig', [
            'controller_name' => 'OrdreController',
            'form_ref' => $form_ref->createView(),
            'form_art' => $form_art->createView(),
            'emplacements' => $this->getEmplacements(),
        ]);
    }

    private function getEmplacements()
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $normalizer = new ObjectNormalizer($classMetadataFactory);
        $serializer = new Serializer([$normalizer], [new JsonEncode()]);

        $entrepots = $this->getDoctrine()->getManager()->getRepository(Entrepots::class)->findAll();
        $data = $serializer->serialize($entrepots, 'json', array('groups' => array('emplacements')));
        return $data;
    }

    /**
     * @Route("/add/reception", name="ordre_reception_add", methods="GET|POST")
     */
    public function manualReception(Request $request) : Response
    {
        $data = $request->request->get('data');

        if ($request->isXmlHttpRequest()) {
            $em = $this->getDoctrine()->getManager();

            $ordre = new Ordres();
            $ordre->setStatut('En attente');
            $ordre->setType('reception');
            $ordre->setDateOrdre(new \DateTime());
            $ordre->setAuteur($this->getUser());
            $em->persist($ordre);
            $em->flush();

            $historique = new Historiques();
            $historique->setDate(new \DateTime());
            $historique->setType('reception');
            $em->persist($historique);
            $em->flush();

            dump($data);

            for ($i = 0; $i < count($data); $i++) {
                if ($i == 0) {
                    $reception = new Receptions();
                    $reception->setStatut('En cours');
                    $reception->addHistorique($historique);
                    $reception->setDateAuPlusTot(date_create_from_format('d/m/Y:H:i:s', $data[$i]["date_au_plus_tot"] . ':00:00:00'));
                    $reception->setDateAuPlusTard(date_create_from_format('d/m/Y:H:i:s', $data[$i]["date_au_plus_tard"] . ':00:00:00'));
                    $reception->setDatePrevue(date_create_from_format('d/m/Y:H:i:s', $data[$i]["date_prévue"] . ':00:00:00'));
                    $reception->setCommentaire($data[$i]["commentaire"]);
                    $reception->setNomCEA($data[$i]["nom_CEA"]);
                    $reception->setPrenomCEA($data[$i]["prenom_CEA"]);
                    $reception->setMailCEA($data[$i]["mail_CEA"]);
                    $reception->setCodeRefTransporteur($data[$i]["code_ref_transporteur"]);
                    $reception->setNomTransporteur($data[$i]["nom_transporteur"]);
                    if ($data[$i]["fournisseur"] != "") {
                        $reception->setFournisseur($this->getDoctrine()->getRepository(Fournisseurs::class)->findOneBy(['id' => $data[$i]["fournisseur"]]));
                    } else {
                        $fournisseur = new Fournisseurs();
                        $fournisseur->setNom($data[$i]["fournisseur_nom"]);
                        $fournisseur->setCodeReference($data[$i]["fournisseur_code_reference"]);
                        $em->persist($fournisseur);
                        $em->flush();
                        $reception->setFournisseur($fournisseur);
                    }
                    $em->persist($reception);
                    $em->flush();
                } else {
                    $contenu = new Contenu();
                    $contenu->setQuantite($data[$i]["quantite"]);
                    $contenu->setReception($reception);
                    $l_empl = explode(' ', $data[$i]["emplacement"]);
                    if ($data[$i]["emplacement"] != "" && $l_empl[1] == "true") {
                        $contenu->setZone($this->getDoctrine()->getRepository(Zones::class)->findOneBy(['id' => $l_empl[2]]));
                    } else if ($data[$i]["emplacement"] != "") {
                        $contenu->setEmplacement($this->getDoctrine()->getRepository(Emplacements::class)->findOneBy(['id' => $l_empl[5]]));
                    }
                    $em->persist($contenu);
                    $em->flush();

                    $article = new Articles();
                    $article->setStatut('En attente');
                    $article->setEtat('Non défini');
                    $article->setReference($this->getDoctrine()->getRepository(ReferencesArticles::class)->findOneBy(['id' => $data[$i]["ref"]]));
                    $article->setLibelleCEA($data[$i]["libelle"]);
                    $article->setReferenceCEA($data[$i]["reference"]);
                    $article->setQuantite(0);
                    $em->persist($article);
                    $em->flush();

                    $contenu->setArticles($article);
                }
            }

            $ordre->addReception($reception);
            $em->flush();

            return new JsonResponse(1);
        }
        throw new NotFoundHttpException('404 not found');
    }

    private function manualPreparation($ordre, $data)
    {
        // $em = $this->getDoctrine()->getManager();

        // $historique = new Historiques();
        // $historique->setDateDebut(new \DateTime());
        // $historique->setTypeMouvement('sortie');
        // $em->persist($historique);
        // $em->flush();

        // $sortie = new Sortie();
        // $sortie->setStatut('En cours');
        // $sortie->setHistorique($historique);
        // $em->persist($sortie);
        // $em->flush();

        // for ($i = 0; $i < count($data); $i++) {
        //     $article = $this->getDoctrine()->getRepository(Articles::class)->findOneBy(['id' => $data[$i]["id"]]);
        //     $article->setStatut('En attente');
        //     $em->flush();
        //     $sortie->addArticle($article);
        // }

        // $em->persist($sortie);
        // $em->flush();

        // $ordre->addSortie($sortie);
        // $em->flush();
    }

    private function isSameCommande($array, $num)
    {
        foreach ($array as $key => $value) {
            list($date_comptabilisation, $n_document, $libelle, $code_magasin, $consigne_entree, $emplacement, $consigne_sortie, $n, $designation, $n_affaire, $code_tache, $quantite, $n_commande) = explode(";", $value[0]);
            if (strcmp($n_commande, $num) == 0) {
                return ($key);
            }
        }
        return -1;
    }

    private function sortAffaire($array)
    {
        $i = 1;
        $j = 0;
        $data = array();

        while ($array[$i][0]) {
            list($date_comptabilisation, $n_document, $libelle, $code_magasin, $consigne_entree, $emplacement, $consigne_sortie, $n, $designation, $n_affaire, $code_tache, $quantite, $n_commande) = explode(";", $array[$i][0]);
            if (($n = $this->isSameCommande($data, $n_commande)) != -1) {
                $data[$n][] = $array[$i][0];
            } else {
                $data[$j] = array($array[$i][0]);
                $j++;
            }
            $i++;
        }

        return ($data);
    }

    /**
     * @Route("/import/csv", name="import_csv", methods="GET|POST")
     */
    public function import_csv(Request $request) : Response
    {
        if ($request->isXmlHttpRequest()) {
            $file = $request->files->get('file');
            $csv = file_get_contents($file);
            $array = array_map("str_getcsv", explode("\n", $csv));
            return new JsonResponse($array);
        }
        throw new NotFoundHttpException('404 not found');
    }

    private function importPreparation(Ordres $ordre, $data)
    {
        $file = $request->files->get('file');
        $csv = file_get_contents($file);
        $array = array_map("str_getcsv", explode("\n", $csv));

        $data = $this->sortAffaire($array);
        foreach ($data as $key => $value) {
            $response = $this->preparation($ordre, $data[$key]);
        }

        // $em = $this->getDoctrine()->getManager();
        // list($date_comptabilisation, $n_document, $libelle, $code_magasin, $consigne_entree, $emplacement, $consigne_sortie, $n, $designation, $n_affaire, $code_tache, $quantite, $n_commande) = explode(";", $data[0]);

        // $historique = new Historiques();
        // $historique->setDateDebut(new \DateTime());
        // $historique->setTypeMouvement('preparation');
        // $em->persist($historique);
        // $em->flush();

        // $commande_client = new CommandesClients();
        // $commande_client->setDateCommande(new \DateTime());
        // $commande_client->setNCommande($n_commande);
        // $commande_client->setNAffaire($n_affaire);
        // $em->persist($commande_client);
        // $em->flush();

        // $preparation = new Preparations();
        // $preparation->setStatut('En cours');
        // $preparation->setCommandeClient($commande_client);
        // $preparation->setHistorique($historique);
        // $em->persist($preparation);
        // $em->flush();

        // $i = 0;
        // while ($i < count($data)) {
        //     list($date_comptabilisation, $n_document, $libelle, $code_magasin, $consigne_entree, $emplacement, $consigne_sortie, $n, $designation, $n_affaire, $code_tache, $quantite, $n_commande) = explode(";", $data[0]);
        //     $article = $this->getDoctrine()->getRepository(Articles::class)->findOneBy(['n' => $n]);
        //     if ($preparation === null) {
        //         return 1;
        //     }
        //     $article->addPreparation($preparation);
        //     $em->flush();
        //     $i += 1;
        // }

        // $ordre->addPreparation($preparation);
        // $em->flush();
    }

    /**
     * @Route("/import/reception", name="ordre_import_reception", methods="GET|POST")
     */
    private function importReception(Ordres $ordre, $data)
    {
        // $em = $this->getDoctrine()->getManager();
        // list($date_comptabilisation, $n_document, $libelle, $code_magasin, $consigne_entree, $emplacement, $consigne_sortie, $n, $designation, $n_affaire, $code_tache, $quantite, $n_commande) = explode(";", $data[0]);

        // $historique = new Historiques();
        // $historique->setDateDebut(new \DateTime());
        // $historique->setTypeMouvement('reception');
        // $em->persist($historique);
        // $em->flush();

        // $commande_fournisseur = new CommandesFournisseurs();
        // $commande_fournisseur->setDateCommande(new \DateTime());
        // $commande_fournisseur->setNCommande($n_commande);
        // $commande_fournisseur->setNAffaire($n_affaire);
        // $em->persist($commande_fournisseur);
        // $em->flush();

        // $reception = new Receptions();
        // $reception->setStatut('En cours');
        // $reception->setCommandeFournisseur($commande_fournisseur);
        // $reception->setHistorique($historique);

        // $i = 0;
        // while ($i < count($data)) {
        //     list($date_comptabilisation, $n_document, $libelle, $code_magasin, $consigne_entree, $emplacement, $consigne_sortie, $n, $designation, $n_affaire, $code_tache, $quantite, $n_commande) = explode(";", $data[$i]);
        //     $article = new Articles();
        //     $article->setStatut('En attente');
        //     $article->setEtat('Non défini');
        //     $article->setDateComptabilisation(date_create_from_format('d/m/Y', $date_comptabilisation));
        //     $article->setNDocument($n_document);
        //     $article->setLibelle($libelle);
        //     $article->setCodeMagasin($code_magasin);
        //     $article->setConsigneEntree($consigne_entree);
        //     $article->setEmplacementReel($emplacement);
        //     $article->setConsigneSortie($consigne_sortie);
        //     $article->setN($n);
        //     $article->setDesignation($designation);
        //     $article->setCodeTache($code_tache);
        //     $article->setQuantite($quantite);
        //     $em->persist($article);
        //     $em->flush();
        //     $reception->addArticle($article);
        //     $i += 1;
        // }

        // $em->persist($reception);
        // $em->flush();

        // $ordre->addReception($reception);
        // $em->flush();
    }

    private function importTransfert()
    {
    }
}
