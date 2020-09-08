<?php

namespace App\Controller;

use App\Entity\CategoryType;
use App\Entity\ChampLibre;
use App\Entity\Emplacement;
use App\Entity\FiltreRef;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Repository\FiltreRefRepository;
use App\Service\RefArticleDataService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class FiltreRefController
 * @package App\Controller
 * @Route("/filtre-ref")
 */
class FiltreRefController extends AbstractController
{

    /**
     * @var FiltreRefRepository
     */
    private $filtreRefRepository;

    /**
     * @var RefArticleDataService
     */
    private $refArticleDataService;

	/**
	 * FiltreRefController constructor.
	 * @param FiltreRefRepository $filtreRefRepository
	 * @param RefArticleDataService $refArticleDataService
	 */
    public function __construct(FiltreRefRepository $filtreRefRepository, RefArticleDataService $refArticleDataService)
    {
        $this->filtreRefRepository = $filtreRefRepository;
        $this->refArticleDataService = $refArticleDataService;
    }

    /**
     * @Route("/creer", name="filter_ref_new", options={"expose"=true})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function new(Request $request,
                        EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $champLibreRepository = $entityManager->getRepository(ChampLibre::class);

            /** @var Utilisateur $user */
            $user = $this->getUser();

            // on vérifie qu'il n'existe pas déjà un filtre sur le même champ
            $userId = $user->getId();
            $existingFilter = $this->filtreRefRepository->countByChampAndUser($data['field'], $userId);

            if($existingFilter == 0) {
                $filter = new FiltreRef();

				// champ Champ Libre
                if (isset($data['field'])) {
                    $field = $data['field'];

                    if (intval($field) != 0) {
                        $champLibre = $champLibreRepository->find(intval($field));
                        $filter->setChampLibre($champLibre);
                    } else {
                        $filter->setChampFixe($data['field']);
                    }
                } else {
                    return new JsonResponse([
                        'success' => false,
                        'msg' => 'Champ inconnu.'
                    ]);
                }

                // champ Value
                if (isset($data['value'])) {
                    $filter->setValue(is_array($data['value']) ? implode(",", $data['value']) : $data['value']);
                }

                // champ Utilisateur
                $filter->setUtilisateur($user);

                $entityManager->persist($filter);
                $entityManager->flush();

                $filterArray = [
                    'id' => $filter->getId(),
                    'champLibre' => $filter->getChampLibre(),
                    'champFixe' => $filter->getChampFixe(),
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
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/supprimer", name="filter_ref_delete", options={"expose"=true}, methods={"DELETE"}, condition="request.isXmlHttpRequest()")
     * @param Request $request
     * @return Response
     */
    public function delete(Request $request): Response
    {
        $filterId = $request->request->get('filterId');
        $success = false;
        $message = "Le filtre n'a pas pu être supprimé";
        if ($filterId) {
            $filter = $this->filtreRefRepository->find($filterId);

            if ($filter) {
                $em = $this->getDoctrine()->getManager();
                $em->remove($filter);
                $em->flush();
                $success = true;
                $message = null;
            }
        }

        return new JsonResponse(['success' => $success, 'msg' => $message]);
    }

    /**
     * @Route("/affiche-liste", name="display_field_elements", options={"expose"=true}, methods={"GET","POST"})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return JsonResponse
     */
	public function displayFieldElements(Request $request,
                                         EntityManagerInterface $entityManager)
	{
		if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {

            $emplacementRepository = $entityManager->getRepository(Emplacement::class);
            $typeRepository = $entityManager->getRepository(Type::class);
            $champLibreRepository = $entityManager->getRepository(ChampLibre::class);

			$value = $data['value'];
			$multiple = false;
			if ($value === 'Emplacement') {
				$emplacements = $emplacementRepository->findAllSorted();
				$options = [];
				foreach ($emplacements as $emplacement) {
					$options[] = $emplacement->getLabel();
				}
			} else if ($value === 'Type') {
				$types = $typeRepository->findByCategoryLabels([CategoryType::ARTICLE], 'asc');
				$options = [];
				foreach ($types as $type) {
					$options[] = $type->getLabel();
				}
			} else {
				$cl = $champLibreRepository->find(intval($value)); /** @var $cl ChampLibre */
				$options = $cl->getElements();
				$multiple = true;
			}

			$view = $this->renderView('reference_article/selectInFilter.html.twig', [
				'options' => $options,
                'multiple' => $multiple
			]);
			return new JsonResponse($view);
		}
		throw new NotFoundHttpException("404");
	}
}
