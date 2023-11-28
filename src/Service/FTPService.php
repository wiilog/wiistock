<?php

namespace App\Service;

use App\Exceptions\FTPException;
use JetBrains\PhpStorm\ArrayShape;
use phpseclib3\Exception\UnableToConnectException;
use phpseclib3\Net\SFTP;
use RuntimeException;
use WiiCommon\Helper\Stream;
use WiiCommon\Helper\StringHelper;

class FTPService {

    private const DIRECTORY_TYPE = 2;

    public function send(
        #[ArrayShape(["host" => "string", "port" => "string", "user" => "string", "pass" => "string"])] array $config,
        string $targetPath,
        mixed $file
    ): true {
        // go back to the start of the file
        fseek($file, 0);

        return $this->wrapFTPAction(
            $config,
            function (SFTP $sftp) use ($targetPath, $file) {
                $localPath = stream_get_meta_data($file)["uri"];
                $path = rtrim($targetPath, "/");
                $filename = basename($localPath);
                $remotePath = "$path/$filename";

                $sftp->put($remotePath, $file, SFTP::SOURCE_LOCAL_FILE);
                if ($sftp->filesize($remotePath) != filesize($localPath)) {
                    throw new FTPException(FTPException::UPLOAD_FAILED);
                }

                return true;
            }
        );
    }


    public function get(
        #[ArrayShape(["host" => "string", "port" => "string", "user" => "string", "pass" => "string",])] array $config,
        string $remotePath
    ): string {
        return $this->wrapFTPAction(
            $config,
            fn(SFTP $sftp) => $sftp->get($remotePath)
        );
    }

    public function try(
        #[ArrayShape(["host" => "string", "port" => "string", "user" => "string", "pass" => "string"])] array $config
    ): true {
        return $this->wrapFTPAction($config, fn() => true);
    }

    public function glob(
        #[ArrayShape(["host" => "string", "port" => "string", "user" => "string", "pass" => "string"])] array $config,
        string $absolutePathMask,
    ): array {
        $maskFirstChar = substr($absolutePathMask, 0, 1);
        $pathParts = Stream::explode("/", $absolutePathMask)->values();

        if (!in_array($maskFirstChar, ["/", "~"])
            || $pathParts <= 1) {
            return [];
        }
        else {
            return $this->wrapFTPAction($config, function (SFTP $sftp) use ($pathParts) {
                $part = array_shift($pathParts);

                $fileMaskIndex = count($pathParts) - 1;
                $allPaths = ["$part/"];

                foreach ($pathParts as $index => $part) {
                    $fileRegex = StringHelper::convertMaskToRegex($part);
                    $isMask = $fileRegex !== $part;
                    $fileLoop = $index === $fileMaskIndex;

                    if (!$isMask && !$fileLoop) {
                        $allPaths = Stream::from($allPaths)
                            ->map(static fn(string $path) => "$path$part/")
                            ->toArray();
                    }
                    else if (!$isMask && $fileLoop) {
                        $allPaths = Stream::from($allPaths)
                            ->map(static fn(string $path) => "$path$part")
                            ->filter(static fn(string $path) => $sftp->is_file($path))
                            ->toArray();
                    }
                    else {
                        $allPaths = Stream::from($allPaths)
                            ->flatMap(function (string $path) use ($sftp, $fileRegex, $fileLoop) {
                                $dirContent = $sftp->rawList($path) ?: [];
                                return Stream::from($dirContent)
                                    ->filter(fn(array $stat, string $name) => (
                                        !in_array($name, [".", ".."])
                                        && (
                                            ($fileLoop && $stat["type"] !== self::DIRECTORY_TYPE)
                                            || (!$fileLoop && $stat["type"] === self::DIRECTORY_TYPE)
                                        )
                                        && preg_match("/^$fileRegex$/", $name)
                                    ))
                                    ->map(fn(array $stat, string $name) => (
                                    $fileLoop
                                        ? "$path$name"
                                        : "$path$name/"
                                    ))
                                    ->values();
                            })
                            ->values();
                    }

                    // we exit loop and function
                    if (empty($allPaths)) {
                        return [];
                    }
                }

                return $allPaths;
            });
        }
    }

    private function wrapFTPAction(
        #[ArrayShape(["host" => "string", "port" => "string", "user" => "string", "pass" => "string"])] array $config,
        callable $action
    ): mixed {
        try {
            $sftp = new SFTP($config["host"], intval($config["port"]));
            $resLogin = $sftp->login($config["user"], $config["pass"]);
            if ($resLogin) {
                $res = $action($sftp);
                $sftp->disconnect();
                return $res;
            } else {
                throw new FTPException(FTPException::INVALID_LOGINS);
            }
        } catch (UnableToConnectException) {
            throw new FTPException(FTPException::UNABLE_TO_CONNECT);
        } catch (RuntimeException) {
            throw new FTPException(FTPException::UNKNOWN_ERROR);
        }
    }

}
