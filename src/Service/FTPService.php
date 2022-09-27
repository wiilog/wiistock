<?php

namespace App\Service;

use App\Exceptions\FTPException;
use phpseclib3\Exception\UnableToConnectException;
use phpseclib3\Net\SFTP;
use RuntimeException;

class FTPService {

    public function send(array $config, mixed $file) {
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
                if($sftp->filesize($remotePath) != filesize($localPath)) {
                    throw new FTPException(FTPException::UPLOAD_FAILED);
                }
            } else {
                throw new FTPException(FTPException::INVALID_LOGINS);
            }
        } catch(FTPException $rethrow) {
            throw $rethrow;
        } catch(UnableToConnectException) {
            throw new FTPException(FTPException::UNABLE_TO_CONNECT);
        } catch(RuntimeException) {
            throw new FTPException(FTPException::UNKNOWN_ERROR);
        } finally {
            fclose($file);
        }
    }

}
