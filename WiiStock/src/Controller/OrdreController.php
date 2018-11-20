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
use App\Entity\Zones;

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
                if ($ents->isEmpty()) {
                    $ents = $ordre->getSorties();
                }
                if ($ents->isEmpty()) {
                    $ents = $ordre->getEntrees();
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
            $articles = $detail->getArticles();
            foreach ($articles as $article) {
                $emplacement = $article->getEmplacement();
                if ($emplacement) {
                    $rack = $emplacement->getRack()->getNom();
                    $travee = $emplacement->getRack()->getTravee()->getNom();
                    $allee = $emplacement->getRack()->getTravee()->getAllee()->getNom();
                    $entrepot = $emplacement->getRack()->getTravee()->getAllee()->getEntrepot()->getNom();
                    $emplacement = $emplacement->getNom();
                } else {
                    $rack = $travee = $allee = $entrepot = $emplacement = '';
                }
                $row = [
                    "libelle" => $article->getLibelle(),
                    "numero" => $article->getN(),
                    "designation" => $article->getDesignation(),
                    "tache" => $article->getCodeTache(),
                    "document" => $article->getNDocument(),
                    "quantite" => $article->getQuantite(),
                    $init = [
                        "emplacement" => $emplacement,
                        "rack" => $rack,
                        "travee" => $travee,
                        "allee" => $allee,
                        "entrepot" => $entrepot,
                    ],
                    $final = [
                        "magasin" => $article->getCodeMagasin(),
                        "entree" => $article->getConsigneEntree(),
                        "emplacement" => $article->getEmplacementReel(),
                        "sortie" => $article->getConsigneSortie(),
                        "" => "",
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

    /**
     * @Route("/add", name="ordre_add", methods="GET|POST")
     */
    public function add(Request $request) : Response
    {
        if ($request->isXmlHttpRequest()) {

            $ordre = new Ordres();
            $ordre->setStatut('En attente');
            $ordre->setType($request->request->get('ordre'));
            $ordre->setDateOrdre(new \DateTime());
            $ordre->setAuteur($this->getUser());

            $em = $this->getDoctrine()->getManager();
            $em->persist($ordre);
            $em->flush();

            $response = 0;

            if (strcmp($request->request->get('toggle'), "false") == 0) {
                $file = $request->files->get('file');
                $csv = file_get_contents($file);
                $array = array_map("str_getcsv", explode("\n", $csv));
                switch ($request->request->get('ordre')) {
                    case "preparation":
                        $data = $this->sortAffaire($array);
                        foreach ($data as $key => $value) {
                            $response = $this->preparation($ordre, $data[$key]);
                        }
                        break;
                    case "reception":
                        $data = $this->sortAffaire($array);
                        foreach ($data as $key => $value) {
                            $response = $this->reception($ordre, $data[$key]);
                        }
                        break;
                    case "transfert":
                        $this->transfert();
                        break;
                };
            } else {
                $data = $request->request->get('data');
                switch ($request->request->get('ordre')) {
                    case "preparation":
                        $this->manualPreparation($ordre, $data);
                        break;
                    case "reception":
                        $this->manualReception($ordre, $data);
                        break;
                    case "transfert":
                        $this->manualTransfert($ordre, $data);
                        break;
                }
                return new JsonResponse($response);
            }
            throw new NotFoundHttpException('404 not found');
        }
    }

    private function manualReception($ordre, $data)
    {
        $em = $this->getDoctrine()->getManager();

        $historique = new Historiques();
        $historique->setDateDebut(new \DateTime());
        $historique->setTypeMouvement('entree');
        $em->persist($historique);
        $em->flush();

        $reception = new Receptions();
        $reception->setStatut('En cours');
        $reception->setHistorique($historique);
        $em->persist($reception);
        $em->flush();

        for ($i = 0; $i < count($data); $i++) {
            $article = new Articles();
            $article->setStatut('En attente');
            $article->setEtat('Non défini');
            $article->setReference($this->getDoctrine()->getRepository(ReferencesArticles::class)->findOneBy(['id' => $data[$i]["ref"]]));
            $article->setDesignation($data[$i]["designation"]);
            $article->setCommentaire($data[$i]["commentaire"]);
            $article->setQuantite($data[$i]["quantite"]);
            $article->setValeur($data[$i]["valeur"]);
            $l_empl = explode(' ', $data[$i]["emplacement"]);
            if ($l_empl[1] == "true") {
                $article->setZone($this->getDoctrine()->getRepository(Zones::class)->findOneBy(['id' => $l_empl[2]]));
            } else {
                $article->setEmplacement($this->getDoctrine()->getRepository(Emplacements::class)->findOneBy(['id' => $l_empl[5]]));
            }
            $em->persist($article);
            $em->flush();
            $reception->addArticle($article);
        }

        $em->persist($reception);
        $em->flush();

        $ordre->addReception($reception);
        $em->flush();
    }

    private function manualPreparation($ordre, $data)
    {
        $em = $this->getDoctrine()->getManager();

        $historique = new Historiques();
        $historique->setDateDebut(new \DateTime());
        $historique->setTypeMouvement('sortie');
        $em->persist($historique);
        $em->flush();

        $sortie = new Sortie();
        $sortie->setStatut('En cours');
        $sortie->setHistorique($historique);
        $em->persist($sortie);
        $em->flush();

        for ($i = 0; $i < count($data); $i++) {
            $article = $this->getDoctrine()->getRepository(Articles::class)->findOneBy(['id' => $data[$i]["id"]]);
            $article->setStatut('En attente');
            $em->flush();
            $sortie->addArticle($article);
        }

        $em->persist($sortie);
        $em->flush();

        $ordre->addSortie($sortie);
        $em->flush();
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

    private function preparation(Ordres $ordre, $data)
    {
        $em = $this->getDoctrine()->getManager();
        list($date_comptabilisation, $n_document, $libelle, $code_magasin, $consigne_entree, $emplacement, $consigne_sortie, $n, $designation, $n_affaire, $code_tache, $quantite, $n_commande) = explode(";", $data[0]);

        $historique = new Historiques();
        $historique->setDateDebut(new \DateTime());
        $historique->setTypeMouvement('preparation');
        $em->persist($historique);
        $em->flush();

        $commande_client = new CommandesClients();
        $commande_client->setDateCommande(new \DateTime());
        $commande_client->setNCommande($n_commande);
        $commande_client->setNAffaire($n_affaire);
        $em->persist($commande_client);
        $em->flush();

        $preparation = new Preparations();
        $preparation->setStatut('En cours');
        $preparation->setCommandeClient($commande_client);
        $preparation->setHistorique($historique);
        $em->persist($preparation);
        $em->flush();

        $i = 0;
        while ($i < count($data)) {
            list($date_comptabilisation, $n_document, $libelle, $code_magasin, $consigne_entree, $emplacement, $consigne_sortie, $n, $designation, $n_affaire, $code_tache, $quantite, $n_commande) = explode(";", $data[0]);
            $article = $this->getDoctrine()->getRepository(Articles::class)->findOneBy(['n' => $n]);
            if ($preparation === null) {
                return 1;
            }
            $article->addPreparation($preparation);
            $em->flush();
            $i += 1;
        }

        $ordre->addPreparation($preparation);
        $em->flush();
    }

    private function reception(Ordres $ordre, $data)
    {
        $em = $this->getDoctrine()->getManager();
        list($date_comptabilisation, $n_document, $libelle, $code_magasin, $consigne_entree, $emplacement, $consigne_sortie, $n, $designation, $n_affaire, $code_tache, $quantite, $n_commande) = explode(";", $data[0]);

        $historique = new Historiques();
        $historique->setDateDebut(new \DateTime());
        $historique->setTypeMouvement('reception');
        $em->persist($historique);
        $em->flush();

        $commande_fournisseur = new CommandesFournisseurs();
        $commande_fournisseur->setDateCommande(new \DateTime());
        $commande_fournisseur->setNCommande($n_commande);
        $commande_fournisseur->setNAffaire($n_affaire);
        $em->persist($commande_fournisseur);
        $em->flush();

        $reception = new Receptions();
        $reception->setStatut('En cours');
        $reception->setCommandeFournisseur($commande_fournisseur);
        $reception->setHistorique($historique);

        $i = 0;
        while ($i < count($data)) {
            list($date_comptabilisation, $n_document, $libelle, $code_magasin, $consigne_entree, $emplacement, $consigne_sortie, $n, $designation, $n_affaire, $code_tache, $quantite, $n_commande) = explode(";", $data[$i]);
            $article = new Articles();
            $article->setStatut('En attente');
            $article->setEtat('Non défini');
            $article->setDateComptabilisation(date_create_from_format('d/m/Y', $date_comptabilisation));
            $article->setNDocument($n_document);
            $article->setLibelle($libelle);
            $article->setCodeMagasin($code_magasin);
            $article->setConsigneEntree($consigne_entree);
            $article->setEmplacementReel($emplacement);
            $article->setConsigneSortie($consigne_sortie);
            $article->setN($n);
            $article->setDesignation($designation);
            $article->setCodeTache($code_tache);
            $article->setQuantite($quantite);
            $em->persist($article);
            $em->flush();
            $reception->addArticle($article);
            $i += 1;
        }

        $em->persist($reception);
        $em->flush();

        $ordre->addReception($reception);
        $em->flush();
    }

    private function entree()
    {
    }

    private function sortie()
    {
    }

    private function transfert()
    {
    }
}
