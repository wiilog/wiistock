<?php


namespace App\Service;


Class StringService
{

    public function mbUcfirst($str, $encode = 'UTF-8') {

        $start = mb_strtoupper(mb_substr($str, 0, 1, $encode), $encode);
        $end = mb_strtolower(mb_substr($str, 1, mb_strlen($str, $encode), $encode), $encode);

        $str = $start.$end;
        return $str;
    }
}
