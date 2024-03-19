# Datastore Session Driver

A session driver using Cloud Firestore in Datastore mode is provided for performance and scalability.

For an incredibly low price, with zero input, it can automatically scale to millions of visitors & active sessions.

## Configuration

In `.env`, or as an environment variable:

```
SESSION_DRIVER=datastore
```

To further customize the kind (table), database ID (now multiple databases are supported per project), or the namespace ID, in `config/session.php`, add the lines:

```php
    'namespace' => env('SESSION_NAMESPACE', null),
    'database'  => env('SESSION_DATABASE', ''),
```

Then, define your environment variables as required, i.e.

```
SESSION_TABLE=laravel-sessions
SESSION_NAMESPACE=my-app
SESSION_DATABASE=my-app-eu
```

## Garbage Collection

It is important to clear out old sessions after they expire, as otherwise, you'll start to be billed for more and more storage, when it isn't being used for anything useful.

Our previous iteration of garbage collection using Cloud Functions is now considered deprecated.

The best method going forward involves using Datastore's built-in TTL policies:

https://cloud.google.com/datastore/docs/ttl

The session handler by default adds the required `expireAt` timestamp field, so follow the guide above to use it to configure garbage collection against your database & kind - it should work across namespaces transparently.

Session lifetime is controlled by the `lifetime` value in `config/session.php`, as an integer of minutes of idle until expiry.

By default, you can configure this as an environment variable, with the default value of 120 minutes:

```
SESSION_LIFETIME=120
```

Note that the `expireAt` field is only written/updated when a session entry is updated in Datastore, so changing the session lifetime will only affect new sessions, or those that are updated after the change is made.