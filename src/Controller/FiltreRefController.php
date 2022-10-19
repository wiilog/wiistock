<?php

namespace App\Controller;

use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\FreeField;
use App\Entity\Emplacement;
use App\Entity\FiltreRef;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Entity\VisibilityGroup;
use App\Service\RefArticleDataService;
use App\Service\VisibleColumnService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use WiiCommon\Helper\Stream;

/**
 * Class FiltreRefController
 * @package App\Controller
 * @Route("/filtre-ref")
 */
class FiltreRefController extends AbstractController
{

    /**
     * @Route("/creer", name="filter_ref_new", options={"expose"=true}, condition="request.isXmlHttpRequest()")
     */
    public function new(Request $request,
                        VisibleColumnService $visibleColumnService,
                        RefArticleDataService $refArticleDataService,
                        EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $champLibreRepository = $entityManager->getRepository(FreeField::class);
            $filtreRefRepository = $entityManager->getRepository(FiltreRef::class);

            /** @var Utilisateur $user */
            $user = $this->getUser();

            // on vérifie qu'il n'existe pas déjà un filtre sur le même champ
            $userId = $user->getId();
            $title = $refArticleDataService->getFieldTitle($data['field']);
            $existingFilter = $filtreRefRepository->countByChampAndUser($title, $userId);
            if($existingFilter == 0) {
                $filter = new FiltreRef();

                $unknownField = false;

				// champ Champ Libre
                if (isset($data['field'])) {
                    $field = $data['field'];
                    $freeFieldId = $visibleColumnService->extractFreeFieldId($field);
                    if (!empty($freeFieldId)) {
                        $champLibre = $champLibreRepository->find($freeFieldId);
                        $filter->setChampLibre($champLibre);
                    } else {
                        if (!empty($title)) {
                            $filter->setChampFixe($title);
                        }
                        else {
                            $unknownField = true;
                        }
                    }
                } else {
                    $unknownField = true;
                }

                if ($unknownField) {
                    return new JsonResponse([
                        'success' => false,
                        'msg' => 'Champ inconnu.'
                    ]);
                }

                // champ Value
                if (isset($data['value'])) {
                    if ($filter->getChampFixe() === FiltreRef::FIXED_FIELD_VISIBILITY_GROUP) {
                        $value = implode(',', json_decode($data['value']));
                    } else {
                        $value = is_array($data['value']) ? implode(",", $data['value']) : $data['value'];
                    }
                    $filter->setValue($value);
                }

                // champ Utilisateur
                $filter->setUtilisateur($user);

                $entityManager->persist($filter);
                $entityManager->flush();

                $filterArray = [
                    'id' => $filter->getId(),
                    'freeField' => $filter->getChampLibre(),
                    'fixedField' => $filter->getChampFixe(),
                    'value' => $filter->getValue()
                ];

                $result = [
                    'filterHtml' => $this->renderView('reference_article/oneFilter.html.twig', ['filter' => $filterArray]),
                    'success' => true
                ];
            } else {
                $result = [
                    'success' => false,
                    'msg' => 'Un filtre sur ce champ existe déjà.'
                ];
            }
            return new JsonResponse($result);
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/supprimer", name="filter_ref_delete", options={"expose"=true}, methods={"DELETE"}, condition="request.isXmlHttpRequest()")
     */
    public function delete(Request $request,
                           EntityManagerInterface $entityManager): Response
    {
        $filterId = $request->request->get('filterId');
        $success = false;
        $message = "Le filtre n'a pas pu être supprimé";
        if ($filterId) {
            $filtreRefRepository = $entityManager->getRepository(FiltreRef::class);
            $filter = $filtreRefRepository->find($filterId);

            if ($filter) {
                $entityManager->remove($filter);
                $entityManager->flush();
                $success = true;
                $message = null;
            }
        }

        return new JsonResponse(['success' => $success, 'msg' => $message]);
    }

    /**
     * @Route("/affiche-liste", name="display_field_elements", options={"expose"=true}, methods={"GET","POST"}, condition="request.isXmlHttpRequest()")
     */
	public function displayFieldElements(Request $request,
                                         VisibleColumnService $visibleColumnService,
                                         EntityManagerInterface $entityManager)
	{
		if ($data = json_decode($request->getContent(), true)) {


			$value = $data['value'];
			$multiple = false;
            $options = [];
			if ($value === 'location') {
                $emplacementRepository = $entityManager->getRepository(Emplacement::class);
                $emplacements = $emplacementRepository->findBy(['isActive' => true],['label'=> 'ASC']);
				foreach ($emplacements as $emplacement) {
					$options[] = $emplacement->getLabel();
				}
			} else if ($value === 'type') {
                $typeRepository = $entityManager->getRepository(Type::class);
                $types = $typeRepository->findByCategoryLabels([CategoryType::ARTICLE], 'asc');
                foreach ($types as $type) {
                    $options[] = $type->getLabel();
                }

            } else if($value === 'status') {
                $statuses = $entityManager->getRepository(Statut::class)->findByCategoryNameAndStatusCodes(CategorieStatut::REFERENCE_ARTICLE, [
                    ReferenceArticle::STATUT_ACTIF,
                    ReferenceArticle::STATUT_INACTIF,
                    ReferenceArticle::DRAFT_STATUS
                ]);
                foreach ($statuses as $status) {
                    $options[] = $this->getFormatter()->status($status);
                }
			} else if ($value === 'visibilityGroups' || $value === 'visibilityGroup') {
                $multiple = true;
                $visibilityGroupRepository = $entityManager->getRepository(VisibilityGroup::class);
                $visibilityGroups = $visibilityGroupRepository->findBy(["active" => true], ["label" => "asc"]);
				foreach ($visibilityGroups as $visibilityGroup) {
					$options[] = $visibilityGroup->getLabel();
				}
			} else {
                $champLibreRepository = $entityManager->getRepository(FreeField::class);
                $freeFieldId = $visibleColumnService->extractFreeFieldId($value);
                if (!empty($freeFieldId)) {
                    /** @var $cl FreeField */
                    $cl = $champLibreRepository->find($freeFieldId);
                    $options = $cl->getElements();
                    $multiple = true;
                }
			}

			$view = $this->renderView('reference_article/selectInFilter.html.twig', [
				'options' => $options,
                'multiple' => $multiple
			]);
			return new JsonResponse($view);
		}
		throw new BadRequestHttpException();
	}

    /**
     * @Route("/update-filters", name="update_filters", options={"expose"=true}, methods={"GET"}, condition="request.isXmlHttpRequest()")
     */
    public function updateFilters(EntityManagerInterface $manager): Response
    {
        /** @var Utilisateur $loggedUser */
        $loggedUser = $this->getUser();

        $filters = $manager->getRepository(FiltreRef::class)->findByUser($loggedUser);
        $filters = Stream::from($filters)->map(fn(FiltreRef $filter) => [
            'id' => $filter->getId(),
            'freeField' => $filter->getChampLibre(),
            'fixedField' => $filter->getChampFixe(),
            'value' => $filter->getValue()
        ])->toArray();

        $templates = [];
        foreach ($filters as $filter) {
            $templates[] = [
                'filterHtml' => $this->renderView('reference_article/oneFilter.html.twig', ['filter' => $filter])
            ];
        }

        return new JsonResponse([
            'success' => true,
            'templates' => $templates
        ]);
    }
}
