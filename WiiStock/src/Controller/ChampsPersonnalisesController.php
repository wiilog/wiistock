<?php

namespace App\Controller;

use App\Entity\ChampsPersonnalises;
use App\Form\ChampsPersonnalisesType;
use App\Repository\ChampsPersonnalisesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @Route("/super/admin/champs_personnalises")
 */
class ChampsPersonnalisesController extends Controller
{
    /**
     * @Route("/", name="champs_personnalises_index", methods="GET")
     */
    public function index(ChampsPersonnalisesRepository $champsPersonnalisesRepository) : Response
    {
        //[{"nom": "Custom 1", "valeur": "test"}, {"nom": "Custom 2", "valeur": ""}]
        //[{"champ": [{"nom": "Custom 1", "valeur": "test"}]}, {"champ": [{"nom": "Custom 2", "valeur": ""}]}]
        // {"id": 4, "name": "Betty"}

        $champsPersonnalise = new ChampsPersonnalises();
        $form = $this->createForm(ChampsPersonnalisesType::class, $champsPersonnalise);

        $cible = 'references_articles';
        $field = '"8"';
        $value = '"tigre"';
        // $field = '"7"';
        // $value = '"canard"';

        $em = $this->getDoctrine()->getManager();
        // $query = $em->createQuery('SELECT a FROM App\Entity\\' . $cible . ' a WHERE ' . $value . ' IN ( SELECT value FROM OPENJSON(Col, $.' . $field);
        // $res = $query->getResult();
        // $rawSql = 'SELECT a FROM App\Entity\\' . $cible . ' a WHERE ' . $value . ' IN ( SELECT value FROM OPENJSON(Col, $.' . $field .');';
        // $rawSql = "SELECT JSON_EXTRACT(custom, '$.name') AS nom FROM references_articles WHERE JSON_EXTRACT(custom, '$.id') > 3";
        // $rawSql = "SELECT custom->'$.name' AS nom FROM references_articles WHERE custom->'$.id' > 4";
        // $rawSql = "SELECT custom->'$[*].champ.valeur' AS valeur FROM references_articles WHERE JSON_EXTRACT(custom, '$[*].champ.nom') = 'Custom'";
        // $rawSql = "SELECT JSON_EXTRACT(custom, '$**.valeur') As valeur FROM references_articles";
        // WHERE JSON_CONTAINS(custom, '\"Custom 1\"', '$.champ.nom')
        // $rawSql = "SELECT custom->>'$[*].champ[*].valeur' AS valeur FROM references_articles WHERE custom->>'$[0].champ[*].valeur[*]' = '\"1\"'";
        // $rawSql = "SELECT custom->>'$[*].champ[*].valeur' AS valeur FROM references_articles WHERE JSON_CONTAINS_PATH(custom, 'one', '$.champ.nom') = '1'";
        // $rawSql = "SELECT custom->>'$[0]." . $field . "' AS nom FROM references_articles WHERE JSON_CONTAINS(custom, '" . $value . "', '$[*]." . $field . "') ";


        $rawSql = "SELECT custom->>'$**." . $field . "' AS nom FROM references_articles WHERE JSON_CONTAINS(custom->'$[*]." . $field . "', '" . $value . "') ";
        // $rawSql = "SELECT id AS id FROM references_articles WHERE JSON_CONTAINS(custom->'$[*]." . $field . "', '" . $value . "') ";

        $res = $em->getConnection()->prepare($rawSql);
        $res->execute();
        $res = $res->fetchAll();
        // dump($res); //res[i]['id'];

        // dump($res);

        return $this->render('champs_personnalises/index.html.twig', [
            'champs_personnalises' => $champsPersonnalisesRepository->findAll(),
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/new", name="champs_personnalises_new", methods="GET|POST")
     */
    public function new(Request $request) : Response
    {
        $champsPersonnalise = new ChampsPersonnalises();
        $form = $this->createForm(ChampsPersonnalisesType::class, $champsPersonnalise);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($champsPersonnalise);
            $em->flush();

            return $this->redirectToRoute('champs_personnalises_index');
        }

        return $this->render('champs_personnalises/new.html.twig', [
            'champs_personnalise' => $champsPersonnalise,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/add", name="champs_personnalises_add", methods="GET|POST")
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

            $champ = new ChampsPersonnalises();
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
            $rawSql = "UPDATE references_articles SET custom = JSON_ARRAY_INSERT(custom, '$[0]', JSON_OBJECT(" . $id . ", " . $defaut . ")) ";

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
     * @Route("/{id}", name="champs_personnalises_show", methods="GET")
     */
    public function show(ChampsPersonnalises $champsPersonnalise) : Response
    {
        return $this->render('champs_personnalises/show.html.twig', ['champs_personnalise' => $champsPersonnalise]);
    }

    /**
     * @Route("/{id}/edit", name="champs_personnalises_edit", methods="GET|POST")
     */
    public function edit(Request $request, ChampsPersonnalises $champsPersonnalise) : Response
    {
        $form = $this->createForm(ChampsPersonnalisesType::class, $champsPersonnalise);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('champs_personnalises_edit', ['id' => $champsPersonnalise->getId()]);
        }

        return $this->render('champs_personnalises/edit.html.twig', [
            'champs_personnalise' => $champsPersonnalise,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="champs_personnalises_delete", methods="DELETE")
     */
    public function delete(Request $request, ChampsPersonnalises $champsPersonnalise) : Response
    {
        if ($this->isCsrfTokenValid('delete' . $champsPersonnalise->getId(), $request->request->get('_token'))) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($champsPersonnalise);
            $em->flush();
        }

        return $this->redirectToRoute('champs_personnalises_index');
    }

    /**
     * @Route("/remove", name="champs_personnalises_remove", methods="GET|POST")
     */
    public function remove(Request $request, ChampsPersonnalisesRepository $champsPersonnalisesRepository) : Response
    {
        if ($request->isXmlHttpRequest()) {
            $em = $this->getDoctrine()->getManager();
            $champsPersonnalise = $champsPersonnalisesRepository->findOneBy(['id' => $request->request->get('id')]);

            // $rawSql = "UPDATE references_articles SET custom = JSON_REMOVE(custom, '$[0]') ";
            $id = '"29"';
            $test = '"grenouille"';
            // $rawSql = "SELECT JSON_REMOVE(custom, '$." . $id . "')";
            // $rawSql = "SELECT `custom`, JSON_REMOVE(`custom`, JSON_UNQUOTE(JSON_SEARCH(`custom`, 'all', " . $id . "))) FROM `references_articles`";
            $rawSql = "SELECT JSON_UNQUOTE(JSON_SEARCH(`custom`, 'all', " . $test . ")) FROM `references_articles`";
            // $rawSql = "SELECT JSON_UNQUOTE(JSON_EXTRACT(`custom`,'$[*]." . $id . "')) FROM `references_articles`";
            $rest = $em->getConnection()->prepare($rawSql);
            $rest->execute();
            $rest = $rest->fetchAll();
            dump($rest);

            // $em->remove($champsPersonnalise);
            $em->flush();

            return new JsonResponse();
        }
        throw new NotFoundHttpException('404 not found');
    }
}

