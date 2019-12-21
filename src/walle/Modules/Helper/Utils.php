<?php
/**
 * 辅助常用功能类
 */

namespace Walle\Modules\Helper;

use InvalidArgumentException;

class Utils
{
    public static function handleException($e)
    {
        $fileStore = sys_get_temp_dir() . '/es-log-'.date('Ymd').'.log';

        $content = sprintf('Exception %s: "%s" at %s line %s',
            self::getClass($e), $e->getMessage(), $e->getFile(), $e->getLine());

        file_put_contents($fileStore, date('Y-m-d H:i:s').PHP_EOL.$content, FILE_APPEND);
    }

    public static function getClass($object)
    {
        $class = \get_class($object);

        return 'c' === $class[0] && 0 === strpos($class, "class@anonymous\0") ? get_parent_class($class).'@anonymous' : $class;
    }

    /**
     * Return the JSON representation of a value
     *
     * @param  mixed             $data
     * @param  int               $encodeFlags flags to pass to json encode, defaults to JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
     * @return string
     */
    public static function jsonEncode($data, $encodeFlags = null)
    {
        if (null === $encodeFlags && version_compare(PHP_VERSION, '5.4.0', '>=')) {
            $encodeFlags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        }

        $json = @json_encode($data, $encodeFlags);
        if (false === $json) {
            return 'null';
        }

        return $json;
    }

    public static function jsonDecode($json, $assoc = false, $depth = 512, $options = 0)
    {
        $data = json_decode($json, $assoc, $depth, $options);
        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new InvalidArgumentException(
                'json_decode error: ' . static::transformJsonError()
            );
        }

        return $data;
    }

    private static function transformJsonError()
    {
        if (function_exists('json_last_error_msg')) {
            return json_last_error_msg();
        }

        switch (json_last_error()) {
            case JSON_ERROR_DEPTH:
                return 'Maximum stack depth exceeded.';

            case JSON_ERROR_STATE_MISMATCH:
                return 'Underflow or the modes mismatch.';

            case JSON_ERROR_CTRL_CHAR:
                return 'Unexpected control character found.';

            case JSON_ERROR_SYNTAX:
                return 'Syntax error, malformed JSON.';

            case JSON_ERROR_UTF8:
                return 'Malformed UTF-8 characters, possibly incorrectly encoded.';

            default:
                return 'Unknown error.';
        }
    }
}
