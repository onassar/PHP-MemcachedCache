<?php

    // dependecy checks
    if (!in_array('memcached', get_loaded_extensions())) {
        throw new Exception('Memcached extension needs to be installed.');
    }

    /**
     * MemcachedCache
     * 
     * Provides accessors for reading, writing  and flushing memcached-based
     * data-stores.
     * 
     * @author   Oliver Nassar <onassar@gmail.com>
     * @abstract
     * @todo     implement prefixing, caching server weighting
     * @notes    false values can be stored in cache, and can be returned
     *           properly based on memcached's flag which allows access to
     *           whether or not a value was returned properly
     * @example
     * <code>
     *     // library inclusions
     *     require_once APP . '/vendors/PHP-MemcachedCache/MemcachedCache.class.php';
     * 
     *     // server identification; connection
     *     $servers = array(
     *         array('127.0.0.1', 11211)
     *     );
     *     MemcachedCache::init('namespace', $servers, false);
     * 
     *     // cache writing; reading; output
     *     MemcachedCache::write('oliver', 'nassar');
     *     echo MemcachedCache::read('oliver');
     *     print_r(MemcachedCache::getStats());
     *     exit(0);
     * </code>
     */
    abstract class MemcachedCache
    {
        /**
         * _analytics
         * 
         * Array of query-type frequencies.
         * 
         * @var    array
         * @access protected
         * @static
         */
        protected static $_analytics = array(
            'deletes' => 0,
            'misses' => 0,
            'reads' => 0,
            'writes' => 0
        );

        /**
         * _benchmark
         * 
         * @var    boolean (default: false)
         * @access protected
         * @static
         */
        protected static $_benchmark = false;

        /**
         * _bypass
         * 
         * @var    boolean (default: false)
         * @access protected
         * @static
         */
        protected static $_bypass = false;

        /**
         * _duration
         * 
         * @var    integer (default: 0)
         * @access protected
         * @static
         */
        protected static $_duration = 0;

        /**
         * _instance
         * 
         * Store of the Memcached storage instance
         * 
         * @var    Memcached
         * @access protected
         * @static
         */
        protected static $_instance;

        /**
         * _namespace
         * 
         * @var    string
         * @access protected
         * @static
         */
        protected static $_namespace;

        /**
         * _clean
         * 
         * Sets a namespace (to allow for multiple environments on the same
         * caching server), replaces spaces (to insure proper storage; memcached
         * may choke otherwise), and hashes string.
         * 
         * @access protected
         * @static
         * @param  string $str
         * @return string
         */
        protected static function _clean($str)
        {
            $str = (self::$_namespace) . ($str);
            $str = str_replace(' ', '!!{_}!!', $str);
            return md5($str);
        }

        /**
         * checkForFlushing
         * 
         * @note   If you are using memcached for session storage, this will
         *         clear them!
         * @access public
         * @static
         * @param  string $key
         * @param  integer $delay (default: 0)
         * @return void
         */
        public static function checkForFlushing($key, $delay = 0)
        {
            if (isset($_GET[$key]) === true) {
                self::flush($delay);
            }
        }

        /**
         * delete
         * 
         * @throws Exception
         * @access public
         * @static
         * @param  string $key
         * @return void
         */
        public static function delete($key)
        {
            // ensure namespace set
            if (is_null(self::$_namespace) === true) {
                throw new Exception('Namespace not set');
            }

            // safely attempt to delete a Memcached node
            try {
                $key = self::_clean($key);
                if (self::$_instance->delete($key, 0) === false) {
                    $resultCode = self::$_instance->getResultCode();
                    throw new Exception(
                        'MemcacheCache Error: Exception while attempting to ' .
                        'delete node (result code: ' . ($resultCode) . ')'
                    );
                }
                ++self::$_analytics['deletes'];
            } catch(Exception $exception) {
                throw new Exception($exception->getMessage());
            }
        }

        /**
         * flush
         * 
         * Empties memcached-level data-store.
         * 
         * @throws Exception
         * @access public
         * @static
         * @param  integer $delay (default: 0)
         * @return void
         */
        public static function flush($delay = 0)
        {
            // safely try to flush resource
            try {
                if (self::$_instance->flush($delay) === false) {
                    $resultCode = self::$_instance->getResultCode();
                    throw new Exception(
                        'MemcachedCache Error: Exception while attempting to ' .
                        'flush resource (result code: ' . ($resultCode) . ')'
                    );
                }
            } catch(Exception $exception) {
                throw new Exception($exception->getMessage());
            }
        }

        /**
         * getDeletes
         * 
         * @access public
         * @static
         * @return integer
         */
        public static function getDeletes()
        {
            return self::$_analytics['deletes'];
        }

        /**
         * getDuration
         * 
         * @access public
         * @static
         * @return float
         */
        public static function getDuration()
        {
            return self::$_duration;
        }

        /**
         * getMisses
         * 
         * Returns the number of memcached-level missed cache reads.
         * 
         * @access public
         * @static
         * @return integer number of read/fetch misses for memcached requests
         */
        public static function getMisses()
        {
            return self::$_analytics['misses'];
        }

        /**
         * getReads
         * 
         * Returns the number of memcached-level successful cache reads.
         * 
         * @access public
         * @static
         * @return integer number of read/fetch requests for memcached
         */
        public static function getReads()
        {
            return self::$_analytics['reads'];
        }

        /**
         * getStats
         * 
         * Returns an associative array of memcached-level cache performance
         * statistics.
         * 
         * @access public
         * @static
         * @return array associative array of key memcached statistics
         */
        public static function getStats()
        {
            return self::$_analytics;
        }

        /**
         * getWrites
         * 
         * Returns the number of successful memcached-level cache writes.
         * 
         * @access public
         * @static
         * @return integer number of times a mixed value was written to memcached
         */
        public static function getWrites()
        {
            return self::$_analytics['writes'];
        }

        /**
         * init
         * 
         * Creates and initializes a memcached instance/resource with a variable
         * number of servers.
         * 
         * @throws Exception
         * @access public
         * @static
         * @param  string $namespace
         * @param  array $servers
         * @param  boolean $benchmark (default: false)
         * @return void
         */
        public static function init(
            $namespace,
            array $servers,
            $benchmark = false
        ) {
            // local static variables
            self::$_namespace = $namespace;
            self::$_benchmark = $benchmark;

            // safely attempt to handle the resource
            try {
                self::$_instance = new Memcached();
                self::$_instance->addServers($servers);
            } catch(Exception $exception) {
                throw new Exception(
                    'MemcachedCache Error: Exception while attempting to add ' .
                    'servers.'
                );
            }
        }

        /** 
         * read
         * 
         * Attempts to read a memcached-level data-store record, returning null
         * if it couldn't be accessed. Handles false/null return value logic.
         *
         * @throws Exception
         * @access public
         * @static
         * @param  string $key key for the cache position
         * @return mixed cache record value, or else null if it's not present
         */
        public static function read($key)
        {
            // ensure namespace set
            if (is_null(self::$_namespace) === true) {
                throw new Exception('Namespace not set');
            }

            // safely attempt to read from Memcached resource
            try {

                // Bypassing checking
                if (self::$_bypass === true) {
                    ++self::$_analytics['misses'];
                    return null;
                }

                // hash key, and check for existance
                $hashedKey = self::_clean($key);
                if (self::$_benchmark == true) {
                    $start = microtime(true);
                    $response = self::$_instance->get($hashedKey);
                    $end = microtime(true);
                    self::$_duration += round($end - $start, 4);
                } else {
                    $response = self::$_instance->get($hashedKey);
                }

                // false boolean found
                if ($response === false) {

                    /**
                     * False value found refers to unsuccessful check: nothing
                     * was stored under this key
                     */
                    if (
                        self::$_instance->getResultCode() !== Memcached::RES_SUCCESS
                    ) {
                        ++self::$_analytics['misses'];
                        return null;
                    }
                }

                // increment memcached hits
                ++self::$_analytics['reads'];

                // return response
                return $response;
            } catch(Exception $exception) {
                throw new Exception(
                    'MemcachedCache Error: Exception while attempting to ' .
                    'read from resource.'
                );
            }
        }

        /** 
         * readMulti
         *
         * @throws Exception
         * @access public
         * @static
         * @param  array $keys
         * @return array
         */
        public static function readMulti(array $keys)
        {
            // ensure namespace set
            if (is_null(self::$_namespace) === true) {
                throw new Exception('Namespace not set');
            }

            // safely attempt to read from Memcached resource
            try {

                // Bypassing checking
                if (self::$_bypass === true) {
                    ++self::$_analytics['misses'];
                    return array_fill_keys($keys, false);
                }

                // hash key, and check for existance
                $hashedKeys = array();
                foreach ($keys as $key) {
                    array_push($hashedKeys, self::_clean($key));
                }
                if (self::$_benchmark == true) {
                    $start = microtime(true);
                    $response = self::$_instance->getMulti($hashedKeys);
                    $end = microtime(true);
                    self::$_duration += round($end - $start, 4);
                } else {
                    $response = self::$_instance->getMulti($hashedKeys);
                }

                // false boolean found
                if ($response === false) {

                    /**
                     * False value found refers to unsuccessful check: nothing
                     * was stored under this key
                     */
                    if (
                        self::$_instance->getResultCode() !== Memcached::RES_SUCCESS
                    ) {
                        ++self::$_analytics['misses'];
                        return array_fill_keys($keys, false);
                    }
                }

                // increment memcached hits
                ++self::$_analytics['reads'];

                // return response
                $formattedResponse = array();
                foreach ($hashedKeys as $index => $value) {
                    $formattedResponse[$keys[$index]] = false;
                    if (isset($response[$value]) === true) {
                        $formattedResponse[$keys[$index]] = $response[$value];
                    }
                }
                return $formattedResponse;
            } catch(Exception $exception) {
                throw new Exception(
                    'MemcachedCache Error: Exception while attempting to ' .
                    'read multiple objects from resource.'
                );
            }
        }

        /**
         * setupBypassing
         * 
         * @access public
         * @static
         * @param  string $key The key, which if found in _GET, will turn
         *         caching off
         * @return void
         */
        public static function setupBypassing($key)
        {
            if (isset($_GET[$key]) === true) {
                self::$_bypass = true;
            }
        }

        /**
         * write
         * 
         * Writes a value to the memcached data-store, based on the passed in
         * key. Handles false/null value storage logic.
         * 
         * @throws Exception
         * @access public
         * @static
         * @param  string $key key for the cache value in the hash
         * @param  mixed $value value for the cache key, which cannot be an
         *         object or object reference
         * @param  integer $ttl. (default: 0) time to live (ttl) for the cache
         *         value, after which it won't be accessible in the store (in
         *         seconds)
         * @return void
         */
        public static function write($key, $value, $ttl = 0)
        {
            // ensure namespace set
            if (is_null(self::$_namespace) === true) {
                throw new Exception('Namespace not set');
            }

            // null value check
            if ($value === null) {
                throw new Exception(
                    'MemcachedCache Error: Cannot perform Memcached write; ' .
                    'attempted to store null value in key *' . ($key) . '*.'
                );
            }

            // safely attempt to write to Memcached resource
            try {

                // attempt to store
                $key = self::_clean($key);
                if (self::$_instance->set($key, $value, $ttl) === false) {
                    $resultCode = self::$_instance->getResultCode();
                    throw new Exception(
                        'MemcacheCache Error: Exception while attempting to ' .
                        'write to resource (result code: ' . ($resultCode) . ')'
                    );
                }
                ++self::$_analytics['writes'];
            } catch(Exception $exception) {
                throw new Exception($exception->getMessage());
            }
        }
    }
