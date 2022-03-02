<?php


namespace App\Model\Git;


class Caches
{

    public const CACHE_SETTINGS     = 1;
    public const CACHE_WEB_SERVICE  = 2;
    public const CACHE_TRANSLATIONS = 3;
    public const CACHE_LAYOUT       = 4;
    public const CACHE_LATTE        = 5;
    public const CACHE_HTML         = 6;
    public const CACHE_MERGED       = 7;

    public const CACHE_DANGEROUS = [
        self::CACHE_HTML,
    ];

    public const INSTALLERS_REGEX = '^app/code/local/Aiti/.+/sql/.+\.php$';

    public const CACHE_REGEX = [ // ATTENTION! Order this in order as caches are flushed
        self::CACHE_SETTINGS     =>'etc\/.+\.xml$',       // config files
        self::CACHE_WEB_SERVICE  =>'api\/|api\.xml$|api2\.xml$',
        self::CACHE_TRANSLATIONS =>'\.csv$',              // translations in .csv files
        self::CACHE_LAYOUT       =>'layout\/',            // Layout
        self::CACHE_LATTE        =>'\.latte$',            // Latte templates
        self::CACHE_HTML         =>'\.(phtml|latte)$',    // HTML cache
        self::CACHE_MERGED       =>'\.css$|\.js$|\.less$', // less cache moved here
    ];

    public const CACHE_NAMES = [
        self::CACHE_SETTINGS     => 'Nastavení',
        self::CACHE_WEB_SERVICE  => 'Web Služba Nastavení',
        self::CACHE_TRANSLATIONS => 'Překlady',
        self::CACHE_LAYOUT       => 'Rozvržení',
        self::CACHE_LATTE        => 'Latte cache',
        self::CACHE_HTML         => 'HTML výstup bloku',
        self::CACHE_MERGED       => 'Merged files',
    ];

    public static function getCaches(array $filenames): array
    {

        $cache_names = self::CACHE_NAMES;
        $cache_regex = self::CACHE_REGEX;

        $numbers = array();
        foreach ($cache_regex as $key => $regex) {
            if (preg_grep("~" . $regex . "~", $filenames)) $numbers[$key] = $cache_names[$key];
        }

        return $numbers;
    }

    public static function markdownCaches(array $filenames): ?string
    {
        $caches = self::getCaches($filenames);
        $markdown = "## Caches to flush";

        if (empty($caches)) {
            $markdown .= "\n- No caches to flush";
        } else {
            foreach ($caches as $name) {
                $markdown .= "\n- [ ] $name";
            }
        }

        return $markdown;
    }
}
