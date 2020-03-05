<?php

namespace App\Service;

use App\Entity\Arrivage;
use App\Entity\Import;
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
	 * @param Arrivage|Litige|MouvementTraca|Import $entity
	 * @return PieceJointe[]
	 */
	public function addAttachements($files, $entity) {
		$attachments = [];

        if ($files instanceof FileBag) {
            $files = $files->all();
        }

        $isFileName = count($files) > 0 && is_string($files[array_key_first($files)]);
        foreach ($files as $fileIndex => $file) {
			if ($file) {
                if ($isFileName) {
                    $originalFileName = $fileIndex;
                    $fileName = $file;
                } else {
                    $fileArray = $this->saveFile($file);
                    $originalFileName = $file->getClientOriginalName();
                    $fileName = $fileArray[$file->getClientOriginalName()];
                }
                $pj = new PieceJointe();
                $pj
                    ->setOriginalName($originalFileName)
                    ->setFileName($fileName);
                $this->em->persist($pj);
                $attachments[] = $pj;

                if ($entity instanceof Arrivage) {
					$entity->addAttachement($pj);
				} elseif ($entity instanceof Litige) {
					$entity->addPiecesJointe($pj);
				} elseif ($entity instanceof MouvementTraca) {
					$entity->addAttachement($pj);
				} elseif ($entity instanceof Import) {
					$entity->addCsvFile($pj);
				}

                $this->em->flush();
			}
		}

        return $attachments;
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
