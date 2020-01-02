<?php

namespace App\Service;

use App\Entity\Arrivage;
use App\Entity\Litige;
use App\Entity\MouvementTraca;
use App\Entity\PieceJointe;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\FileBag;


class AttachmentService
{

    private const ATTACHMENT_DIRECTORY = '../public/uploads/attachements/';

	/**
	 * @var EntityManagerInterface
	 */
	private $em;

    public function __construct(EntityManagerInterface $em)
    {
    	$this->em = $em;
    }

	/**
	 * @param FileBag|UploadedFile[] $files
	 * @param Arrivage $arrivage
	 * @param Litige|null $litige
	 * @param MouvementTraca|null $mvtTraca
	 */
	public function addAttachements($files, $arrivage, $litige = null, $mvtTraca = null) {
        if ($files instanceof FileBag) {
            $files = $files->all();
        }
		if (!file_exists(self::ATTACHMENT_DIRECTORY)) {
			mkdir(self::ATTACHMENT_DIRECTORY, 0777);
		}
		foreach ($files as $file) {
			if ($file) {
				$filename = uniqid() . '.' . $file->getClientOriginalExtension() ?? '';
				$file->move(self::ATTACHMENT_DIRECTORY, $filename);

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
