<?php

require_once( __DIR__.'/SeleniumInstance.php' );

use Facebook\WebDriver\Cookie;

/** Handles a pool of geckodriver instances and dispatch them for use with locking mechanism 
 */

class TimeoutException extends Exception { }

class SeleniumPool {

    private $executable = 'geckodriver';
    private $nInstances = 6;
    private $timeout = 3;
    private $storage;
    private $releasingAll = false;
    private $headless = true;
    private $acquireDeadTime = 60;
    private $maxFailures = 1;
    private $cookieFile = false;
    private $acquiredPort = 0;
    
    private $acquiredInstances = [];
    private $logger;
    private $semHandle = null; // semaphore
    private $semAcquired = false;

    public function __construct( $storage, $ports, $logger=false ) {
        $this->storage = $storage;
        $this->ports = $ports;
        $this->nInstances = count($ports);
        $this->logger = $logger;
    }

    public function setLogger( $logger ) {
        $this->logger = $logger;
    }

    private function semAcquire( $retryUntil ) {

        if ( $this->semAcquired ) return true;
        if ( !$this->semHandle ) {
            $sem_key = ftok( __FILE__, "a" );
            $this->semHandle = sem_get( $sem_key );    
        }

        while ((
            ! ( $this->semAcquired = sem_acquire( $this->semHandle, true ) )
        ) && (
            time() < $retryUntil
        )) {
            usleep(200000);
        }

        return $this->semAcquired;
    }

    private function semRelease() {
        if ( $this->semAcquired ) sem_release( $this->semHandle );
        $this->semAcquired = false;
    }

    public function log($row,$obj=false) { 
        if ( $this->logger ) {
            if ( $this->acquiredPort ) {
                $row = "[{$this->acquiredPort}] $row" ;
            } else $row = "       $row";
            if ( $obj ) $row = "$row ; ".print_r($obj,true);
            call_user_func($this->logger,$row);
        }
    }

    /** Sets maximum number of seconds an instance can be acquired before being
     * considered lost forever in a bug and in need to be killed and restarted.
     */
    public function setAcquireDeadTime( $deadTime ) {
        $this->acquireDeadTime = $deadTime;
    }

    /** Sets the path to cooieFile, a netscape cookie file from where we'll load cookies 
     * at startup
     */
    public function setCookieFile( $filename ) {
        $this->cookieFile = $filename;
    }

    public function hasCookieFile() { return $this->cookieFile !== false; }

    /** When cookieFile is defined, returns an array of Cookie describing
     * cookies in cookieFile.
     */
    public function getCookies() {

        $content = $this->hasCookieFile() ? file_get_contents( $this->cookieFile ) : '';
        $content = $content ?: '';

        $cookies = array();
    
        $lines = explode("\n", $content);
    
        // iterate over lines
        foreach ($lines as $line) {
    
            // Removing curl #HttpOnly_ prefixes
            $httpOnly = ( strncmp( $line, '#HttpOnly_', 10 ) === 0 );
            if ( $httpOnly )  {
                $line = substr( $line, 10 );
            }

            // we only care for valid cookie def lines
            if ( substr_count($line, "\t") == 6 ) {
    
                // get tokens in an array
                $tokens = explode("\t", $line);
    
                // trim the tokens
                $tokens = array_map('trim', $tokens);
    
                $cookie = new Cookie( $tokens[5], $tokens[6] );
    
                $cookie->setPath( $tokens[2] );
                $cookie->setDomain( $tokens[0] );
                $cookie->setExpiry( $tokens[4] );
                $cookie->setSecure( $tokens[3]=='TRUE' );
                $cookie->setHttpOnly( $httpOnly );

                $cookies[] = $cookie;
            }
        }
        
        return $cookies;
    }

    /** Timeout in seconds for acquiring an available selenium instance. Acquire function
     * will return null instead of an instance if timeout is exceeded.
     */
    public function setTimeout( $timeout ) {
        $this->timeout = $timeout;
    }

        /** Sets maximum number of seconds an instance can be acquired before being
     * considered lost forever in a bug and in need to be killed and restarted.
     */
    public function setMaxFailures( $maxFailures ) {
        $this->maxFailures = $maxFailures;
    }

    /** Sets option headless : if true, firefox is started without window rendering
     * 
     */
    public function setHeadless($headless) {
        $this->headless = $headless;
        return $this;
    }

    public function isHeadless() {
        return $this->headless;
    }

    public function getStorage() { return $this->storage; }

    /** Launches process for port $port and returns pid
     */
    public function launch( $port ) {
        $command = ( $this->executable == 'chromedriver' ) ?
            ''.$this->executable.' --port='.$port.' > /dev/null & echo $!;' :
            ''.$this->executable.' -p '.$port.' > /dev/null & echo $!;';
        $this->log( "$ $command" );
        $rt = exec($command, $output);
        $this->log("> $rt");
        return (int)$rt;
    }

    public function kill( $pid ) {
//        $cmd = 'PID=`pgrep -f "'.$this->executable.' -p '.$port.'"` && pkill -9 -P $PID && kill -9 $PID';
        if ( $pid ) {
            $cmd = "pkill -9 -P $pid ; kill -9 $pid";
            $this->log( "$ $cmd" );
            $this->log( "> ".shell_exec($cmd) );
        }
//        echo "Executing ".'pkill -9 -f "'.$this->executable.' -p '.$port.'"';
//        shell_exec('pkill -9 -f "'.$this->executable.' -p '.$port.'"');
    }

    public function initialize() {
        $this->semAcquire(60);

        shell_exec('pkill -9 geckodriver ; pkill -9 firefox');
        foreach ( $this->ports as $idx => $port ) {
            $instance = new SeleniumInstance( $this, $idx );
            $instance->setPort( $port );
            $instance->launch();
            $instance->start();
        }

        $this->semRelease();
    }

    public function initializeSessions() {
        foreach ( $this->ports as $idx => $port ) {
            $instance = new SeleniumInstance( $this, $idx );
            $instance->setPort( $port );
            $instance->start();
        }
    }

    static public function handle_acquire_timeout() {
        throw new TimeoutException('Acquire timeout');
    }

    public function getInstanceByIndex($index) {
        return new SeleniumInstance( $this, $index );
    }

    private function syncAcquire() {
        $idx0 = rand(0,$this->nInstances-1);
        for ( $i = 0 ; $i < $this->nInstances ; $i++ ) {
            $idx = ( $idx0+$i )%$this->nInstances;
            $instance = new SeleniumInstance( $this, $idx );
            $this->acquiredPort = $instance->getPort();
            if ( ! $instance->taken() ) {
                if ( !$instance->testSessionAlive() ) {
                    $instance->incFailureCount();
                } else {
                    $instance->acquire();
                    $this->acquiredInstances[] = $instance;
                    return $instance;
                }
            }
        }
        $this->acquiredPort = 0;
        return null;
    }
      
    public function acquire($url='') {

        $instance = null;

        $start = time();

        while (( 
            ! $instance 
        ) && (
            time() - $start < $this->timeout
        )) {

            $this->semAcquire( $start + $this->timeout );

            if ( $this->semAcquired ) {
                $instance = $this->syncAcquire();
                $this->semRelease();

                if ( !$instance ) {
                    sleep(200000);
                }
            }
        }

        $this->log("For url $url");
        if ( $instance ) {
            $this->log("Acquired session {$instance->getSessId()}");
        } else {
            $this->log("Acquire timeout ".(time()-$start)." s ");
        }

        return $instance;
    }

    /** Called by an instance being released to notify its release to the pool.
     */
    public function notifyReleased( $instance ) {
        if ( $this->releasingAll ) return;
        $this->acquiredPort = 0;
        $this->acquiredInstances = array_filter(
            $this->acquiredInstances,
            function($i) use ( $instance ) {
                return $i->getIdx() != $instance->getIdx();
            }
        );
    }

    /** Releases all instances acquired by this pool, during this PHP session,
     * that has not been released yet.
     * It should be called in case of an Exception aborting the script.
     * Best practice is something like this:
     * 
     *  $pool = new SeleniumPool( $storage, $ports );
     *  try {
     *   .....
     *  } finally {
     *    $pool->releaseAll(true);
     *  }
     * 
     */
    public function releaseAll( $countAsFailure = true ) {
        $this->releasingAll = true;
        foreach ( $this->acquiredInstances as $instance ) {
            if ( $countAsFailure ) {
                if ( $instance->taken() ) {
                    $instance->incFailureCount();
                }
            }
            $instance->release(false); // abnormal release
        }
        $this->acquiredInstances = [];
        $this->releasingAll = false;
    }

    public function autoTests() {

        if ( !$this->semAcquire(10) ) {
            $this->log("Aborting autotests : semaphore is set");
            return;
        }
        for ( $idx = 0 ; $idx < $this->nInstances ; $idx++ ) {
            try {
                $instance = new SeleniumInstance( $this, $idx );
                $this->acquiredPort = $instance->getPort();
                $howLong = $instance->howLongAcquired();
                $nFailures = $instance->getFailures();
                $this->log( "Instance ".
                    ($howLong ? " acquired for $howLong seconds" : " is free").
                    " and has failed ".$nFailures." consecutive times" );
                if ((( $this->acquireDeadTime > 0 )&&( $howLong > $this->acquireDeadTime ))
                    ||
                    (( $this->maxFailures > 0 )&&( $nFailures > $this->maxFailures ))) {
                    $this->log( "Restarting instance" );
                    $instance->acquire();
                    $instance->launch();
                    $instance->start();
                }
            } catch ( Exception $e ) {
                $this->log("Exception : ".$e->getMessage());
            }
        }
    }
}