<?php

/**
 * Holds all collections
 *
 * Create indexes to all fields that have high selectivity, meaning there is a high variation in the content of the
 * field that is queried.
 *
 *
 * @author dso
 *
 */
class DB
{

    /**
     * We reuse one global connection per collection per php thread
     * Dunno if this is more efficient but lets try at least
     *
     * @param string $collname
     * @return MongoDB\Collection
     */
    private static function getDB($collname)
    {
        global ${"mongo_$collname"};

        if (! isset(${"mongo_$collname"})) {
            ${"mongo_$collname"} = (new MongoDB\Client(Config::get('MONGODBURL')))->main->$collname;
        }
        return ${"mongo_$collname"};
    }

    /**
     *
     * @return MongoDB\Collection
     */
    public static function req()
    {
        return static::getDB('requests');
    }

    /**
     *
     * @return MongoDB\Collection
     */
    public static function log()
    {
        return static::getDB('log');
    }

    /**
     *
     * @return MongoDB\Collection
     */
    public static function exc()
    {
        return static::getDB('executions');
    }

    /**
     *
     * @return MongoDB\Collection
     */
    public static function tenant()
    {
        return static::getDB('tenants');
    }

    /**
     *
     * @return MongoDB\Collection
     */
    public static function user()
    {
        return static::getDB('users');
    }
    

    /**
     *
     * @return MongoDB\Collection
     */
    public static function sched()
    {
        return static::getDB('schedule');
    }

    /**
     *
     * @return MongoDB\Collection
     */
    public static function session()
    {
        return static::getDB('sessions');
    }

    /**
     *
     * @return MongoDB\Collection
     */
    public static function data()
    {
        return static::getDB('datastorage');
    }

    /**
     *
     * @return MongoDB\Collection
     */
    public static function impl()
    {
        return static::getDB('implementations');
    }

    /**
     * This is shorthand to add sorting by id ascending to db queries since its needed seemingly often
     *
     * @return number[]
     */
    public static function sortByIdAsc()
    {
        return [
            'sort' => [
                '_id' => - 1
            ]
        ];
    }

    /**
     * Shorthand for adding tenant filter.
     * Should be added by default to all database queries, except log or user collection
     * since there are no
     *
     * Sometimes upper level controls if tenant filter is used, so that param can be passed directly to bypass tenant filtering
     *
     * @param boolean $noTenantFilter
     * @return array|string[]
     */
    public static function tenantFilter($noTenantFilter = false)
    {
        if ($noTenantFilter === true) {
            return [];
        }
        return [
            'tenantId' => Session::getSelectedTenantId()
        ];
    }
    
    
    public static function initializeDB() {
        $files = ['main.tenants' => 'initial_collection_tenants.json','main.implementations' => 'initial_collection_implementations.json', 'main.users' => 'initial_collection_users.json'];
        $manager = new MongoDB\Driver\Manager(Config::get('MONGODBURL'));
        foreach ($files as $k => $filename) {
            $lines = file(Config::get('ROOTPATH').'config/'. $filename, FILE_IGNORE_NEW_LINES);
            
            $bulk = new MongoDB\Driver\BulkWrite;
            
            foreach ($lines as $line) {
                $bson = MongoDB\BSON\fromJSON($line);
                $document = MongoDB\BSON\toPHP($bson);
                $bulk->insert($document);
            }
            
            
            $result = $manager->executeBulkWrite($k, $bulk);
            if (!$result) {
                throw new GenException('Couldnt initialize db data');
            }
                
            
        }
        
    }
       
}