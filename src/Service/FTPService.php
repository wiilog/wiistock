<?php

namespace App\Service;

use App\Exceptions\FTPException;
use phpseclib3\Exception\UnableToConnectException;
use phpseclib3\Net\SFTP;
use Throwable;

class FTPService {

    public function send(array $config, mixed $file) {
        // go back to the start of the file
        fseek($file, 0);

        try {
            $sftp = new SFTP($config["host"], intval($config["port"]));
            $login = $sftp->login($config["user"], $config["pass"]);
            if ($login) {
                $path = rtrim($config["path"], "/");
                $filename = basename(stream_get_meta_data($file)["uri"]);
                dump("$path/$filename");
                $sftp->put("$path/$filename", $file, SFTP::SOURCE_LOCAL_FILE);
                dump("ok??");
            } else {
                throw new FTPException(FTPException::INVALID_LOGINS);
            }
        } catch(FTPException $rethrow) {
            throw $rethrow;
        } catch(UnableToConnectException $_) {
            throw new FTPException(FTPException::UNABLE_TO_CONNECT);
        }  catch(Throwable $e) {
                dump($e);
        } finally {
            fclose($file);
        }
    }

}
