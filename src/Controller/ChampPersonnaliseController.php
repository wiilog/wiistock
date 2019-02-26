<?php

namespace App\Controller;

use App\Entity\ChampPersonnalise;
use App\Form\ChampPersonnaliseType;
use App\Repository\ChampPersonnaliseRepository;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @Route("/super/admin/champ_personnalise")
 */
class ChampPersonnaliseController extends Controller
{
    /**
     * @Route("/", name="champ_personnalise_index", methods="GET")
     */
    public function index(ChampPersonnaliseRepository $champPersonnaliseRepository) : Response
    {
        //[{"nom": "Custom 1", "valeur": "test"}, {"nom": "Custom 2", "valeur": ""}]
        //[{"champ": [{"nom": "Custom 1", "valeur": "test"}]}, {"champ": [{"nom": "Custom 2", "valeur": ""}]}]
        // {"id": 4, "name": "Betty"}

        $champPersonnalise = new ChampPersonnalise();
        $form = $this->createForm(ChampPersonnaliseType::class, $champPersonnalise);

        $cible = 'reference_article';
        $field = '"8"';
        $value = '"tigre"';
        // $field = '"7"';
        // $value = '"canard"';

        $em = $this->getDoctrine()->getManager();
        // $query = $em->createQuery('SELECT a FROM App\Entity\\' . $cible . ' a WHERE ' . $value . ' IN ( SELECT value FROM OPENJSON(Col, $.' . $field);
        // $res = $query->getResult();
        // $rawSql = 'SELECT a FROM App\Entity\\' . $cible . ' a WHERE ' . $value . ' IN ( SELECT value FROM OPENJSON(Col, $.' . $field .');';
        // $rawSql = "SELECT JSON_EXTRACT(custom, '$.name') AS nom FROM reference_article WHERE JSON_EXTRACT(custom, '$.id') > 3";
        // $rawSql = "SELECT custom->'$.name' AS nom FROM reference_article WHERE custom->'$.id' > 4";
        // $rawSql = "SELECT custom->'$[*].champ.valeur' AS valeur FROM reference_article WHERE JSON_EXTRACT(custom, '$[*].champ.nom') = 'Custom'";
        // $rawSql = "SELECT JSON_EXTRACT(custom, '$**.valeur') As valeur FROM reference_article";
        // WHERE JSON_CONTAINS(custom, '\"Custom 1\"', '$.champ.nom')
        // $rawSql = "SELECT custom->>'$[*].champ[*].valeur' AS valeur FROM reference_article WHERE custom->>'$[0].champ[*].valeur[*]' = '\"1\"'";
        // $rawSql = "SELECT custom->>'$[*].champ[*].valeur' AS valeur FROM reference_article WHERE JSON_CONTAINS_PATH(custom, 'one', '$.champ.nom') = '1'";
        // $rawSql = "SELECT custom->>'$[0]." . $field . "' AS nom FROM reference_article WHERE JSON_CONTAINS(custom, '" . $value . "', '$[*]." . $field . "') ";


        $rawSql = "SELECT custom->>'$**." . $field . "' AS nom FROM reference_article WHERE JSON_CONTAINS(custom->'$[*]." . $field . "', '" . $value . "') ";
        // $rawSql = "SELECT id AS id FROM reference_article WHERE JSON_CONTAINS(custom->'$[*]." . $field . "', '" . $value . "') ";

        $res = $em->getConnection()->prepare($rawSql);
        $res->execute();
        $res = $res->fetchAll();
        // dump($res); //res[i]['id'];

        // dump($res);

        return $this->render('champ_personnalise/index.html.twig', [
            'champ_personnalise' => $champPersonnaliseRepository->findAll(),
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/new", name="champ_personnalise_new", methods="GET|POST")
     */
    public function new(Request $request) : Response
    {
        $champPersonnalise = new ChampPersonnalise();
        $form = $this->createForm(ChampPersonnaliseType::class, $champPersonnalise);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($champPersonnalise);
            $em->flush();

            return $this->redirectToRoute('champ_personnalise_index');
        }

        return $this->render('champ_personnalise/new.html.twig', [
            'champ_personnalise' => $champPersonnalise,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/add", name="champ_personnalise_add", methods="GET|POST")
     */
    public function add(Request $request) : Response
    {
        if ($request->isXmlHttpRequest()) {
            $em = $this->getDoctrine()->getManager();

            $nom = $request->request->get('nom');
            $type = $request->request->get('type');
            $cible = $request->request->get('cible');
            $unicite = $request->request->get('unicite');
            $nullable = $request->request->get('nullable');
            $defaut = $request->request->get('defaut');

            $champ = new ChampPersonnalise();
            $champ->setNom($nom);
            $champ->setType($type);
            $champ->setEntiteCible($cible);
            $champ->setUnicite($unicite);
            $champ->setNullable($nullable);
            $champ->setValeurDefaut($defaut);
            $em = $this->getDoctrine()->getManager();
            $em->persist($champ);
            $em->flush();

            $id = $champ->getId();
            $defaut = '"' . $defaut . '"';
            $rawSql = "UPDATE reference_article SET custom = JSON_ARRAY_INSERT(custom, '$[0]', JSON_OBJECT(" . $id . ", " . $defaut . ")) ";

            $rest = $em->getConnection()->prepare($rawSql);
            $rest->execute();

                // $json = $re->getCustom() == null ? [] : $re->getCustom();
                // $array = array(
                //     $champ->getId() => $defaut,
                // );
                // array_push($json, $array);
                // $re->setCustom($json);

            $em->flush();

            return new JsonResponse($champ->getId());
        }
        throw new NotFoundHttpException('404 not found');
    }

    /**
     * @Route("/{id}", name="champ_personnalise_show", methods="GET")
     */
    public function show(ChampPersonnalise $champPersonnalise) : Response
    {
        return $this->render('champ_personnalise/show.html.twig', ['champ_personnalise' => $champPersonnalise]);
    }

    /**
     * @Route("/{id}/edit", name="champ_personnalise_edit", methods="GET|POST")
     */
    public function edit(Request $request, ChampPersonnalise $champPersonnalise) : Response
    {
        $form = $this->createForm(ChampPersonnaliseType::class, $champPersonnalise);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('champ_personnalise_edit', ['id' => $champPersonnalise->getId()]);
        }

        return $this->render('champ_personnalise/edit.html.twig', [
            'champ_personnalise' => $champPersonnalise,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="champ_personnalise_delete", methods="DELETE")
     */
    public function delete(Request $request, ChampPersonnalise $champPersonnalise) : Response
    {
        if ($this->isCsrfTokenValid('delete' . $champPersonnalise->getId(), $request->request->get('_token'))) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($champPersonnalise);
            $em->flush();
        }

        return $this->redirectToRoute('champ_personnalise_index');
    }

    /**
     * @Route("/remove", name="champ_personnalise_remove", methods="GET|POST")
     */
    public function remove(Request $request, ChampPersonnaliseRepository $champPersonnaliseRepository) : Response
    {
//        if ($request->isXmlHttpRequest()) {
//            $em = $this->getDoctrine()->getManager();
//            $champsPersonnalise = $champPersonnaliseRepository->findOneBy(['id' => $request->request->get('id')]);
//
//            $rawSql =
//            " SELECT JSON_SEARCH(`custom`, 'all', `value`) FROM (SELECT JSON_EXTRACT(`value`, '$[0]') FROM (SELECT JSON_EXTRACT(custom, '$[*]." . $id . "') AS `value` FROM `reference_article`) AS `value`) AS `value`, `reference_article` ";
//
//            $rest = $em->getConnection()->prepare($rawSql);
//            $rest->execute();
//            $rest = $rest->fetchAll();
//
//            // $em->remove($champsPersonnalise);
//            $em->flush();
//
//            return new JsonResponse();
//        }
//        throw new NotFoundHttpException('404 not found');
    }
}

