<?php


/** Abstracts storage of the pool of selenium instances
 */

interface SeleniumStorageInterface {

    /** Gets the instance at index $idx. Should return 
     * ['port'     => Port of the web driver handeling the instance,
     *  'pid'      => Pid of the web driver running, (one web driver per instance)
     *  'sessId'   => Session id of the running session in the web driver, or false if no instance running yet
     *  'acquired' => Timestamp of instance being acquired, or false if not acquired
     *  'failures' => Counts the number of consecutive failures (Exception) while using instance
     * ]
     */
    public function getSeleniumInstance( $idx );
    /** Stores the instance at index $idx. $data should have the same format as
     * the return of getSeleniumInstance.
     */
    public function setSeleniumInstance( $idx, $data );

}