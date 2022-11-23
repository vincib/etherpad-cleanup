<?php

// a pilot to drive redis-based pads:

class redispads {
    private $redis=false;
    private $iterator=false;
    private $k=false;
    private $koffset=0;

/* constructor: host/port. If you need more parameters (like auth)
 * change the code below, and look at https://github.com/phpredis/phpredis#connection
 */
    function redispads( $host="127.0.0.1", $port=6379 ) {
        $this->redis=new Redis();

        if (!$this->redis->connect($host,$port)) {
            echo "Can't connect to Redis, exiting\n";
            exit();
        }

        $this->redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_NONE);	  // Don't serialize data
    }

    /* returns the value of a key. */
    function get($key) { 
        return $this->redis->get($key);
    }

    /* starts an enumeration, listing the keys that match a specific prefix.*/
    function enumerate($keyprefix="") {
        // only store the information, don't actually scan anything (next will)
        $this->redis->setOption(Redis::OPT_SCAN, Redis::SCAN_RETRY);
        $this->iterator=null;
        $this->keyprefix=$keyprefix;
        $this->k=false;
        $this->koffset=0;
        return true;
    }

    function next() {
        if ($this->k===false) {
            $this->k = $this->redis->scan($this->iterator, $this->keyprefix."*", 1000);
            $this->koffset=0;
        }
        if ($this->k===false) {
            return false;
        }

        $val=$this->k[$this->koffset];
        $this->koffset++;
        if ($this->koffset==count($this->k)) 
            $this->k=false; // will reenumerate at next call.

        return $val;
    }

    function end() {
        return true; // nothing to do here :) 
    }

    function del($key) {
        $this->redis->del($key);
    }
    
}
