<?php

/**
 * Simple Utility
 *
 *
 *
 * @author dso
 *
 */
class SU
{

    public static function guidv4()
    {
        if (function_exists('com_create_guid') === true)
            return trim(com_create_guid(), '{}');

        $data = openssl_random_pseudo_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public static function xmlencode($str)
    {
        return htmlentities($str, ENT_XML1);
    }

    /**
     * Without params it returns current time
     *
     * @param string $time
     * @return MongoDB\BSON\UTCDateTime
     */
    public static function timeToBSON($time = 'now')
    {
        if (is_numeric($time)) {
            return new MongoDB\BSON\UTCDateTime($time*1000);
        }
        
        if ($time instanceof DateTime) {
            return new MongoDB\BSON\UTCDateTime($time);
        }
        return new MongoDB\BSON\UTCDateTime(strtotime($time) * 1000);
    }

    public static function BSONTimeToString(&$bson, $format = user::DATEFORMAT)
    {
        return su::gis($bson) ? date($format, $bson->__toString() / 1000) : 'undefined';
    }

    /**
     *
     * Multiconversion with default as JSON
     *
     * @param array $array
     * @param string $type
     * @return string
     */
    public static function arrayToJSONorXML(array $array, $type = null)
    {
        $xml = null;
        if ($type === 'XML') {
            self::array_to_xml($array, $xml);
            return $xml->asXML();
        } else {
            return json_encode($array);
        }
    }

    public static function array_to_xml($data, &$xml_data = null)
    {
        if (empty($xml_data)) {
            $xml_data = new SimpleXMLElement('<?xml version="1.0"?><data></data>');
        }

        foreach ($data as $key => $value) {
            if (is_numeric($key)) {
                $key = 'item';
            }
            if (is_array($value)) {
                $subnode = $xml_data->addChild($key);
                self::array_to_xml($value, $subnode);
            } else {
                $xml_data->addChild("$key", htmlspecialchars("$value"));
            }
        }
    }

    public static function removeNamespacesFromXMLString($xml)
    {
        $xml = preg_replace('/(<\s*)\w+:/', '$1', $xml); // removes <xxx:
        $xml = preg_replace('/(<\/\s*)\w+:/', '$1', $xml); // removes </xxx:
        $xml = preg_replace('/\s+xmlns:[^>]+/', '', $xml); // removes xmlns:...
        return $xml;
    }

    /**
     * Get if set
     */
    public static function gis(&$value, $default = null)
    {
        return isset($value) ? $value : $default;
    }

    /**
     *
     * @return string[]
     */
    public static function getUrlParts($url)
    {
        return explode('/', parse_url($url)['path']);
    }

    public static function getRequestValue($key, $default = null)
    {
        return isset($_REQUEST[$key]) ? $_REQUEST[$key] : $default;
    }

    public static function unhandledErrorHandler($num, $str, $file, $line, $context = null)
    {
        su::unhandledExceptionHandler(new ErrorException($str, 0, $num, $file, $line));
    }

    /**
     * Uncaught exception handler.
     */
    public static function unhandledExceptionHandler(Throwable $e)
    {
        $message = "Type: " . get_class($e) . "; Message: {$e->getMessage()}; File: {$e->getFile()}; Line: {$e->getLine()};";
        try {
            Log::fatal('Uncaught: ' . $message);
            Mail::sendAlertFromException($e);
        } catch (Throwable $u) {
            error_log($message);
        }

        Execution::externalException($e);
    }

    /**
     * Checks for a fatal error, work around for set_error_handler not working on fatal errors.
     */
    public static function phpShutdownfunction()
    {
        $error = error_get_last();
        $schedId = session::getScheduleId();
        if ($schedId instanceof \MongoDB\BSON\ObjectId) {
            // This would normally be in Run.php destructor but since destructors are not executed on fatal errors...
            Schedule::finalizeRun($schedId);
        }
        if ($error["type"] == E_ERROR) {
            $msg = $error["type"] . $error["message"] . $error["file"] . $error["line"];
            Log::fatal('PHP is shutdown with error: ' . $msg);
            $e = new Exception($msg);
            Mail::sendAlertFromException($e);
            Execution::externalException($e);
        }
    }

    /**
     * Encrypt a string
     *
     * @param string $message
     *            - message to encrypt
     * @return string
     * @throws RangeException
     */
    public static function encrypt(string $message, $key = null): string
    {
        if (empty($key)) {
            $key = Config::getEncKey();
        }

        $key = Defuse\Crypto\Key::loadFromAsciiSafeString($key);

        return Defuse\Crypto\Crypto::encrypt($message, $key);
    }

    /**
     * Decrypt a message
     *
     * @param string $encrypted
     *            - message encrypted with safeEncrypt()
     * @param string $key
     *            - encryption key
     * @return string
     * @throws Exception
     */
    public static function decrypt(string $encrypted): string
    {
        $key = Config::getEncKey();
        $key = Defuse\Crypto\Key::loadFromAsciiSafeString($key);

        return Defuse\Crypto\Crypto::decrypt($encrypted, $key);
    }

    /**
     * Convert anything to bool
     *
     * Filter:
     * Returns TRUE for "1", "true", "on" and "yes"
     * Returns FALSE for "0", "false", "off" and "no"
     * Returns NULL otherwise.
     *
     * @param mixed $str
     * @return boolean
     */
    public static function toBool($str)
    {
        if (is_bool($str)) {
            return $str;
        }
        if (is_null($str)) {
            return false;
        }

        return filter_var($str, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     *
     * @param mixed $id
     * @return \MongoDB\BSON\ObjectId
     */
    public static function strToObjectId($id)
    {
        if ($id instanceof MongoDB\BSON\ObjectId) {
            return $id;
        }
        if (empty($id)) {
            throw new ValidationError('Cannot convert empty string to BSON object ID');
        }
        
        try {
            $res = new MongoDB\BSON\ObjectId($id);
        } catch (Throwable $e) {
            log::error($e);
            throw new ValidationError($e->getMessage());
        }
        
        return $res;
    }

    /**
     * Not actually sure what..
     *
     * @param array $array
     * @param string $column
     * @param string $value
     * @return mixed
     */
    public static function arraySearchMultidim($array, $column, $value)
    {
        $key = (array_search($value, array_column($array, $column)));
        return $array[$key];
    }

    /**
     * Get git version ref
     *
     * @return string
     */
    public static function getVersionRef()
    {
        $k = 'gitVersionRef';
        $ref = session::getKeyRequestScope($k);

        if (! empty($ref)) {
            return $ref;
        }
        $ref = file_get_contents('/etc/hostname');
        if (empty($ref)) {
            throw new Exception('No git ref');
        }
        $ref = trim($ref);
        session::setKeyReqestScope($k, $ref);
        return $ref;
    }

    public static function getStringAfterSubstring($string, $substring)
    {
        return substr($string, strpos($string, $substring) + strlen($substring));
    }
    
    public static function endOB() {
        $ob = ob_get_clean();
        if (!empty(trim($ob)) ) {
            mail::sendAlertFromException(new GenException('Output buffer was not empty: '.$ob));
        }
        return $ob;
    }
}