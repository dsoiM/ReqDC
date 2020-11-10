<?php
use MongoDB\Collection;
use MongoDB\BSON\Binary;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Driver\Exception\Exception as MongoDBException;

class MongoSessionHandler implements \SessionHandlerInterface
{

    private $collection;

    private $logger;

    public function __construct(Collection $collection)
    {
        $this->collection = $collection;
    }

    public function open($_save_path, $_name)
    {
        return true;
    }

    public function close()
    {
        return true;
    }

    public function read($id)
    {

        $session = $this->collection->findOne([
            '_id' => $id
        ], [
            'projection' => [
                'data' => 1
            ]
        ]);

        if ($session) {

            return $session['data']->getData();
        } else {
            log::debug("No session {$id} found, returning no data");

            return '';
        }
    }

    public function write($id, $data)
    {
        $session = [
            '_id' => $id,
            'data' => new Binary($data, Binary::TYPE_GENERIC),
            'last_accessed' => new UTCDateTime(floor(microtime(true) * 1000)),
            'userId' => (session::getUser() ? session::getUser()->getId() : null)
        ];

        try {
            //log::debug("Saving data {$data} to session {$id}");
            $this->collection->replaceOne([
                '_id' => $id
            ], $session, [
                'upsert' => true
            ]);

            return true;
        } catch (MongoDBException $e) {
            log::error("Error when saving {$data} to session {$id}: {$e->getMessage()}");

            return false;
        }
    }

    public function destroy($id)
    {
        log::debug("Destroying session {$id}");

        try {
            $this->collection->deleteOne([
                '_id' => $id
            ]);

            return true;
        } catch (MongoDBException $e) {
            log::error("Error removing session {$id}: {$e->getMessage()}");

            return false;
        }
    }

    public function gc($maxlifetime)
    {
        $lastAccMin = time() - $maxlifetime;
        $lastAccessed = new UTCDateTime($lastAccMin * 1000);

        try {
            log::debug("Removing any sessions older than {$lastAccessed}");
            $this->collection->deleteMany([
                'last_accessed' => [
                    '$lt' => $lastAccessed
                ]
            ]);

            return true;
        } catch (MongoDBException $e) {
            log::error("Error removing sessions older than {$lastAccessed}: {$e->getMessage()}");

            return false;
        }
    }
}