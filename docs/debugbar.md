# Debugbar integration

For those using Laravel Debugbar in development, this may be useful.

We already publish data to Cloud Trace by default, and this shows the same data in Debugbar.

## To enable

Add the following service provider to `config/app.php`:

```diff
diff --git a/config/app.php b/config/app.php
index ee04459..d945faa 100755
--- a/config/app.php
+++ b/config/app.php
@@ -170,6 +170,7 @@ return [
         /*
          * Package Service Providers...
          */
+        A1comms\GaeSupportLaravel\Integration\Debugbar\DebugbarServiceProvider::class,
 
         /*
          * Application Service Providers...
```

Then turn the default `time` collector off in `config/debugbar.php`:

```diff
diff --git a/config/debugbar.php b/config/debugbar.php
index 77272c8..9a53de0 100644
--- a/config/debugbar.php
+++ b/config/debugbar.php
@@ -151,7 +151,7 @@ return [
     'collectors' => [
         'phpinfo'         => true,  // Php version
         'messages'        => true,  // Messages
-        'time'            => true,  // Time Datalogger
+        'time'            => false,  // Time Datalogger
         'memory'          => true,  // Memory usage
         'exceptions'      => true,  // Exception displayer
         'log'             => true,  // Logs from Monolog (merged in messages if enabled)
```