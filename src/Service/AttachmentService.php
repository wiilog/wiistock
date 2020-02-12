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
	 * @param FileBag|UploadedFile[]|array $files if array it's an assoc array between originalFileName and serverFileName
	 * @param Arrivage $arrivage
	 * @param Litige|null $litige
	 * @param MouvementTraca|null $mvtTraca
	 */
	public function addAttachements($files, $arrivage, $litige = null, $mvtTraca = null) {
        if ($files instanceof FileBag) {
            $files = $files->all();
        }

        $isFileName = count($files) > 0 && is_string($files[array_key_first($files)]);

        foreach ($files as $fileIndex => $file) {
			if ($file) {
			    $filename = $isFileName
                    ? $file
                    : $this->saveFile($file);

                $pj = new PieceJointe();
                $pj
                    ->setOriginalName($fileIndex)
                    ->setFileName($filename);

                $this->em->persist($pj);

				if ($arrivage) {
					$arrivage->addAttachement($pj);
				} elseif ($litige) {
					$litige->addPiecesJointe($pj);
				} elseif ($mvtTraca) {
					$mvtTraca->addAttachement($pj);
				}
			}
		}
	}

    /**
     * @param UploadedFile $file
     * @return array [originalName (string) => filename (string)]
     */
	public function saveFile(UploadedFile $file): array {
        if (!file_exists(self::ATTACHMENT_DIRECTORY)) {
            mkdir(self::ATTACHMENT_DIRECTORY, 0777);
        }

        $filename = uniqid() . '.' . $file->getClientOriginalExtension() ?? '';
        $file->move(self::ATTACHMENT_DIRECTORY, $filename);
        return [$file->getClientOriginalName() => $filename];
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

        $pieceJointeRepository = $this->em->getRepository(PieceJointe::class);
        $pieceJointeAlreadyInDB = $pieceJointeRepository->findOneByFileName($attachment->getFileName());
        if (count($pieceJointeAlreadyInDB) === 1) {
            $path = "../public/uploads/attachements/" . $attachment->getFileName();
            unlink($path);
        }

        $this->em->remove($attachment);
        $this->em->flush();
	}

}
