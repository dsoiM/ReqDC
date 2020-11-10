<?php

class Tenant
{

    /**
     *
     * @var string
     */
    public $id;

    /**
     *
     * @var string
     */
    public $name;

    /**
     * These IDs are allowed to do basic operations, such as send data and read data
     *
     * @var string[]
     */
    public $allowedUserIds = [];

    /**
     * List of userId's who are admins for the tenant and can change passwords and ...
     *
     * @var array
     */
    public $adminUserIds = [];

    // TODO Test adminuserId handling

    /**
     *
     * @var string[]
     */
    public $allowedImplementations = [];

    /**
     *
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     *
     * @return string[]
     */
    public function getAllowedUserIds()
    {
        return $this->allowedUserIds;
    }

    /**
     *
     * @return string[]
     */
    public function getAllowedImplementations()
    {
        return $this->allowedImplementations;
    }

    /**
     * Instanciation only via static methods
     */
    private function __construct($id)
    {
        $this->id = $id;
    }

    /**
     *
     * @param int $id
     * @throws Exception
     * @return Tenant
     */
    public static function byId($id)
    {
        $res = DB::tenant()->findOne([
            'id' => $id
        ], [
            'typeMap' => [
                'allowedImplementations' => 'array'
            ]
        ]);

        if ($res) {
            $obj = new Tenant($id);
            foreach ($res as $k => $v) {
                $obj->{$k} = $v;
            }
        } else {
            throw new GenException('Tenant not found by ID: ' . $id);
        }
        return $obj;
    }

    /**
     *
     * @return int[]
     */
    public static function getAllowedTenantIdsForUserId($userId, $isAdmin = false)
    {
        if ($isAdmin === true) {
            $data = DB::tenant()->find();
        } else {
            $data = DB::tenant()->find([
                'allowedUserIds' => $userId
            ]);
        }

        $res = [];
        foreach ($data as $r) {
            $res[] = $r->id;
        }

        return $res;
    }
}