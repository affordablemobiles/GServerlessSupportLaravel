## Queue Driver for Cloud Tasks

This allows you to use Laravel's Job / Queue system with Cloud Tasks, by automatically serializing jobs and exposing them to be executed via a HTTP endpoint which can be consumed by Cloud Tasks.

### Installation

To enable, you'll first need to include our `QueueServiceProvider` in `config/app.php`, replacing the default Laravel one:

```php
    'providers' => [
        //Illuminate\Queue\QueueServiceProvider::class,
        AffordableMobiles\GServerlessSupportLaravel\Queue\QueueServiceProvider::class,
    ];
```

You'll then need to add a driver entry into `config/queue.php`:

```php
    'connections' => [
        'gae' => array(
            'driver'    => 'gae',
            'queue'     => 'default',
            'url'       => '/tasks',
            'encrypt'   => true,
            'compress'  => true,
        ),
    ],
```

And set the queue driver in your `.env` file to `QUEUE_DRIVER=gae`

Then, to actually use it, you'll need to make sure at-last the default queue exists in Cloud Tasks, which can be accomplished by deploying this example `queue.yaml` file:

```yaml
queue:
- name: default
  rate: 5/s
  max_concurrent_requests: 1
```

You can adjust this for how you'd like it to run (i.e. how many concurrent requests you'd like, if you want a limit as we have in the example), see the [documentation](https://cloud.google.com/appengine/docs/standard/php/config/queueref).

### Usage

An example of usage is below, in the context of sending a ticket notification from a model:

```php
<?php

namespace App\Models;

use App\Jobs;
use Illuminate\Foundation\Bus\DispatchesJobs;

class Ticket extends Elegant
{
    use DispatchesJobs;

    ...

    public function notify($email)
    {
        ...

        $this->dispatch((new Jobs\SendEmail('emails::tickets.new', ['ticket_id' => $this->ticket_id], 'New Ticket', $email))->onQueue('tickets-notify'));

        ...
    }

    ...
}
```

Where the `->onQueue('%name%')` part at the end denotes it should go into a different queue as named, rather than the default. If you'd like to just use the default, this can be omitted.

Our example job would look like this:

```php
<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\DispatchesJobs;
...

class SendEmail implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels, DispatchesJobs;

    ...

    protected $data;

    ...

    /**
     * Create a new job instance.
     *
     * @param  string $view
     * @param  array  $view_data
     * @param  string $email
     * @return void
     */
    public function __construct($view, $view_data, $subject, $email)
    {
        $this->data = [
            'view'              => $view,
            'view_data'         => $view_data,
            'subject'           => $subject,
            'email'             => $email,
        ];
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // send email here...
    }

    ...
}
```
