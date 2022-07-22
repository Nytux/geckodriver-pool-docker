<?php

require_once( __DIR__.'/SeleniumInstance.php' );

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverDimension;
use Facebook\WebDriver\WebDriverKeys;

class SeleniumTools {

    public $driver;
    public $flog = false;   // file descriptior to store log into
    private $instance;
    private $referer = false;

    private $contentType = '';
    private $metaTags = false;

    public function __construct( $instance ) {
        $this->driver = $instance->getDriver();
        $this->instance = $instance;
    }

    public function log($row) {
        $this->instance->log($row);
    }

    public function getDriver() { return $this->driver; }

    public function get( $url ) {
        $this->driver->get($url);
        return $this;
    }

    private function normalizeContentType() {
        if ( strncmp( $this->contentType, 'application/xhtml+xml', 21 ) === 0 ) {
            $this->contentType = 'text/html'.substr($this.contentType,0,21);
        }
    }

    /** Get current url after redirections. This should be called after waitForComplete.
     */
    public function getUrl() {
        return $this->driver->getCurrentURL();
    }

    public function getContentType() {
        $this->contentType = $this->driver->executeScript("
            let charset = document.characterSet
            return document.contentType+(charset?('; charset='+charset.toLowerCase()):'')
        ");
        $this->normalizeContentType();
        return $this->contentType;
    }

    public function isImage() {
        return strncmp( $this->contentType, "image/", 6 ) === 0;
    }

    public function isHtml() {
        return strncmp( $this->contentType, 'text/html', 9 ) === 0;
    }

    /** To be called on pages that assert method isImage().
     * Returns the actual image url.
     */
    public function getImageUrl() {
        return $this->driver->findElement(
                WebDriverBy::tagName('img')
            ) -> getAttribute('src');
    }


    /** When engaged in the process of getting a page,
     * this will wait until the page is loaded and all onLoad javascript is executed.
     * Will not block more than $timeout seconds.
     */
    public function waitForComplete( $timeout ) {

        $tWait = time();
        $state = '';
        while (
            ( time() - $tWait <= $timeout ) &&
            ( ( $state = $this->driver->executeScript("return document.readyState") ) != "complete" )
        ) {
            usleep(100);
        }
        return $this;
    }

    public function getMetaTags() {
        if ( !$this->metaTags ) {
            $this->metaTags = $this->driver->findElements(WebDriverBy::tagName('meta'));
        }
        return $this->metaTags;
    }

    /** Redirects now if document contains a <meta http-equiv="refresh"... directive
     */
    public function resolveRefresh() {
        try {
            foreach ( $this->getMetaTags() as $meta ) {
                if ( strtolower($meta->getAttribute('http-equiv')) == 'refresh' ) {
                    if ( preg_match("/^([0-9]+)\\s*;\\s*url\\s*=\\s*(\\S+)/",$meta->getAttribute('content'), $pregs ) ) {
                        $delay = (int)$pregs[1];
                        $url =  $pregs[2];
                        $this->get($url);
                        return true;
                    }
                }
            }
        } catch (Exception $e) {}
        return false;
    }

    /** Gets the html content of the WebDriver $driver current document.
     * This includes all javascript modifications to the document.
     */
    public function getHtml() {
        return $this->driver->executeScript("return document.documentElement.outerHTML");
    }

    public function getPageSource() {
        return $this->driver->getPageSource();
    }

    /** Both releases the instance and makes it available again,
     * and closes any opened window in firefox to prevent dynamic webpages
     * to continue running.
     */
    public function closeAndRelease() {
        $this->driver->newWindow();
        $newWindow = $this->driver->getWindowHandle();
        foreach ( $this->driver->getWindowHandles() as $window ) {
            if ( $window != $newWindow ) {
                $this->driver->switchTo()->window($window);
                $this->driver->close();
            }
        }
        $this->driver->switchTo()->window($newWindow);
        $this->instance->release();
    }

    public function resize( $width, $height ) {
        $this->driver->manage()->window()->setSize(
            new WebDriverDimension( $width, $height )
        );
        return $this;
    }

    function llog( $line, $object=null ) {
        if ( $this->flog ) {
            if ( @$object ) 
                $line .= ' '.print_r($object,true);
            fwrite( $this->flog, $line."\n" );
        }
    }    
}