<?php

namespace App\Controller;

use App\Entity\Utilisateurs;
use App\Form\UtilisateursType;
use App\Repository\UtilisateursRepository;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

/**
 * @Route("/utilisateurs")
 */
class UtilisateursController extends Controller
{
    /**
     * @Route("/", name="utilisateurs_index", methods="GET|POST")
     */
    public function index(UtilisateursRepository $utilisateursRepository, Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {

            $current = $request->request->get('current');
            $rowCount = $request->request->get('rowCount');
            $searchPhrase = $request->request->get('searchPhrase');
            $sort = $request->request->get('sort');

            $utilisateurs = $utilisateursRepository->findBySearchSort($searchPhrase, $sort);

            if ($searchPhrase != "") {
                $count =  count($utilisateurs->getQuery()->getResult());
            } else {
                $count = count($utilisateursRepository->findAll());
            }

            if ($rowCount != -1) {
                $min = ($current - 1) * $rowCount;
                $max = $min + $rowCount;
    
                $utilisateurs->setMaxResults($max)
                    ->setFirstResult($min);
            }

            $utilisateurs = $utilisateurs->getQuery()->getResult();

            $rows = array();
            foreach ($utilisateurs as $utilisateur) {
                $roles = $utilisateur->getRoles();
                $roles_string = "";
                foreach ($roles as $role) {
                    $roles_string = $role . ", " . $roles_string;
                }

                // enlève les deux derniers caractères
                $roles_string = substr($roles_string, 0, -2);


                // format de la derniere date de connexion
                if ($utilisateur->getLastLogin()) {
                    $lastLogin = date_diff(new \Datetime(), $utilisateur->getLastLogin());

                    $format = "Il y a ";
                    if ($lastLogin->y) {
                        $format = $format . "environ " . $lastLogin->y . "an(s) " . $lastLogin->m . "mois";
                    }
                    else if ($lastLogin->m) {
                        $format = $format . "environ " . $lastLogin->m . "mois " . $lastLogin->d . "jour(s)";
                    }

                    else if ($lastLogin->d) {
                        $format = $format . $lastLogin->d . "jour(s) " . $lastLogin->h . "heure(s)";
                    }
                    
                    else if ($lastLogin->h) {
                        $format = $format . $lastLogin->h . "h" . $lastLogin->i . "min";
                    }

                    else {
                        $format = $format . $lastLogin->i . "min";
                    }
                    
                    $lastLogin = $lastLogin->format($format);    
                
                } else {
                    $lastLogin = "Aucune connexion";
                }
                

                $row = [
                    "id" => $utilisateur->getId(),
                    "username" => $utilisateur->getUsername(),
                    "email" => $utilisateur->getEmail(),
                    "groupe" => $utilisateur->getGroupe(),
                    "lastLogin" => $lastLogin,
                    "roles" => $roles_string,
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

        return $this->render('utilisateurs/index.html.twig', ['utilisateurs' => $utilisateursRepository->findAll()]);
    }

    /**
     * @Route("/new", name="utilisateurs_new", methods="GET|POST")
     */
    public function new(Request $request, UserPasswordEncoderInterface $passwordEncoder): Response
    {
        $utilisateur = new Utilisateurs();
        $form = $this->createForm(UtilisateursType::class, $utilisateur);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $password = $passwordEncoder->encodePassword($utilisateur, $utilisateur->getPlainPassword());
            $utilisateur->setPassword($password);
            $em->persist($utilisateur);
            $em->flush();

            return $this->redirectToRoute('utilisateurs_index');
        }

        return $this->render('utilisateurs/new.html.twig', [
            'utilisateur' => $utilisateur,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="utilisateurs_show", methods="GET")
     */
    public function show(Utilisateurs $utilisateur): Response
    {
        return $this->render('utilisateurs/show.html.twig', ['utilisateur' => $utilisateur]);
    }

    /**
     * @Route("/{id}/edit", name="utilisateurs_edit", methods="GET|POST")
     */
    public function edit(Request $request, Utilisateurs $utilisateur, UserPasswordEncoderInterface $passwordEncoder): Response
    {
        $form = $this->createForm(UtilisateursType::class, $utilisateur);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $password = $passwordEncoder->encodePassword($utilisateur, $utilisateur->getPlainPassword());
            $utilisateur->setPassword($password);
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('utilisateurs_edit', ['id' => $utilisateur->getId()]);
        }

        return $this->render('utilisateurs/edit.html.twig', [
            'utilisateur' => $utilisateur,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="utilisateurs_delete", methods="DELETE")
     */
    public function delete(Request $request, Utilisateurs $utilisateur): Response
    {
        if ($this->isCsrfTokenValid('delete'.$utilisateur->getId(), $request->request->get('_token'))) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($utilisateur);
            $em->flush();
        }

        return $this->redirectToRoute('utilisateurs_index');
    }
}
