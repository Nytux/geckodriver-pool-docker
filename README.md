# Geckodriver-pool

Geckodriver-pool 
Copyright 2022 Fabien Coulon

## Licence

Geckodriver-pool is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
Geckodriver-pool is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
You should have received a copy of the GNU General Public License along with Geckodriver-pool. If not, see <https://www.gnu.org/licenses/>.

## Description 

Geckodriver-pool runs several geckodriver processes in one docker container. 
It is based on repository `instrumentisto/geckodriver`, which originally runs one geckodriver process.
In geckodriver-pool, multiple instances are kept alive, which means firefox is already running, available for being acquired by a process and released. It has been designed to provide a robust alternative to selenium grid.

## Build

You need docker installed.

In www/, run `php composer.phar install` to install php dependencies.

In root directory, 
* Check `geckodriver-pool.yml` content: environment variables and ports configuration.
* Run `./build.sh`

## Tutorial

The original content of `geckodriver-pool.yml` is:
```
version: "3"
services:
  geckodriver-pool:
    image: nytux/geckodriver-pool
    container_name: geckodriver-pool
    environment:
      - GECKOPOOL_N_INSTANCES=3
      - GECKOPOOL_ACQUIRE_DEADTIME=60
      - GECKOPOOL_MAX_FAILURES=1
    ports:
      - "127.0.0.1:8080:80"
    volumes:
      - ./my-app:/var/www/html
    deploy:
      resources:
        limits:
          cpus: "2.0"
          memory: 4G
```
Environment variables :
* `GECKOPOOL_N_INSTANCES` sets the number of concurrent geckodriver instances that should be running.
* `GECKOPOOL_ACQUIRE_DEADTIME` sets the maximal number of seconds an instance can be acquired before it is considered abandoned and restarted.
* `GECKOPOOL_MAX_FAILURES` sets the maximal number of consecutive times an instance may not have been released properly before it is considered malfunctioning and restarted. It is allowed to fail consecutively `GECKOPOOL_MAX_FAILURES` times and will be restarted when reaching `GECKOPOOL_MAX_FAILURES+1`


Ultimately,  you'll replace `./my-app` by a path to your local PHP application that implements all the logic of communicating with the geckodriver instances and provide an API access for the rest of your application, through the container port 80. For this tutorial, keep it like that.

We're going to try first with this small sample file. Create a sub-directory `my-app` in the same directory as `geckodriver-pool.yml` and place `test.php` in it :

```
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
```
Launch with `docker compose -f geckodriver-pool.yml up -d`. Then, try getting `http://127.0.0.1:8080/test.php?url=https://xkcd.com/`

Sources are available at https://github.com/Nytux/geckodriver-pool-docker

