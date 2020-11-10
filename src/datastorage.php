<?php

/**
 * TenantId + KeyUpper + CategoryUpper = Unique combo
 *
 * @author vehja
 *
 */
class DataStorage
{

    private $_id;

    /**
     * Indicates a high-level category for this data
     *
     * @var string
     */
    public $category;

    public $categoryUpper;

    /**
     * Key for this value, key is always a string of camelcase
     *
     * @var string
     */
    public $key;

    public $keyUpper;

    /**
     * Value for this key.
     * It can be a string or an array which gets converted to JSON document in the DB
     *
     * @var mixed
     */
    public $value;

    /**
     * TenantId, business as usual
     *
     * @var string
     */
    public $tenantId;

    public $type;

    const TYPES = [
        'string',
        'string_encrypted'
    ];

    /**
     *
     * @param string $category
     * @param string $key
     * @return mixed
     */
    public static function get(string $category, string $key, bool $exceptions = true)
    {
        $res = db::data()->findOne([
            'keyUpper' => mb_strtoupper($key),
            'categoryUpper' => mb_strtoupper($category)
        ] + db::tenantFilter());

        if (! $res) {
            $m = 'Data storage entry not found by Category: "' . $category . '" -- Key: "' . $key . '"';
            if ($exceptions === true) {
                throw new DataStorageException($m);
            } else {
                // You could write a warning log here but meh...
                return null;
            }
        }

        
        $ds = DataStorage::byDoc($res);

        return $ds->getValue();
    }

    /**
     *
     * @param string $category
     * @param string $key
     * @throws DataStorageException
     * @return boolean
     */
    public static function delete(string $category, string $key)
    {
        
        if (session::ifUserIsSelectedTenantAdmin() !== true) {
            throw new AccessDeniedException('Datastorage DELETE is only for tenant admins');
        }
        
        $res = db::data()->deleteOne([
            'keyUpper' => mb_strtoupper($key),
            'categoryUpper' => mb_strtoupper($category),
            'tenantId' => session::getSelectedTenantId()
        ]);

        if ($res->getDeletedCount() !== 1) {
            throw new DataStorageException('Not deleted: ' . $category . ' Key: ' . $key);
        }

        return true;
    }

    /**
     *
     * @param MongoDB\Model\BSONDocument $DBRow
     */
    private static function byDoc(MongoDB\Model\BSONDocument $DBRow)
    {
        $ds = new DataStorage();
        foreach ($DBRow as $k => $v) {
            $ds->{$k} = $v;
        }

        if ($ds->isEncrypted()) {
            $ds->setValue(su::decrypt($ds->getValue()));
        }
        return $ds;
    }

    /**
     * Set a value to datastorage.
     * It will use category+key combination as unique identifier
     *
     *
     * @param string $category
     * @param string $key
     * @param mixed $value
     * @param boolean $upsert
     *            if true then insert is done if no existing found
     * @param string $type
     */
    public static function set(string $category, string $key, $value, $upsert = true, $type = "string"): void
    {
        
        if (session::ifUserIsSelectedTenantAdmin() !== true) {
            throw new AccessDeniedException('Datastorage SET is only for tenant admins');
        }
        
        $d = new DataStorage();

        $d->setCategory(trim($category));
        $d->setKey(trim($key));
        $d->setType($type);
        $d->setCategoryUpper(mb_strtoupper($category));
        $d->setKeyUpper(mb_strtoupper($key));
        $d->setTenantId(session::getSelectedTenantId());
        $d->setValue($value);
        $d->saveToDb($upsert);
    }

    /**
     *
     * @return \MongoDB\Driver\Cursor
     */
    public static function getListingForViewTable()
    {
        return db::data()->find(db::tenantFilter());
    }

    /**
     *
     * @param string $type
     * @return boolean
     */
    public static function isTypeEncrypted($type)
    {
        return stripos($type, '_encrypted') !== false;
    }

    public function isEncrypted()
    {
        return self::isTypeEncrypted($this->type);
    }

    /**
     *
     * @param boolean $upsert
     * @throws DataStorageException
     */
    private function saveToDb($upsert = true)
    {
        if (empty($this->getKeyUpper()) || empty($this->getCategoryUpper())) {
            throw new DataStorageException('Data storage key and category must not be empty');
        }

        if ($this->isEncrypted()) {
            $this->setValue(su::encrypt($this->getValue()));
        }

        $insertOneResult = DB::data()->replaceOne([
            'tenantId' => session::getSelectedTenantId(),
            'keyUpper' => $this->getKeyUpper(),
            'categoryUpper' => $this->getCategoryUpper()
        ], $this, [
            'upsert' => $upsert
        ]);
        $id = ! empty($insertOneResult->getUpsertedId()) ? $insertOneResult->getUpsertedId() : $this->getId();

        if ($insertOneResult->getMatchedCount() === 0 && empty($insertOneResult->getUpsertedId())) {
            throw new DataStorageException('Failed to save ' . $insertOneResult->getMatchedCount());
        }
        $this->setId($id);
        Log::debug('Data storage entry saved to DB:' . $this->_id);
    }

    /**
     *
     * @return mixed
     */
    public function getId()
    {
        return $this->_id;
    }

    /**
     *
     * @param mixed $_id
     */
    public function setId($_id)
    {
        $this->_id = $_id;
    }

    /**
     *
     * @return string
     */
    public function getCategory()
    {
        return $this->category;
    }

    /**
     *
     * @param string $category
     */
    public function setCategory($category)
    {
        $this->category = $category;
    }

    /**
     *
     * @return mixed
     */
    public function getCategoryUpper()
    {
        return $this->categoryUpper;
    }

    /**
     *
     * @param mixed $categoryUpper
     */
    public function setCategoryUpper($categoryUpper)
    {
        $this->categoryUpper = $categoryUpper;
    }

    /**
     *
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     *
     * @param string $key
     */
    public function setKey($key)
    {
        $this->key = $key;
    }

    /**
     *
     * @return mixed
     */
    public function getKeyUpper()
    {
        return $this->keyUpper;
    }

    /**
     *
     * @param mixed $keyUpper
     */
    public function setKeyUpper($keyUpper)
    {
        $this->keyUpper = $keyUpper;
    }

    /**
     *
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     *
     * @param mixed $value
     */
    public function setValue($value)
    {
        $this->value = $value;
    }

    /**
     *
     * @return string
     */
    public function getTenantId()
    {
        return $this->tenantId;
    }

    /**
     *
     * @param string $tenantId
     */
    public function setTenantId($tenantId)
    {
        $this->tenantId = $tenantId;
    }

    /**
     *
     * @return mixed
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     *
     * @param mixed $type
     */
    private function setType($type)
    {
        if (! in_array($type, self::TYPES)) {
            throw new GenException('Type must be one of: ' . implode(',', self::TYPES));
        }
        $this->type = $type;
    }
}