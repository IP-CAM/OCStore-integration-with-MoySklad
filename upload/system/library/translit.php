<?php
class Translit
{
    static public $rustolat = array(
            'жё' => 'zho',  // жёлоб -> zholob
            'жю' => 'zhu',  // жюри -> zhuri
            'чё' => 'cho',  // чёлка -> cholka
            'щё' => 'shcho',    // щётка -> shchotka
            'щ' => 'shch',
            'шю' => 'shu',  // парашют -> parashut
            'ч' => 'ch',
            'ц' => 'ts',
            'х' => 'kh',
            'ю' => 'yu',
            'я' => 'ya',
            'ё' => 'yo',
            'ж' => 'zh',
            'ш' => 'sh',
            'а' => 'a',
            'б' => 'b',
            'в' => 'v',
            'г' => 'g',
            'д' => 'd',
            'е' => 'e',
            'з' => 'z',
            'и' => 'i',
            'й' => 'j',
            'к' => 'k',
            'л' => 'l',
            'м' => 'm',
            'н' => 'n',
            'о' => 'o',
            'п' => 'p',
            'р' => 'r',
            'с' => 's',
            'т' => 't',
            'у' => 'u',
            'ф' => 'f',
            'ъ' => '',
            'ы' => 'y',
            'ьо' => 'io',   // бульон -> bulion
            'ь' => '',
            'э' => 'e',
            'шё' => 'sho',  // шёлк -> sholk
    );

    static public function transliterate($string)
    {
            $obfuscated =  trim( mb_strtolower(preg_replace('/\s+/u', '-', preg_replace('/\W+/u', ' ', $string) )), '-');
            $rustolat_re = '/'. implode("|", array_keys(self::$rustolat)) .'/';
            return preg_replace_callback($rustolat_re, function($m){ return self::$rustolat[$m[0]];}, $obfuscated);
    }
}