# Debugbar integration

For those using Laravel Debugbar in development, this may be useful.

We already publish data to Cloud Trace by default, and this shows the same data in Debugbar.

## To enable

Add the following service provider to `config/app.php`:

```diff
diff --git a/config/app.php b/config/app.php
index 8c73e3b..7433671 100644
--- a/config/app.php
+++ b/config/app.php
@@ -19,6 +19,7 @@
         // Package Service Providers...
         AffordableMobiles\GServerlessSupportLaravel\GServerlessSupportServiceProvider::class,
         AffordableMobiles\GServerlessSupportLaravel\Auth\AuthServiceProvider::class,
+        AffordableMobiles\GServerlessSupportLaravel\Integration\Debugbar\DebugbarServiceProvider::class,
     ])->replace([
         \Illuminate\View\ViewServiceProvider::class => AffordableMobiles\GServerlessSupportLaravel\View\ViewServiceProvider::class,
     ])->toArray(),
```

Then update the storage for Debugbar in `config/debugbar.php` to use `/tmp`:

```diff
diff --git a/config/debugbar.php b/config/debugbar.php
index 44aff57..076b66d 100644
--- a/config/debugbar.php
+++ b/config/debugbar.php
@@ -40,7 +40,7 @@
         'enabled'    => true,
         'open'       => env('DEBUGBAR_OPEN_STORAGE'), // bool/callback.
         'driver'     => 'file', // redis, file, pdo, socket, custom
-        'path'       => storage_path('debugbar'), // For file driver
+        'path'       => g_serverless_storage_path('debugbar'), // For file driver
         'connection' => null,   // Leave null for default connection (Redis/PDO)
         'provider'   => '', // Instance of StorageInterface for custom driver
         'hostname'   => '127.0.0.1', // Hostname to use with the "socket" driver
```

And finally, turn the default `time` collector off in `config/debugbar.php`:

```diff
diff --git a/config/debugbar.php b/config/debugbar.php
index 44aff57..076b66d 100644
--- a/config/debugbar.php
+++ b/config/debugbar.php
@@ -161,7 +161,7 @@
     'collectors' => [
         'phpinfo'         => true,  // Php version
         'messages'        => true,  // Messages
-        'time'            => true,  // Time Datalogger
+        'time'            => false,  // Time Datalogger
         'memory'          => true,  // Memory usage
         'exceptions'      => true,  // Exception displayer
         'log'             => true,  // Logs from Monolog (merged in messages if enabled)
```