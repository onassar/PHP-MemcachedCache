PHP-MemachedCache
===


### Connection, Caching &amp; Reading Example

    // library inclusions
    require_once APP . '/vendors/PHP-MemcachedCache/MemcachedCache.class.php';
    
    // server identification; connection
    $servers = array(
        array('127.0.0.1', 11211)
    );
    MemcachedCache::init($servers);
    
    // cache writing; reading; output
    MemcachedCache::write('oliver', 'nassar');
    echo MemcachedCache::read('oliver');
    print_r(MemcachedCache::getStats());
    exit(0);
