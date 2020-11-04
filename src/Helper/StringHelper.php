<?php

namespace App\Helper;

class StringHelper {

    private static function stripUTF8Accents($str, &$map) {
        // find all multibyte characters (cf. utf-8 encoding specs)
        $matches = array();
        if(!preg_match_all('/[\xC0-\xF7][\x80-\xBF]+/', $str, $matches))
            return $str; // plain ascii string

        // update the encoding map with the characters not already met
        foreach($matches[0] as $mbc)
            if(!isset($map[$mbc]))
                $map[$mbc] = chr(128 + count($map));

        // finally remap non-ascii characters
        return strtr($str, $map);
    }

    public static function levenshtein($s1, $s2) {
        $charMap = array();
        $s1 = self::stripUTF8Accents($s1, $charMap);
        $s2 = self::stripUTF8Accents($s2, $charMap);

        return levenshtein($s1, $s2);
    }

}
