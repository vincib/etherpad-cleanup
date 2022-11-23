# etherpad-cleanup
A small php-cli script to cleanup your etherpad instance of older, unused pads. 

edit autoexpire.php to match your etherpad configuration, which means, the driver used (mysql or redis)  and the access (db/name/login/pass/port ...) 

chose how old you want to be before a pad is deleted (default 1year)

launch autoexpire.php, it will create static archives of your older pads in your data folder.

you can, if you want, change your nginx configuration using the proposed patches in nginx-pad.conf to serve those static archives as html pages at the SAME url <3

licence: CC0
