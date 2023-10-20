<?php

namespace App\Service;

use App\Exceptions\FTPException;
use Exception;
use phpseclib3\Exception\UnableToConnectException;
use phpseclib3\Net\SFTP;
use RuntimeException;

class FTPService
{

    public function send(array $config, mixed $file): void
    {
        // go back to the start of the file
        fseek($file, 0);

        try {
            $sftp = new SFTP($config["host"], intval($config["port"]));
            $login = $sftp->login($config["user"], $config["pass"]);
            if ($login) {
                $localPath = stream_get_meta_data($file)["uri"];
                $path = rtrim($config["path"], "/");
                $filename = basename($localPath);
                $remotePath = "$path/$filename";

                $sftp->put($remotePath, $file, SFTP::SOURCE_LOCAL_FILE);
                if ($sftp->filesize($remotePath) != filesize($localPath)) {
                    throw new FTPException(FTPException::UPLOAD_FAILED);
                }
            } else {
                throw new FTPException(FTPException::INVALID_LOGINS);
            }
        } catch (FTPException $rethrow) {
            throw $rethrow;
        } catch (UnableToConnectException) {
            throw new FTPException(FTPException::UNABLE_TO_CONNECT);
        } catch (RuntimeException) {
            throw new FTPException(FTPException::UNKNOWN_ERROR);
        }
    }

    public function get(array $config, $remotePath): string
    {
        try {
            $sftp = new SFTP($config["host"], intval($config["port"]));
            $login = $sftp->login($config["user"], $config["pass"]);
            if ($login) {
                return $sftp->get($remotePath);
            } else {
                throw new FTPException(FTPException::INVALID_LOGINS);
            }
        } catch (FTPException $rethrow) {
            throw $rethrow;
        } catch (UnableToConnectException) {
            throw new FTPException(FTPException::UNABLE_TO_CONNECT);
        } catch (RuntimeException) {
            throw new FTPException(FTPException::UNKNOWN_ERROR);
        }
    }

    public function try(array $config): Exception|bool
    {
        try {
            $sftp = new SFTP($config["host"], intval($config["port"]));
            return $sftp->login($config["user"], $config["pass"]);
        } catch (FTPException $rethrow) {
            return $rethrow;
        } catch (UnableToConnectException) {
            return new FTPException(FTPException::UNABLE_TO_CONNECT);
        } catch (RuntimeException) {
            return new FTPException(FTPException::UNKNOWN_ERROR);
        }
    }

}
