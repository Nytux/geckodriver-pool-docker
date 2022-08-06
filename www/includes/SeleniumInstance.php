<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Firefox\FirefoxOptions;
use Facebook\WebDriver\Firefox\FirefoxProfile;
use Facebook\WebDriver\Firefox\FirefoxDriver;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Chrome\ChromeDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\WebDriverBy;

class SeleniumInstance {

    private $port = false;
    private $sessId = false;
    private $pid = false;
    private $acquired = false;
    private $failures = 0;
    private $idx;
    private $storage;
    private $pool;

    public function __construct( $pool, $idx ) {
        $this->pool = $pool;
        $this->storage = $pool->getStorage();
        $this->idx = $idx;
        $this->load();
    }

    public function log( $row ) { $this->pool->log($row); }

    public function getSessId() {
        return $this->sessId;
    }

    public function getIdx() {
        return $this->idx;
    }

    public function setPort( $port ) {
        $this->port = $port;
    }

    public function getPort() { return $this->port; }

    public function getFailures() { return $this->failures; }

    public function getUrl() {
        return "http://localhost:".$this->port;
    }

    public function taken() {
        return $this->acquired;
    }

    public function acquire() {
        $this->acquired = time();
        $this->store();
    }

    /** Number of seconds it's been acquired. 0 if not acquired.
     */
    public function howLongAcquired() {
        return $this->acquired ? time() - $this->acquired : 0;
    }

    public function testSessionAlive() {
        $ch = curl_init();
        try {
            curl_setopt($ch, CURLOPT_URL, "{$this->getUrl()}/session/{$this->sessId}/window" );
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
            curl_setopt($ch, CURLOPT_TIMEOUT,        4);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $rt = curl_exec($ch);
            $handle = json_decode( $rt, false )->value;
            if ( !(is_string($handle)&&preg_match( 
                '%^[-0-9a-f]+$%', 
                $handle,
            )) ) throw new Exception("Session window value returned is malformed : ".print_r($handle,true));
            $this->log("Test on session {$this->port} passed");
        } catch ( Exception $e ) {
            $this->log("Test failed on session {$this->port} : ".$e->getMessage());
            return false;
        } finally {
            curl_close($ch);
        }
        return true;
    }

    public function release($normal=true) {

        $this->acquired = 0;
        if ( $normal ) $this->failures = 0;
        $this->store();
        $this->log("Releases session {$this->sessId}");
        $this->pool->notifyReleased( $this );
    }

    public function incFailureCount() {
        $this->failures++;
        $this->log("Marking one failure for session {$this->sessId} : total {$this->failures} failures");
        $this->store();
    }

    public function load() {
        $data = $this->storage->getSeleniumInstance( $this->idx );
        if ( $data ) {
            $this->pid = $data['pid'];
            $this->port = $data['port'];
            $this->sessId = $data['sessId'];
            $this->acquired = $data['acquired'];
            $this->failures = $data['failures'] ?: 0;
        } else {
            $this->sessId = $this->port = false;
        }
    }

    public function store() {
        $this->storage->setSeleniumInstance( $this->idx, 
            [
                'pid'=>$this->pid,
                'port'=>$this->port,
                'sessId'=>$this->sessId,
                'acquired'=>$this->acquired,
                'failures'=>$this->failures
            ]
        );
    }

    private function _getDriver() {
        return $this->sessId ?
            RemoteWebDriver::createBySessionID( $this->sessId, $this->getUrl() ) : 
            false;
    }

    /** Exception proof version of _getDriver : gets the selenium driver and restarts it if needed
     */
    public function getDriver() {
        if ( !$this->sessId ) return $this->start();
        try {
            $driver = $this->_getDriver();
            if ( !$driver ) {
                $this->sessId = false;
                return $this->start();
            }
            return $driver;
        } catch ( Exception $e ) {
            $this->sessId = false;
            return $this->start();
        }
    }

    public function getDriverTools() {
        return new SeleniumTools( $this );
    }

    public function launch() {
        if ( $this->pid ) { 
            if ( $this->sessId ) {
                try {
                    $driver = $this->_getDriver();
                    if ( $driver ) {
                        $driver->quit();
                    }
                } catch ( Exception $e ) {}
            }
/*            echo "Killing ".$this->pid."\n";
            posix_kill( $this->pid, 9 );
*/
            $this->pool->kill( $this->pid );

            $this->pid = false;
            $this->sessId = false;
            sleep(1);
        }

        $this->pid = $this->pool->launch( $this->port );
        sleep(2);
    }

    public function start() {

        if ( $this->sessId ) {
            try {
                $driver = $this->_getDriver();
                if ( $driver ) {
                    $driver->quit();
                }
            } catch ( Exception $e ) {}
        }

        // $desiredCapabilities = DesiredCapabilities::chrome();

        $desiredCapabilities = DesiredCapabilities::firefox();

        // Add arguments via FirefoxOptions to start headless firefox
        $firefoxOptions = new FirefoxOptions();

        if ( $this->pool->isHeadless() ) {
            $firefoxOptions->addArguments(['-headless']);
        }
        
        $desiredCapabilities->setCapability(FirefoxOptions::CAPABILITY, $firefoxOptions);

/*
        FirefoxProfile profile = new FirefoxProfile(); 
        profile.setPreference(“permissions.default.image”, 2); 
        options.setProfile(profile);
        options.setCapability(FirefoxDriver.PROFILE, profile);
*/
            
        $profile = new FirefoxProfile();

        $desiredCapabilities->setCapability( FirefoxDriver::PROFILE, $profile );
        
        for ( $ctRetry = 0 ; $ctRetry < 5 ; $ctRetry++ ) {
            try {
                $driver = RemoteWebDriver::create( $this->getUrl(), 
                    $desiredCapabilities,
                    4*1000,
                    4*1000
                );
                $this->sessId = $driver->getSessionID();
                $this->acquired = 0;
                $this->failures = 0;
                $this->store();
                break;
            } catch ( Exception $e ) {
                if ( strncmp( $e->getMessage(),'Curl error',10 ) === 0 ) {
                    $this->log("Could not contact the driver yet. Waiting a little.");
                    sleep(1);
                } else throw $e;
            }
        }

        foreach (glob(__DIR__.'/../plugins/*.xpi') as $filename) {

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "{$this->getUrl()}/session/{$this->sessId}/moz/addon/install" );
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $payload = json_encode( [
                'path'=>$filename,
    //            '/var/plugins/i_dont_care_about_cookies-3.4.1.xpi',
    //            __DIR__.'/../plugins/i_dont_care_about_cookies-3.4.1.xpi',
                'temporary'=>false] );
            curl_setopt( $ch, CURLOPT_POSTFIELDS, $payload );
            curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $this->log("Sent plugin request to session {$this->sessId} : Got $httpCode ($result)");
        }
/*        if ( $this->pool->hasCookieFile() ) {
            $cookies = $this->pool->getCookies();
            $options = $driver->manage();
            foreach ( $cookies as $cookie ) {
                $options->addCookie( $cookie );
            }
        }
*/
        return $driver;
        
    }

}