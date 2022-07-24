<?php

/** Simple test/tutorial that fetches page $_GET['url'] and returns the HTML after
 * loading and javascript interpretation
 */

require( __DIR__.'/../instanciate.php' );

try {
    if ( ! $url = @$_GET['url'] ) {
        die("You should provide a url GET parameter (?url=https://something)");
    }
    $instance = $pool->acquire($url); // Acquiring an available geckodriver instance, if any
    // Parameter $url is provided for logging purpose and identifies the current thread in logging stream

    // Once you have $instance, you can work directly on the driver object by doing:
    // $driver = $instance->getDriver(); // returns a Facebook\WebDriver object
    // Read https://github.com/php-webdriver/php-webdriver documentation.
    //
    // Or by overloading class SeleniumTools and implement high level 
    // operations in it (some already exist).
    // Here we use the second solution:

    $helper = $instance ? $instance->getDriverTools() : false;

    if ( $helper ) {
        echo $helper -> get($url)
                -> waitForComplete(3)
                -> getHTML();
        $helper -> closeAndRelease(); // Normal release. No error generated.
        // SeleniumTools provides this convenient method closeAndRelease()
        // that both releases the instance and makes it available again,
        // and closes any opened window in firefox to prevent dynamic webpages
        // to continue running.
        // Alternately, you could directly call $instance->release();
    } else {
        echo "Could not acquire an available session !";
    }

} finally {
    $pool->releaseAll( true ); // You should always terminate with this.
    // It will release the session anyway, and increment its error counter if
    // it has not been released normally before.
}
