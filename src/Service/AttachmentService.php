<?php

namespace App\Service;

use App\Entity\Arrivage;
use App\Entity\Litige;
use App\Entity\MouvementTraca;
use App\Entity\PieceJointe;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class AttachmentService
{
	/**
	 * @var EntityManagerInterface
	 */
	private $em;

    public function __construct(EntityManagerInterface $em)
    {
    	$this->em = $em;
    }

	/**
	 * @Route("/ajouter-pj", name="add_attachement", options={"expose"=true}, methods="GET|POST")
	 * @param Request $request
	 * @param Arrivage $arrivage
	 * @param Litige|null $litige
	 * @param MouvementTraca|null $mvtTraca
	 */
	public function addAttachements(Request $request, $arrivage, $litige = null, $mvtTraca = null)
	{
		$path = "../public/uploads/attachements/";
		if (!file_exists($path)) {
			mkdir($path, 0777);
		}
		for ($i = 0; $i < count($request->files); $i++) {
			$file = $request->files->get('file' . $i);
			if ($file) {
				$filename = uniqid() . '.' . $file->getClientOriginalExtension() ?? '';
				$file->move($path, $filename);

				$pj = new PieceJointe();
				$pj
					->setOriginalName($file->getClientOriginalName())
					->setFileName($filename);
				if ($arrivage) {
					$arrivage->addAttachement($pj);
				} elseif ($litige) {
					$litige->addPiecesJointe($pj);
				} elseif ($mvtTraca) {
					$mvtTraca->addAttachement($pj);
				}
				$this->em->persist($pj);
			}
		}
		$this->em->flush();
	}

	/**
	 * @param PieceJointe $attachment
	 * @param Arrivage $arrivage
	 * @param Litige $litige
	 * @param MouvementTraca $mvtTraca
	 */
	public function removeAndDeleteAttachment($attachment, $arrivage, $litige = null, $mvtTraca = null)
	{
		if ($arrivage) {
			$arrivage->removeAttachement($attachment);
		} elseif ($litige) {
			$litige->removeAttachement($attachment);
		} elseif ($mvtTraca) {
			$mvtTraca->removeAttachement($attachment);
		}
		$path = "../public/uploads/attachements/" . $attachment->getFileName();
		unlink($path);
		$this->em->flush();
	}

}
