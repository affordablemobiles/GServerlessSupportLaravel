## StackDriver Trace Support

This package includes built in support for tracing important components to StackDriver Trace.

By default, this includes:
* Laravel startup
* Laravel internals (including more granular startup).
  * Application construct
  * Request capture
  * Request handle
  * Response send
  * Request terminate (cleanup)
* Application specific
  * Middleware
  * Time in Router
  * Controller / Route-Closure Runtime
  * Blade view compile/render
* External calls / RPC
  * memcached
  * redis
  * MySQL
  * PDO
  * Eloquent (Laravel)
  * Datastore
  * Guzzle (HTTP(s))