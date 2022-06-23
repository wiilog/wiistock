<?php


namespace App\Service;


Class StringService
{

    public static function removeAccents(string $input) {
        return strtolower(trim(preg_replace('~[^0-9a-z]+~i', '-', preg_replace('~&([a-z]{1,2})(acute|cedil|circ|grave|lig|orn|ring|slash|th|tilde|uml);~i', '$1', htmlentities($input, ENT_QUOTES, 'UTF-8'))), ' '));
    }

    public static function mbstrcmp(string $a, string $b) {
        return strcmp(self::removeAccents($a), self::removeAccents($b));
    }

    public function mbUcfirst($str, $encode = 'UTF-8') {

        $start = mb_strtoupper(mb_substr($str, 0, 1, $encode), $encode);
        $end = mb_strtolower(mb_substr($str, 1, mb_strlen($str, $encode), $encode), $encode);

        return $start.$end;
    }
}
