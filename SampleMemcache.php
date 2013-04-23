<?php

namespace \My\Sample\Service;

use Doctrine\Common\Cache\Cache;

/**
 * Our version of the Memcache class so we can override
 * as we see fit.
 *
 */
class SampleMemcache extends \Memcache implements Cache
{

    private $prefix;
    private $containerKey;
    private $debug;

    /**
     * Default constructor
     *
     * @param string $prefix
     * @param boolean $debug echoes set/get keys to stdout, default false
     */
    public function __construct($prefix, $debug = false)
    {
        $this->prefix = $prefix['prefix'] . \gethostname();
        $this->debug = $debug;
    }

    /**
     * Allows us to take in [host1:port1;...hostN:portN] to define memcache
     * servers.
     *
     * @param string $url [host1:port1;...hostN:portN]
     * @param boolean $persistent
     * @param int $weight
     * @param int $timeout
     * @param int $retry_interval
     * @param boolean $status
     * @param callable $failure_callback
     * @param int $timeoutms
     *
     * @return boolean
     *
     * @throws \InvalidArgumentException
     */
    // @codingStandardsIgnoreStart
    public function addserver($url, $persistent = true, $weight = 1, $timeout = 1, $retry_interval = 15, $status = true, $failure_callback = null, $timeoutms = null)
    {
        if (empty($url)) {
            throw new \InvalidArgumentException('Missing required $url parameter [host1:port1;...hostN:portN]');
        }

        $servers = \explode(';', $url);

        foreach ($servers as $server) {
            $host = \explode(':', $server);
            if (empty($host[0]) || empty($host[1])) {
                throw new \InvalidArgumentException('Missing required $host definition [host1:port1]');
            }

            parent::addserver($host[0], $host[1], $persistent, $weight, $timeout, $retry_interval, $status, $failure_callback, $timeoutms);
        }

        return true;
    }

    // @codingStandardsIgnoreEnd

    /**
     * Gets your key, we append the prefix for you.
     * We will walk your array of keys too and append the prefix.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function get($key)
    {
        $get = false;
        if ($this->doPrefix($key)) {
            try {
                if ($this->debug)
                    echo "\n Cache::get $this->prefix$key\n";
                $get = @parent::get($this->prefix . $key);
            } catch (\Exception $e) {
                // swallow; symfony is interpreting connection refusal
                // warnings from memcache's secondary server as an
                // exception
                return $get;
            }
            return $get;
        }

        return @parent::get($key);
    }

    /**
     * Sets your key, we append the prefix for you.
     *
     * @param string $key
     * @param mixed $var
     * @param int $flags
     * @param int $expire
     *
     * @return boolean
     */
    public function set($key, $var, $flags = \MEMCACHE_COMPRESSED, $expire = 86400)
    {
        $set = false;
        if ($this->doPrefix($key)) {
            try {
                if ($this->debug)
                    echo "\n Cache::set $this->prefix$key\n";
                $set = @parent::set($this->prefix . $key, $var, $flags, $expire);
            } catch (\Exception $e) {
                //swallow
                return $set;
            }
            return $set;
        }

        return @parent::set($key, $var, $flags, $expire);
    }

    /**
     * Replace your key, we append the prefix for you.
     *
     * @param string $key
     * @param mixed $var
     * @param int $flags
     * @param int $expire
     *
     * @return boolean
     */
    public function replace($key, $var, $flags = \MEMCACHE_COMPRESSED, $expire = 86400)
    {
        $replace = false;
        if ($this->doPrefix($key)) {
            try {
                $replace = @parent::replace($this->prefix . $key, $var, $flags, $expire);
            } catch (\Exception $e) {
                //swallow
                return $replace;
            }
            return $replace;
        }

        return @parent::replace($key, $var, $flags, $expire);
    }

    /**
     * Delete your key, we append the prefix for you.
     *
     * @param string $key
     *
     * @return boolean
     */
    public function delete($key)
    {
        $delete = false;
        if ($this->doPrefix($key)) {
            try {
                $delete = @parent::delete($this->prefix . $key);
            } catch (\Exception $e) {
                //swallow
                return $delete;
            }
            return $delete;
        }

        return @parent::delete($key);
    }

    /**
     * Deep cleaning done when the cache/{$env} directories
     * are missing.
     */
    public function deepClean()
    {
        // Clean up all of our entries (old and new).
        @parent::delete('class');
        @parent::delete($this->containerKey);
        @$this->delete('class');
        @$this->delete($this->containerKey);
    }

    /**
     * Interface methods for Cache impl.
     * We route through our extended class to get the prefixing
     * for memcache sharing across multiple environments.
     */

    /**
     * {@inheritdoc}
     */
    public function fetch($id)
    {
        return $this->get($id);
    }

    /**
     * {@inheritdoc}
     */
    public function contains($id)
    {
        if (false !== $this->get($id)) {
            return true;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function save($id, $data, $lifeTime = 0)
    {
        return $this->set($id, $data, null, $lifeTime);
    }

    /**
     * {@inheritdoc}
     */
    public function getStats()
    {
        $stats = parent::getStats();
        if (empty($stats)) {
            return null;
        }

        $retStats = array(
            Cache::STATS_HITS => $stats['get_hits'],
            Cache::STATS_MISSES => $stats['get_misses'],
            Cache::STATS_UPTIME => $stats['uptime'],
            Cache::STATS_MEMORY_USAGE => $stats['bytes'],
            Cache::STATS_MEMORY_AVAILIABLE => $stats['limit_maxbytes']
        );

        return $retStats;
    }

    /**
     * Set our container key so we don't step on the same
     * compiled container.
     *
     * @param string $key
     */
    public function setContainerKey($key)
    {
        $this->containerKey = $key;
    }

    /**
     * Our flush.
     */
    public function flush()
    {
        parent::flush();
        /**
         * http://www.php.net/manual/en/memcache.flush.php
         * See above link regarding 1 second wait after flush.
         */
        $time = \time() + 1; //one second
        while (\time() < $time) {
            unset($empty);
            //sleep
        }
    }

    /**
     * doPrefix checks to see if we're dealing with a
     * sf2 session $key.  We do not prepend the prefix
     * to their session keys.
     *
     * @param string $key
     *
     * @return boolean
     */
    private function doPrefix($key)
    {
        return ('sf$2s' != substr($key, 0, 5));
    }

}
