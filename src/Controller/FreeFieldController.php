<?php

namespace App\Controller;

use App\Entity\Arrivage;
use App\Entity\Article;
use App\Entity\CategorieCL;
use App\Entity\FreeField;

use App\Entity\Collecte;
use App\Entity\Demande;
use App\Entity\Handling;
use App\Entity\Reception;
use App\Entity\ReferenceArticle;
use App\Entity\Type;
use App\Entity\Utilisateur;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @Route("/type")
 */
class FreeFieldController extends AbstractController {

    const CATEGORY_CL_TO_CLASSNAMES = [
        CategorieCL::RECEPTION => Reception::class,
        CategorieCL::ARTICLE => Article::class,
        CategorieCL::REFERENCE_ARTICLE => ReferenceArticle::class,
        CategorieCL::ARRIVAGE => Arrivage::class,
        CategorieCL::DEMANDE_COLLECTE => Collecte::class,
        CategorieCL::DEMANDE_LIVRAISON => Demande::class,
        CategorieCL::DEMANDE_HANDLING => Handling::class,
        CategorieCL::AUCUNE => null
    ];

    /**
     * @Route("/api/{id}", name="free_field_api", options={"expose"=true}, methods={"POST"})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param $id
     * @return Response
     * @throws Exception
     */
    public function api(Request $request,
                        EntityManagerInterface $entityManager,
                        $id): Response
    {
        if ($request->isXmlHttpRequest()) { //Si la requête est de type Xml
            $champLibreRepository = $entityManager->getRepository(FreeField::class);
            $champsLibres = $champLibreRepository->findByType($id);
            $rows = [];
            foreach ($champsLibres as $champLibre) {

                if ($champLibre->getTypage() === FreeField::TYPE_BOOL) {
                    $typageCLFr = 'Oui/Non';
                } elseif ($champLibre->getTypage() === FreeField::TYPE_NUMBER) {
                    $typageCLFr = 'Nombre';
                } elseif ($champLibre->getTypage() === FreeField::TYPE_TEXT) {
                    $typageCLFr = 'Texte';
                } elseif ($champLibre->getTypage() === FreeField::TYPE_LIST) {
                    $typageCLFr = 'Liste';
                } elseif ($champLibre->getTypage() === FreeField::TYPE_DATE) {
                    $typageCLFr = 'Date';
                } elseif ($champLibre->getTypage() === FreeField::TYPE_DATETIME) {
                    $typageCLFr = 'Date et heure';
                } elseif ($champLibre->getTypage() === FreeField::TYPE_LIST_MULTIPLE) {
                    $typageCLFr = 'Liste multiple';
                } else {
                    $typageCLFr = '';
                }

                $defaultValue = $champLibre->getDefaultValue();
                if ($champLibre->getTypage() == FreeField::TYPE_BOOL) {
                    $defaultValue = $champLibre->getDefaultValue() === null ?
                        "" : ($champLibre->getDefaultValue()
                            ? "Oui"
                            : "Non");
                } else if ($champLibre->getTypage() === FreeField::TYPE_DATETIME
                    || $champLibre->getTypage() === FreeField::TYPE_DATE) {
                    $defaultValueDate = new DateTime(str_replace('/', '-', $defaultValue));
                    $defaultValue = $defaultValueDate->format('d/m/Y H:i');
                }

                $rows[] =
                    [
                        'id' => ($champLibre->getId() ? $champLibre->getId() : 'Non défini'),
                        'Label' => ($champLibre->getLabel() ? $champLibre->getLabel() : 'Non défini'),
                        "S'applique à" => ($champLibre->getCategorieCL() ? $champLibre->getCategorieCL()->getLabel() : ''),
                        'Typage' => $typageCLFr,
                        'Affiché à la création' => ($champLibre->getDisplayedCreate() ? "oui" : "non"),
                        'Obligatoire à la création' => ($champLibre->getRequiredCreate() ? "oui" : "non"),
                        'Obligatoire à la modification' => ($champLibre->getRequiredEdit() ? "oui" : "non"),
                        'Valeur par défaut' => $defaultValue,
                        'Elements' => $champLibre->getTypage() == FreeField::TYPE_LIST || $champLibre->getTypage() == FreeField::TYPE_LIST_MULTIPLE ? $this->renderView('free_field/freeFieldElems.html.twig', ['elems' => $champLibre->getElements()]) : '',
                        'Actions' => $this->renderView('free_field/datatableFreeFieldRow.html.twig', ['idChampLibre' => $champLibre->getId()]),
                    ];
            }
            $data['data'] = $rows;

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/voir/{type}/champs-libres", name="champs_libre_show", methods={"GET","POST"})
     * @param EntityManagerInterface $entityManager
     * @param Type $type
     * @return Response
     */
    public function show(EntityManagerInterface $entityManager, Type $type): Response {
        $categorieCLRepository = $entityManager->getRepository(CategorieCL::class);
        $typages = FreeField::TYPAGE;

        return $this->render('free_field/show.html.twig', [
            'type' => $type,
            'categoriesCL' => $categorieCLRepository->findByLabel([CategorieCL::ARTICLE, CategorieCL::REFERENCE_ARTICLE]),
            'typages' => $typages,
        ]);
    }

    /**
     * @Route("/new", name="free_field_new", options={"expose"=true}, methods={"GET","POST"}, condition="request.isXmlHttpRequest()")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function new(Request $request, EntityManagerInterface $entityManager): Response {
        $data = json_decode($request->getContent(), true);

        $champLibreRepository = $entityManager->getRepository(FreeField::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $categorieCLRepository = $entityManager->getRepository(CategorieCL::class);

		// on vérifie que le nom du champ libre n'est pas déjà utilisé
		$champLibreExist = $champLibreRepository->countByLabel($data['label']);
		if (!$champLibreExist) {
			$type = $typeRepository->find($data['type']);
			$champLibre = new FreeField();
			$champLibre
				->setlabel($data['label'])
				->setRequiredCreate($data['displayedCreate'] ? $data['requiredCreate'] : false)
				->setRequiredEdit($data['requiredEdit'])
				->setDisplayedCreate($data['displayedCreate'])
				->setType($type)
				->settypage($data['typage']);

			if (isset($data['categorieCL'])) {
                $champLibre->setCategorieCL($categorieCLRepository->find($data['categorieCL']));
            } else {
                $champLibre->setCategorieCL($categorieCLRepository->findOneBy([
                    'categoryType' => $type->getCategory()
                ]));
            }

			if (in_array($champLibre->getTypage(), [FreeField::TYPE_LIST, FreeField::TYPE_LIST_MULTIPLE])) {
				$champLibre
					->setElements(array_filter(explode(';', $data['elem'])))
					->setDefaultValue(null);
			} else {
				$champLibre
					->setElements(null)
					->setDefaultValue($data['valeur']);
			}
			$entityManager->persist($champLibre);
            $entityManager->flush();

            return $this->json([
                'success' => true,
                'msg' => 'Le champ libre <strong>' . $data['label'] . '</strong> a bien été créé.'
            ]);
		} else {
			return new JsonResponse([
			    'success' => false,
                'msg' => 'Le champ libre <strong>' . $data['label'] . '</strong> existe déjà, veuillez définir un autre nom.'
            ]);
		}
    }

    /**
     * @Route("/api-modifier", name="free_field_api_edit", options={"expose"=true},  methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function editApi(Request $request, EntityManagerInterface $entityManager): Response {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $champLibreRepository = $entityManager->getRepository(FreeField::class);
            $categorieCLRepository = $entityManager->getRepository(CategorieCL::class);
            $champLibre = $champLibreRepository->find($data['id']);
            $typages = FreeField::TYPAGE;

            $json = $this->renderView('free_field/modalEditFreeFieldContent.html.twig', [
                'champLibre' => $champLibre,
                'typageCL' => FreeField::TYPAGE_ARR[$champLibre->getTypage()],
                'categoriesCL' => $categorieCLRepository->findByLabel([CategorieCL::ARTICLE, CategorieCL::REFERENCE_ARTICLE]),
                'typages' => $typages,
            ]);

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/modifier", name="free_field_edit", options={"expose"=true},  methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function edit(Request $request, EntityManagerInterface $entityManager): Response {
        $data = json_decode($request->getContent(), true);

        $champLibreRepository = $entityManager->getRepository(FreeField::class);
        $categorieCLRepository = $entityManager->getRepository(CategorieCL::class);

		$champLibre = $champLibreRepository->find($data['champLibre']);

        if(isset($data['categorieCL'])) {
            $categorieCL = $categorieCLRepository->find($data['categorieCL']);
            $champLibre->setCategorieCL($categorieCL);
        }

		$champLibre
			->setLabel($data['label'])
			->setRequiredCreate($data['displayedCreate'] ? $data['requiredCreate'] : false)
			->setRequiredEdit($data['requiredEdit'])
			->setDisplayedCreate($data['displayedCreate'])
			->setTypage($data['typage']);

		if (in_array($champLibre->getTypage(), [FreeField::TYPE_LIST, FreeField::TYPE_LIST_MULTIPLE])) {
			$champLibre
				->setElements(array_filter(explode(';', $data['elem'])))
				->setDefaultValue(null);
		} else {
		    dump($data);
			$champLibre
				->setElements(null)
				->setDefaultValue($data['typage'] === FreeField::TYPE_BOOL && $data['valeur'] == -1 ? null : $data['valeur']);
		}

		$this->getDoctrine()->getManager()->flush();

        return $this->json([
            'success' => true,
            'msg' => 'Le champ libre <strong>' . $data['label'] . '</strong> a bien été modifié.'
        ]);
    }

    /**
     * @Route("/delete", name="free_field_delete",options={"expose"=true}, methods={"GET","POST"}, condition="request.isXmlHttpRequest()")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function delete(Request $request, EntityManagerInterface $entityManager): Response {
        $data = json_decode($request->getContent(), true);

        $champLibreRepository = $entityManager->getRepository(FreeField::class);
        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);

		$champLibre = $champLibreRepository->find($data['champLibre']);
		$filters = $champLibre->getFilters();
		$ffLabel = $champLibre->getLabel();
		foreach ($filters as $filter) {
		    $entityManager->remove($filter);
        }

		$categorieCL = $champLibre->getCategorieCL();
		$categorieCLLabel = $categorieCL ? $categorieCL->getLabel() : null;

        $userFieldToRemove = $categorieCLLabel === CategorieCL::ARTICLE
            ? 'rechercheForArticle'
            : ($categorieCLLabel === CategorieCL::REFERENCE_ARTICLE
                ? 'recherche'
                : null);
		if ($userFieldToRemove) {
		    $utilisateurRepository->removeFromSearch($userFieldToRemove, ucfirst(strtolower($champLibre->getLabel())));
        }
		$entityManager->remove($champLibre);
		$entityManager->flush();

        return $this->json([
            'success' => true,
            'msg' => 'Le champ libre <strong>' . $ffLabel . '</strong> a bien été supprimé.'
        ]);
    }

    /**
     * @Route("/display-require-champ", name="display_required_champs_libres", options={"expose"=true},  methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function displayRequiredChampsLibres(Request $request,
                                                EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $champLibreRepository = $entityManager->getRepository(FreeField::class);
            $typeRepository = $entityManager->getRepository(Type::class);

            if (array_key_exists('create', $data)) {
                $type = $typeRepository->find($data['create']);
                $champsLibres = $champLibreRepository->getByTypeAndRequiredCreate($type);
            } else if (array_key_exists('edit', $data)) {
                $type = $typeRepository->find($data['edit']);
                $champsLibres = $champLibreRepository->getByTypeAndRequiredEdit($type);
            } else {
                $json = false;
                return new JsonResponse($json);
            }
            $json = [];
            foreach ($champsLibres as $champLibre) {
                $json[] = $champLibre['id'];
            }
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }
}
