TicketsToMongodb
================

This will extract various Zendesk elements to csv files



Setup 
------

This requires the following:

-   Web server to receive API calls from Zendesk

-   Mongodb





Installation 
-------------

1.  `git clone` this repository.

2.  Download composer: `curl -s https://getcomposer.org/installer | php`

3.  Install TicketsToMongodb dependencies: `php composer.phar install`

4.  `cp config.php.default config.php`

5.  Edit config.php

6.  Add the following line to your (or whomever's) crontab:

    `* * * * * cd /path/to/project && php jobby.php 1>> /dev/null 2>&1`



Usage 
------

more to come
