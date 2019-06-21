<?php namespace Lusito\ZianoType;

class Utils
{
    const SINGLE_PROPERTIES = ['selected' => true, 'checked' => true, 'disabled' => true];
    const SELF_CLOSING_TAGS = [
        'area' => true, 'base' => true, 'br' => true, 'col' => true, 'embed' => true, 'hr' => true, 'img' => true,
        'input' => true, 'link' => true, 'meta' => true, 'param' => true, 'source' => true, 'track' => true, 'wbr' => true
    ];

    public static function isSelfClosingTag($name)
    {
        return isset(static::SELF_CLOSING_TAGS[$name]);
    }

    public static function isSingleProperty($name)
    {
        return isset(static::SINGLE_PROPERTIES[$name]);
    }

    public static function safeText($value)
    {
        $search  = ['"', '$', "\n", "\r", "\t"];
        $replace = ['\"', '\$', '\n', '\r', '\t'];
        return str_replace($search, $replace, $value);
    }

    public static function safeString($value)
    {
        return '"' . self::safeText($value) . '"';
    }

    public static function escapeText($text)
    {
        return htmlspecialchars($text, ENT_NOQUOTES | ENT_HTML5);
    }

    public static function escapeProperty($text)
    {
        return addcslashes(htmlspecialchars($text, ENT_QUOTES | ENT_HTML5), '"');
    }

    public static function maybeContainsCode($string)
    {
        return preg_match('/[${}]/', $string);
    }

    public static function prepareCodeParts($value)
    {
        if (preg_match('/^\$[a-zA-Z0-9_]+$/', $value))
            return $value;
        $value = '"' .  str_replace(['{{', '}}'], ['" . ', ' . "'], $value) . '"';
        $value = str_replace("\n", " ", $value);
        return str_replace(['"" . ', ' . ""'], "", $value);
    }

    public static function exportPropertyValue($value)
    {
        if (preg_match('/^\$[a-zA-Z0-9_]+$/', $value))
            return $value;
        else if (preg_match('/^{{.+}}$/', $value)) {
            $stripped = substr($value, 2, strlen($value) - 4);
            if (strpos($stripped, "{{") === false && strpos($stripped, "}}") === false)
                return $stripped;
        } else if (preg_match('/^{\$.+}$/', $value)) {
            $stripped = substr($value, 1, strlen($value) - 2);
            if (strpos($stripped, "{") === false && strpos($stripped, "}") === false)
                return $stripped;
        }
        $value = addcslashes($value, '"');
        return "\"$value\"";
    }
}
