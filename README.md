PHP-MemachedCache
===
The PHP-MemcachedCache library includes just one statically accessed class,
which provides three basic methods (`flush`, `read`, `write`) for manipulating a
distributed memcached setup.

Additionally, it offers 4 analytic methods, and one for initiation (whereby
caching servers are defined, along with their ports).  
This class is meant to provide a clean, organized wrapper for accessing a group
of caching servers.

Enhancements that will be done include defining a prefix/key for data stored,
as well as the weighting of individual servers (useful during the maintenance,
removal and addition process).

### Connection, Caching &amp; Reading Example
The following basic example adds a single server (hosted locally) to the class,
writes to the data-store, reads from it, and prints out the statistics (which
includes data-store-request misses, reads and writes):

``` php
// library inclusions
require_once APP . '/vendors/PHP-MemcachedCache/MemcachedCache.class.php';

// server identification; connection
$servers = array(
    'namespace',
    array('127.0.0.1', 11211)
);
MemcachedCache::init($servers);

// cache writing; reading; output
MemcachedCache::write('oliver', 'nassar');
echo MemcachedCache::read('oliver');
print_r(MemcachedCache::getStats());
exit(0);
```
