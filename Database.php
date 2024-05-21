<?php

namespace FpDbTest;

use Exception;
use mysqli;

class Database implements DatabaseInterface
{
    const SKIP_CONDITION_PARAMETER = 0;

    private mysqli $mysqli;

    private static $arDataTypes  = [
        'd' => 'integer',
        'f' => 'float',
        'a' => 'array',
        '#' => 'keys',
    ];

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function buildQuery(string $query, array $args = []): string
    {
        if (!$query){
            throw new Exception('Empty query string!');
        }

        // -- Getting special keys to replace with $args -------------------- //
        if (preg_match_all("/\?([".join(',', array_keys(self::$arDataTypes))."])*/i", $query, $arMatches)){
            $query = $this->replaceByTypes($query, $args, $arMatches[1]);
        }

        // -- Checking for conditional blocks ------------------------------- //
        if (preg_match_all('/\{([^}{]*)\}/si', $query, $arMatches)){
            $query = $this->checkConditions($query, $arMatches[1]);
        }

        if (preg_match('/[{}]+/s', $query, $arMatches)){
            throw new Exception('Nested conditions found!');
        }

        return $query;
    }

    public function skip()
    {
        return self::SKIP_CONDITION_PARAMETER;
    }

    private function replaceByTypes(string $query = '', array $args = [], array $arTypes = []): string
    {
        $arValues = [];
        foreach($arTypes AS $key => &$type)
        {
            $value      = isset($args[$key]) ? $args[$key] : null;
            $func_name  = $this->getFuncName($type);
            $value_to_replace   = $this->$func_name($value);
            $query = preg_replace("/\?$type/", $value_to_replace, $query, 1);
        }

        return $query;
    }

    private static function getFuncName(string $type = ''): string
    {
        $type_name  = ucfirst(isset(self::$arDataTypes[$type]) ? self::$arDataTypes[$type] : 'custom');
        return "get{$type_name}Value";
    }

    private static function getCustomValue($value)
    {
        if (is_integer($value)){
            return self::getIntegerValue($value);
        }else if (is_float($value)){
            return self::getFloatValue($value);
        }else if (is_bool($value)){
            return self::getBoolValue($value);
        }else if (is_array($value)){
            return self::getArrayValue($value);
        }

        return self::getStringValue((string)$value);
    }

    private static function getStringValue(string $value = ''): string
    {
        return !$value ? 'NULL' : '\''.$value.'\'';
    }

    private static function getIntegerValue($value) {
        return (int) $value;
    }

    private static function getFloatValue($value) {
        return (float) $value;
    }

    private static function getBoolValue($value) {
        return (bool) $value ? 1 : 0;
    }

    private static function getArrayValue(array $arValues = [], bool $is_key = false): string {
        $arResult = [];
        foreach($arValues AS $key => &$value){
            $arResult[] = is_integer($key) ?  (
                    $is_key ? "`$value`" : self::getCustomValue($value)
                ) : "`$key` = ".self::getCustomValue($value);
        }

        return join(', ', $arResult);
    }

    private static function getKeysValue($value){
        if (is_array($value)){
            return self::getArrayValue($value, true);
        }

        return "`$value`";
    }

    private static function checkConditions(string $query = '', array $arBlocks = []): string {
        foreach($arBlocks AS &$block){
            $replace_with = preg_match("/=\s*".self::SKIP_CONDITION_PARAMETER."/si", $block) ? '' : $block;
            $query = str_replace("{".$block."}", $replace_with, $query);
        }

        return $query;
    }
}
