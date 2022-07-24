<?php


require_once( __DIR__ . '/../SeleniumStorageInterface.php');

class SeleniumStorageMongo implements SeleniumStorageInterface {

    private $storage = null;

    public function __construct( $s, $database ) {
        $this->mng = new MongoDB\Driver\Manager($s);
        $this->database = $database;
    }

    /** Gets the instance at index $idx. Should return 
     * ['port'     => Port of the web driver handeling the instance,
     *  'pid'      => Pid of the web driver running, (one web driver per instance)
     *  'sessId'   => Session id of the running session in the web driver, or false if no instance running yet
     *  'acquired' => Timestamp of instance being acquired, or false if not acquired
     * ]
     */

    public function getSeleniumInstance( $idx ) {
        
        $filter = [ 'idx' => $idx ];
        $query = new MongoDB\Driver\Query($filter);
        $res = $this->mng->executeQuery($this->database, $query);
        $data = current($res->toArray());
        return (!empty($data)) ? (array)$data : false;
    }


    /** Stores the instance at index $idx. $data should have the same format as
     * the return of getSeleniumInstance.
     */

    public function setSeleniumInstance( $idx, $data ) {

        $filter = [ 'idx' => $idx ];
        $data['idx'] = $idx;
        $bulk = new MongoDB\Driver\BulkWrite;
        $bulk->update(
            $filter,
            ['$set' => $data],
            ['upsert'=>true,'multi'=>false]
        );
        $this->mng->executeBulkWrite($this->database, $bulk);
    }

}