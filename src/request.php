<?php

/**
 * Request describes which implementation should be executed and with what parameters
 *
 *
 * @author dso
 *
 */
abstract class Request
{

    /**
     * Contains raw body
     *
     * @var string
     */
    public $payload;

    // Possibilities: JSON, XML
    public $contentType;

    /**
     *
     * @var MongoDB\BSON\ObjectId
     */
    private $_id;

    /**
     * Populated if contentType = XML
     *
     * @var SimpleXMLElement
     */
    protected $xmlObject;

    /**
     * Populated if contentType = JSON
     *
     * @var array
     */
    protected $payloadArr;

    /**
     *
     * @var string
     */
    public $tenantId;

    /**
     *
     * @var string
     */
    public $implementationId;

    /**
     *
     * @var implementation
     */
    protected $implementation;

    /**
     * This is really used only when request is launched by Run
     *
     * @var string
     */
    public $userId;

    /**
     * Type of request
     *
     * @var string
     */
    public $reqClassName;

    public $time;

    /**
     * hostname where received/handled initially
     *
     * @var string
     */
    public $node;

    /**
     *
     * @param string $userId
     */
    public function setUserId($userId)
    {
        $this->userId = $userId;
    }

    protected function __construct(MongoDB\BSON\ObjectId $id = null)
    {
        $this->_id = $id;
        $this->reqClassName = get_class($this);
    }

    public function saveToDb()
    {
        $this->node = gethostname();
        $insertOneResult = DB::req()->insertOne($this);
        $this->_id = $insertOneResult->getInsertedId();
        Log::debug('New request saved to DB:' . $this->_id);
    }

    public function generateImplementationClassName()
    {
        if ($this instanceof RequestHttp) {
            $implName = $this->getImplementationClassNameFromHttpURL();
        /**
         * } elseif ($this->isFTP()) {
         * // asd
         * } elseif ($this->isMail()) {
         * // fg*
         */
        } elseif ($this instanceof RequestDirect) {
            // Direct request must have implementation set beforehand
            $implName = $this->implementationId;
        }

        if (empty($implName)) {

            throw new Exception('Couldnt process implementation class name');
        }

        $this->implementationId = $implName;
    }

    /**
     *
     * @return string
     */
    private function getImplementationClassName()
    {
        return $this->implementationId;
    }

    /**
     *
     * @return Implementation
     */
    public function getAndSetImplementation()
    {
        $this->generateImplementationClassName();
        $implName = $this->getImplementationClassName();

        $impl = Implementation::getById($implName);

        $impl->checkTenantPermissionFromRequest($this);
        $this->implementation = &$impl;
        return $impl;
    }

    public function getImplementation()
    {
        if (! isset($this->implementation)) {
            return $this->getAndSetImplementation();
        }
        return $this->implementation;
    }

    /**
     * If validate equals true, then validate payload according to content, duh
     *
     * @param string $payload
     * @param boolean $validate
     */
    protected function setPayload($payload, $parseAndValidate = true)
    {
        $this->payload = $payload;
        if (! $parseAndValidate) {
            return;
        }

        try {
            if (empty($payload) && $this->reqClassName !== 'RequestDirect') {
                throw new GenException('Request body cannot be empty');
            }

            if ($this->contentType === 'XML') {
                $this->parseAndValidateXMLpayload();
            } elseif ($this->contentType === 'JSON') {

                $this->parseAndValidateJSONpayload();
            } elseif ($this->contentType === 'CSV') {
                // $this->parseAndValidateCSVpayload();
            } else {
                // eh? We cant do squat
            }
        } catch (Throwable $e) {
            throw new ValidationError($e->getMessage());
        }
    }

    private function parseAndValidateXMLpayload()
    {
        libxml_use_internal_errors(true);
        $this->xmlObject = simplexml_load_string($this->payload, null, LIBXML_NSCLEAN | LIBXML_PARSEHUGE);
        if ($this->xmlObject === false) {
            $err = 'XML ';
            foreach (libxml_get_errors() as $error) {
                $err .= $error->message;
            }
            throw new ValidationError($err);
        }

        $this->xmlObjectcleanXMLNamespaces();
    }

    private function xmlObjectcleanXMLNamespaces()
    {
        $dom_sxe = dom_import_simplexml($this->xmlObject);

        $dom = new DOMDocument('1.0');
        $dom_sxe = $dom->importNode($dom_sxe, true);
        $dom_sxe = $dom->appendChild($dom_sxe);

        $element = $dom->childNodes->item(0);

        // See what the XML looks like before the transformation
        foreach ($this->xmlObject->getDocNamespaces(true) as $name => $uri) {
            $element->removeAttributeNS($uri, $name);
        }
        $this->xmlObject = simplexml_import_dom($dom);
    }

    private function parseAndValidateJSONpayload()
    {
        $this->payloadArr = json_decode($this->payload, true);
        if ($this->payloadArr === null) {
            $err = json_last_error_msg();
            throw new ValidationError('JSON: ' . $err);
        }
    }

    public function getPayloadArr()
    {
        return $this->payloadArr;
    }

    /**
     *
     * @return SimpleXMLElement
     */
    public function getXMLObject()
    {
        return $this->xmlObject;
    }

    public function xpath($xpath, $index = 0)
    {
        $res = $this->getXMLObject()->xpath($xpath);
        if (isset($res[$index])) {
            $return = $res[$index];
        } else {
            $return = null;
        }
        return $return;
    }

    /**
     */
    public function setContentType($contentType)
    {
        $this->contentType = ($contentType == 'XML' || $contentType == 'JSON') ? $contentType : null;
    }

    public function getContentType()
    {
        return $this->contentType;
    }

    /**
     *
     * @return \MongoDB\BSON\ObjectId
     */
    public function getId()
    {
        return $this->_id;
    }

    protected function setTenantId($id)
    {
        $this->tenantId = $id;
    }

    public function getTenantId()
    {
        return $this->tenantId;
    }

    /**
     * Request retrieval and object instancation by ID
     * Only place where you may skip tenant enforcing is Run.php since it needs the first request to know the tenant
     *
     * @param mixed $id
     * @throws Exception
     * @return RequestHttp|RequestDirect
     */
    public static function getById($id, $doNotEnforceTenantCheck = false)
    {
        $id = su::strToObjectId($id);
        if ($doNotEnforceTenantCheck === true) {
            $res = DB::req()->findOne([
                '_id' => $id
            ]);
        } else {
            $res = DB::req()->findOne([
                '_id' => $id,
                'tenantId' => Session::getSelectedTenantId()
            ]);
        }

        if ($res) {
            $obj = new $res->reqClassName($id);
            foreach ($res as $k => $v) {
                $obj->{$k} = $v;
            }
        } else {
            throw new Exception('Request not found by ID: ' . $id);
        }
        $obj->setPayload($obj->payload);

        return $obj;
    }

    public static function getListingForViewTable($beg, $end, $selection)
    {
        if ($selection) {
            $idfilter = [
                '_id' => su::strToObjectId($selection)
            ];
        } else {
            $idfilter = [];
        }
        $rows = db::req()->aggregate([
            0 => [
                '$match' => db::tenantFilter() + [
                    'time' => [
                        '$gte' => $beg,
                        '$lt' => $end
                    ]
                ] + $idfilter
            ],
            1 => [
                '$sort' => [
                    'time' => - 1
                ]
            ],
            2 => [
                '$limit' => 100
            ],
            3 => [
                '$lookup' => [
                    'from' => 'executions',
                    'localField' => '_id',
                    'foreignField' => 'requestId',
                    'as' => 'execs'
                ]
            ]
        ]);
        return $rows;
    }
}


/*
class RequestFtp extends Request
{
}

class RequestMail extends Request
{
}

class RequestScheduled extends Request
{
}

class RequestExecution extends Request
{
}
*/