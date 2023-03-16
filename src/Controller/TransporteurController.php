<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Attachment;
use App\Entity\Chauffeur;
use App\Entity\Transporteur;
use App\Entity\Menu;
use App\Service\AttachmentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Environment as Twig_Environment;

/**
 * @Route("/transporteur")
 */
class TransporteurController extends AbstractController
{
    #[Route("/api", name: "transporteur_api", options: ["expose" => true], methods: ["GET", "POST"], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::REFERENTIEL, Action::DISPLAY_TRAN], mode: HasPermission::IN_JSON)]
    public function api(EntityManagerInterface $entityManager,
                        Twig_Environment       $templating): Response
    {
        $transporteurRepository = $entityManager->getRepository(Transporteur::class);
        $chauffeurRepository = $entityManager->getRepository(Chauffeur::class);

        $transporteurs = $transporteurRepository->findAll();

        $rows = [];
        foreach ($transporteurs as $transporteur) {
            $charNumbers = $transporteur->getMinTrackingNumberLength() && $transporteur->getMaxTrackingNumberLength()
                ? "De " . $transporteur->getMinTrackingNumberLength() . " à " . $transporteur->getMaxTrackingNumberLength()
                : ($transporteur->getMinTrackingNumberLength()
                    ? "A partir de " . $transporteur->getMinTrackingNumberLength()
                    : ($transporteur->getMaxTrackingNumberLength()
                        ? "Jusqu'à " . $transporteur->getMaxTrackingNumberLength()
                        : ""));

            $rows[] = [
                'label' => $transporteur->getLabel() ?: null,
                'code' => $transporteur->getCode() ?: null,
                'driversNumber' => $chauffeurRepository->countByTransporteur($transporteur),
                'charNumbers' => $charNumbers,
                'isRecurrent' => $transporteur->isRecurrent() ? 'Oui' : 'Non',
                'logo' => $templating->render('datatable/image.html.twig', [
                    "image" => $transporteur->getAttachments()->get(0)
                ]),
                'actions' => $this->renderView('transporteur/datatableTransporteurRow.html.twig', [
                    'transporteur' => $transporteur
                ]),
            ];
        }
        $data['data'] = $rows;

        return new JsonResponse($data);
    }

    #[Route("/", name: "transporteur_index", methods: "GET")]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $transporteurRepository = $entityManager->getRepository(Transporteur::class);
        return $this->render('transporteur/index.html.twig', [
            'transporteurs' => $transporteurRepository->findAll(),
        ]);
    }

    #[Route("/form-validation", name: "transporteur_form", options: ["expose" => true], methods: ["GET", "POST"], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::REFERENTIEL, Action::CREATE], mode: HasPermission::IN_JSON)]
    public function formValidation(EntityManagerInterface $entityManager,
                        Request                $request,
                        AttachmentService      $attachmentService): Response
    {
        $transporteurRepository = $entityManager->getRepository(Transporteur::class);

        $data = $request->request->all();

		$code = $data['code'];
		$label = $data['label'];
        $minTrackingNumber = $data['min-char-number'] ?? null;
        $maxTrackingNumber = $data['max-char-number'] ?? null;
        $isRecurrent = $data['is-recurrent'];
        /** @var Attachment $logo */
        $logo = $request->files->get('logo')
            ? $attachmentService->createAttachements([$request->files->get('logo')])[0]
            : null;

        $carrierId = $request->query->get('carrier');
        if ($carrierId) {
            $transporteur = $transporteurRepository->find($carrierId);
        } else {
            $transporteur = new Transporteur();
        }

		// unicité du code et du nom transporteur
		$codeAlreadyUsed = intval($transporteurRepository->countByCode($code, $carrierId));
		$labelAlreadyUsed = intval($transporteurRepository->countByLabel($label, $carrierId));

		if ($codeAlreadyUsed + $labelAlreadyUsed) {
            $msg = 'Ce ' . ($codeAlreadyUsed ? 'code ' : 'nom ') . 'de transporteur est déjà utilisé.';
            return new JsonResponse([
                'success' => false,
                'msg' => $msg,
            ]);
		}

		$transporteur
			->setLabel($label)
			->setCode($code)
            ->setRecurrent($isRecurrent);

        if ($minTrackingNumber) {
            $transporteur->setMinTrackingNumberLength($minTrackingNumber);
        }
        if ($maxTrackingNumber) {
            $transporteur->setMaxTrackingNumberLength($maxTrackingNumber);
        }
        if ($logo) {
            if ($carrierId && !$transporteur->getAttachments()->isEmpty()) {
                $transporteur->removeAttachment($transporteur->getAttachments()[0]);
            }
            $transporteur->addAttachment($logo);
        } else if ($carrierId && !$transporteur->getAttachments()->isEmpty()) {
            $transporteur->removeAttachment($transporteur->getAttachments()[0]);
        }

		$entityManager->persist($transporteur);
		$entityManager->flush();

		return new JsonResponse([
			'success' => true,
			'id' => $transporteur->getId(),
			'text' => $transporteur->getLabel()
		]);
    }

    #[Route("/api-template", name: "transporteur_template", options: ["expose" => true], methods: "GET", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::REFERENTIEL, Action::EDIT], mode: HasPermission::IN_JSON)]
    public function templateForm(EntityManagerInterface $entityManager,
                                 Request                $request): JsonResponse
    {
        $transporteurRepository = $entityManager->getRepository(Transporteur::class);

        $carrierId = $request->query->get('carrier');
        $carrier = $carrierId
            ? $transporteurRepository->find($carrierId)
            : new Transporteur();

        return new JsonResponse($this->renderView('transporteur/modalTransporteurForm.html.twig', [
            'carrier' => $carrier,
        ]));
    }

    #[Route("/supprimer", name: "transporteur_delete", options: ["expose" => true], methods: ["GET","POST"], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::REFERENTIEL, Action::DELETE], mode: HasPermission::IN_JSON)]
    public function delete(EntityManagerInterface $entityManager,
                           Request                $request): Response
    {
        $carrierId = $request->query->get('carrier');

        $transporteurRepository = $entityManager->getRepository(Transporteur::class);
        $carrier = $transporteurRepository->find($carrierId);

        if(!$carrier->getEmergencies()->isEmpty()) {
            return $this->json([
                'success' => false,
                'msg' => "Ce transporteur est lié à une ou plusieurs urgences, vous ne pouvez pas le supprimer"
            ]);
        }

        if (!$carrier->getAttachments()->isEmpty()) {
            foreach ($carrier->getAttachments() as $attachment) {
                $carrier->removeAttachment($attachment);
            }
        }

        $entityManager->remove($carrier);
        $entityManager->flush();

        $name = $carrier->getLabel();
        return $this->json([
            'success' => true,
            'msg' => "Le transporteur $name a bien été créé"
        ]);
    }

    #[Route("/supprimer-logo", name: "transporteur_delete_logo", options: ["expose" => true], methods: ["GET","POST"], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::REFERENTIEL, Action::DELETE], mode: HasPermission::IN_JSON)]
    public function deleteLogo(EntityManagerInterface $entityManager,
                               Request                $request): Response
    {
        $carrierId = $request->query->get('carrier');

        $transporteurRepository = $entityManager->getRepository(Transporteur::class);
        $carrier = $transporteurRepository->find($carrierId);

        if (!$carrier->getAttachments()->isEmpty()) {
            foreach ($carrier->getAttachments() as $attachment) {
                $carrier->removeAttachment($attachment);
            }
        }

        $entityManager->flush();

        return $this->json([
            'success' => true,
        ]);
    }
}
