<?php

namespace App\Controller;

use App\Entity\Menu;
use App\Entity\PrefixeNomDemande;

use App\Repository\PrefixeNomDemandeRepository;
use App\Service\UserService;


use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\DependencyInjection\Tests\Compiler\J;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;



class PrefixeNomDemandeController extends AbstractController
{
    /**
     * @var PrefixeNomDemandeRepository
     */
    private $prefixeNomDemandeRepository;

    /**
     * @var UserService
     */
    private $userService;

    public function __construct(PrefixeNomDemandeRepository $prefixeNomDemandeRepository, UserService $userService)
    {
        $this->prefixeNomDemandeRepository = $prefixeNomDemandeRepository;
        $this->userService = $userService;
    }

    /**
     * @Route("/prefixe-demande", name="prefixe_demande_index")
     */
    public function index(): Response
    {
        if (!$this->userService->hasRightFunction(Menu::PARAM)) {
            return $this->redirectToRoute('access_denied');
        }

        return $this->render('prefixe_demande/prefixeDemande.html.twig');
    }

    /**
     * @Route("/ajax-update-prefix-demand", name="ajax_update_prefix_demand",  options={"expose"=true},  methods="GET|POST")
     */
    public function updatePrefixDemand(Request $request): response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $prefixeDemande =  $this->prefixeNomDemandeRepository->findOneByTypeDemande($data['typeDemande']);

            $em = $this->getDoctrine()->getManager();
            if($prefixeDemande == null){
                $newPrefixe = new PrefixeNomDemande();
                $newPrefixe
                    ->setTypeDemandeAssociee($data['typeDemande'])
                    ->setPrefixe($data['prefixe']);

                $em->persist($newPrefixe);
            } else {
                $prefixeDemande->setPrefixe($data['prefixe']);
            }
            $em->flush();
            return new JsonResponse(['typeDemande' => $data['typeDemande'], 'prefixe' => $data['prefixe']]);
        }
        throw new NotFoundHttpException("404");
    }

	/**
	 * @Route("/ajax-get-prefix-demand", name="ajax_get_prefix_demand",  options={"expose"=true},  methods="GET|POST")
	 */
    public function getPrefixDemand(Request $request)
	{
		if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
			$prefixeNomDemande = $this->prefixeNomDemandeRepository->findOneByTypeDemande($data);
			$prefix = $prefixeNomDemande ? $prefixeNomDemande->getPrefixe() : '';

			return new JsonResponse($prefix);
		}
		throw new NotFoundHttpException("404");
	}
}