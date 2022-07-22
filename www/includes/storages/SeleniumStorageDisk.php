<?php

require_once( __DIR__ . '/../SeleniumStorageInterface.php');

class SeleniumStorageDisk implements SeleniumStorageInterface {

    private $directory = null;

    public function __construct( $directory ) {
        $this->directory = $directory;
    }

    private function fileName( $idx ) {
        return "{$this->directory}/$idx.json";
    }

    /** Gets the instance at index $idx. Should return 
     * ['port'     => Port of the web driver handeling the instance,
     *  'pid'      => Pid of the web driver running, (one web driver per instance)
     *  'sessId'   => Session id of the running session in the web driver, or false if no instance running yet
     *  'acquired' => Timestamp of instance being acquired, or false if not acquired
     * ]
     */

    public function getSeleniumInstance( $idx ) {
        
        $data = @file_get_contents( $this->fileName($idx) );
        return $data ? json_decode($data,true) : false;
    }


    /** Stores the instance at index $idx. $data should have the same format as
     * the return of getSeleniumInstance.
     */

    public function setSeleniumInstance( $idx, $data ) {

        file_put_contents( $this->fileName($idx), json_encode($data) );
    }

}