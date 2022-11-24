<?php

// automatic expiration of pads whose modification date is more than 1 year ago.

require_once("driver-mysql.php");
require_once("driver-redis.php");

// create a store object by calling $store=new <class>($connection info); 
// class should be mysqlpads or postgresqlpads or redispads 

$dsn="mysql:host=localhost;dbname=publicpad";
$store=new mysqlpads($dsn,"dblogin","dbpassword");

// could be this is you use redis store instead:
// $store=new redispads("127.0.0.1",6379);


// pads not modified for a year (365 days) will be deleted
$older = time()-86400*365; 

// pad root url : MUST end with / !
$padurl = "https://mypad.mydomain.fr/";
// pad APIKEY.txt (you'll find it at the root of your etherpad filesystem)
$apikey = "34b1b4b8935a12870e5b1e059825aefebfd1b3e2";
// data root: we will create the following folders there : 
// old-pad-exports = export etherpad format,   p = export html format,    private-pads = not accessible url for private html/etherpad exports.
$dataroot="/var/www/publicpad/data";


// --------------------------------------------------------------------------------
// this is the beginning of the main code, please don't touch it ;) 

// force-delete a pad, takes a store object and a padid as parameter
function force_delete_pad($store,$pad) {

    // first we enumerate all the pad revs and chat: 
    $keys=[];

    $store->enumerate("pad:$pad:revs:");
    while (($next = $store->next())!=false) {
        $keys[]=$next;
    }
    $store->end();

    $store->enumerate("pad:$pad:chat:");
    while (($next = $store->next())!=false) {
        $keys[]=$next;
    }
    $store->end();

    // then the known static key name:
    $keys[]="pad:".$pad;
    $keys[]="comments:".$pad;
    $keys[]="mypads:pad:".$pad;

    // delete all those:
    foreach($keys as $one) {
        echo "deleting $one\n";
        $store->del($one);
    }
}


echo "Step 1: enumerate the pads\n";
$store->enumerate("pad:");
$f=fopen("pads.txt","wb");
$padcount=0;
while(($k=$store->next())!==false) {
    // we save the list of pads. 
    if (strpos(substr($k,4),":")===false) {
        fputs($f,trim(substr($k,4))."\n");
        $padcount++;
        if ($padcount%10==0) echo ".";
        if ($padcount%1000==0) echo " $padcount\n";
    }
}
$store->end();
echo "\n";
fclose($f);
echo "Found $padcount pads\n";


echo "Step 2: List the pads 'update date' & if they were used as protected 'mypads'. Save the one that will be archived to pads-archive.txt\n";
$f=fopen("pads.txt","rb");
$g=fopen("pads-archive.txt","wb");
$padcount=0;
while ($s=fgets($f,1024)) {
    $pad=trim($s);

    $padinfo=$store->get("pad:".$pad);
    if ($padinfo===false) {
        echo "\npad $pad invalid (1);\n";
        continue; // skip unknown pads :/ weird
    }
    $padinfo=json_decode($padinfo,true);
    $head=$padinfo["head"];
    $padinfo=$store->get("pad:".$pad.":revs:".$head);
    if ($padinfo==false) {
        echo "\npad $pad head $head invalid (2)\n";
        // we consider it to be created years ago : 
        $ts=0;
    } else {
        $padinfo=json_decode($padinfo,true);
        $ts=intval($padinfo["meta"]["timestamp"]/1000);
    }

// | mypads:pad:twitter-guardint-ddkk9rb                          | {"name":"Twitter_GUARDINT","group":"guardint-09e19ca","users":[],"visibility":"public","password":null,"readonly":null,"_id":"twitter-guardint-ddkk9rb","ctime":1578485684785}
    $mypad=$store->get("mypads:pad:".$pad);
    if ($mypad!=false) {
        $mypad=json_decode($mypad,true);
        //echo "found mypad: "; print_r($mypad);
        $mypad_protected = intval(!($mypad["visibility"]=="public"));
    } else {
        $mypad_protected=0;
    }
    if ($ts < $older) 
    fputs($g,$ts." ".$mypad_protected." ".$pad."\n");    
    $padcount++;
    if ($padcount%10==0) echo ".";
    if ($padcount%1000==0) echo " $padcount\n";
}
fclose($f);
fclose($g);

$now=date("Y-m-d");

echo "Step 3: delete and archive old pads.\n";
$f=fopen("pads-archive.txt","rb");

// prepare the data folders:
@mkdir($dataroot."/p");
@mkdir($dataroot."/old-pad-exports");
@mkdir($dataroot."/private-pads");
touch($dataroot."/private-pads/index.html");

while ($s=fgets($f,1024)) {
    list($ts,$mypad,$pad) = explode(" ",trim($s),3);
    // export the pad as HTML & etherpad
    $exporthtml = file_get_contents("https://pad.lqdn.fr/p/".urlencode($pad)."/export/html");
    https://pad.lqdn.fr/p/cotentin2022/export/etherpad
    $exportetherpad = file_get_contents("https://pad.lqdn.fr/p/".urlencode($pad)."/export/etherpad");
    if (! $exporthtml || !$exportetherpad) {
        echo "Can't export pad '".$pad."', skipping...\n";
    } else {
        $exporthtml = str_replace("<body>","<body>\n"."<p>Pad archivé le ".$now." / This pad has been archived on ".$now.", You can access its <a href=\"/old-pad-exports/".$pad.".etherpad\">etherpad export here</a></p>\n<hr>\n",$exporthtml);
        if ($mypad=="0") {
            file_put_contents($dataroot."/old-pad-exports/".$pad.".etherpad",$exportetherpad);
            file_put_contents($dataroot."/p/".$pad.".html",$exporthtml);
        } else {
            file_put_contents($dataroot."/private-pads/".$pad.".etherpad",$exportetherpad);
            file_put_contents($dataroot."/private-pads/".$pad.".html",$exporthtml);
        }
        $deleted=file_get_contents($padurl."api/1/deletePad?apikey=".$apikey."&padID=".$pad);
        $deleted=json_decode($deleted,true);
        if ($deleted) {
            if ($deleted["message"]=="padID does not exist") {
                echo "Pad '$pad' could not be deleted, deleting it manually\n";
                force_delete_pad($store,$pad);
            }
        }
        // deleted:{"code":1,"message":"padID does not exist","data":null} (for !ùù )
        // in that case we may want to delete it manually : 
        // pad:<padid> pad:<padid>:revs:%  pad:<padid>:chat:% mypads:pad:<padid>
        echo "deleted:".str_replace("\n","",print_r($deleted,true))."\n";
        echo "pad '$pad' exported and deleted.";
    } // if export is fine 
    // wait for a minute: we may be rate-limited if we don't wait enough :/
    sleep(60);

} // for each pad to delete 
fclose($f);

