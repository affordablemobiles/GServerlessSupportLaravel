## Blade View Pre-Compiler

Templating in Laravel is done using [Blade Templates](https://laravel.com/docs/5.5/blade).

As it states in their documentation:

> ... all Blade views are compiled into plain PHP code and cached until they are modified ...

This is a problem on App Engine, as generating files at runtime isn't well suited for an environment with a read-only file system, that also runs on many instances which can't share the results easily.

### Previous Approach

Previous versions of our library used a method borrowed from @shpasser, which involved generating the compiled version of the views at runtime and storing the results in memcache.

This got around the read-only file system problem, plus allowed all instances to share the results.

It did however, result in a few downsides:

* Reliance on memcache, which in the PHP 7.2 runtime, is taken away in favour of Cloud Memorystore
* Short cache lifetime & extra effort needed to keep regenerating.
* Cache unreliability, resulting in us storing the compiled view into it, then it not being there to read back
  * This was eventually fixed by using an in-memory array of compiled views per request, at some performance cost.

### Our Investigation

When we examined the requirements in more detail for the move to PHP 7.2, it came to our attention that neither solution was optimal.

It was our view that the main design consideration for compiling the views at runtime and using a cache was to support the usual LAMP style deployment model, where the app would be served from a single server, with the underlying files sat on a read/write disk, which could be modified at any time by the owner (i.e. deploying files by (S)FTP, editing on the command line, or doing a "git pull" in that directory).

The need to expect these kind of changes actually incurs an overhead, as even though the compiled views are cached, we have to check the source files on every request to see if they have changed, to trigger a re-compile if they have, so there is no manual intervention required by the site owner.

It also became clear, that hadn't occured to us before: the "compilation" was a simple translation that didn't take any input from the actual request, so would be the same every time given the same source files.

Plus, the other bit we thought that might be part of the compliation process, including sub-views, was actually handled outside of the compilation process. It keeps everything seporate and calls back to the view handlers from the translated PHP, massively reducing the complexity of our problem.

### Our Solution

For an environment like App Engine, one we deploy the code and it becomes a read-only image that is shipped to the containers for runtime, we don't have to worry about a lot of these considerations required for the traditional LAMP environment.

Infact, it is better for us if to have re-producible builds that do as much work as possible before shipping to production, to not only save us resources, but also to aid with debugging and auditing, as we've got a clear view of exactly what code is running (we can drill down to post-compilation view files in Stackdriver Debugger using location information from a stacktrace).

So with this in mind, we chose to pre-compile all of our views at deploy time (using the same Blade compiler as before) and store the resulting PHP files & a map to their location in a folder to be shipped along with the rest of the app.

### Turning it on

To enable, you'll first need to include our `ViewServiceProvider` in `config/app.php`, replacing the default Laravel one:

```php
    'providers' => [
        //Illuminate\View\ViewServiceProvider::class,
        A1comms\GaeSupportLaravel\View\ViewServiceProvider::class,
    ];
```

This will enable the functionality only when running on App Engine, plus when `APP_ENV=production` is set in `.env`, making sure you always use the default templating system during development, so you don't need to worry about seeing out of date views.

Compiling the views is handled by the command `artisan gae:viewcompile`, however, if you've already added `artisan gae:prepare` to your composer scripts, it will auto-detect when the `ViewServiceProvider` is in your config and auto compile all of the views every time composer runs.

### How it works

At compile time (see above), we loop through all of the view directories looking for templates, then compile them into the same location that the cache files would usually sit when generated at runtime (`config('view.compiled')`), with the addition of a `manifest.php` file which contains a static map between the source view file name and the compiled file name.

When it's active on App Engine, the `ViewServiceProvider` loads a fake compiler, which instead of reading any of the source view files, simply loads the manifest and any of the already compiled view files as required, by looking up the names against the manifest.

### Dynamic Views at Runtime

With this method active, if you have any kind of dynamic view generation at runtime, it will no longer function with this method active, as we have replaced the default method to access the Blade compiler.

**It is worth noting that dynamic views should be heavily discouraged, as especially when taking input from a request to generate them, it is very easy for them to lead to code injection and full RCE (Remote Code Execution, a compromise of your site, quite a big security risk).**

However, there is still a method with which this can be accomplished:

```php
<?php

namespace App;

use Exception;
use Illuminate\View\Engines\PhpEngine;
use Illuminate\View\Compilers\BladeCompiler;

class Template
{
    /**
     * Store Raw Template
     *
     * @var string
     */
    private $content;

    /**
     * Create Template Object
     *
     * @param   string  $content
     * @return  void
     */
    public function __construct($content)
    {
        $this->content = $content;
    }

	/**
	 * Render content
	 *
	 * @return string
	 */
	public function render($data = [])
	{
		$compiler = new BladeCompiler(app('files'), sys_get_temp_dir());
		$compiled = $compiler->compileString(htmlspecialchars_decode($this->content));

		$temp = @tempnam('render', 'blade');

		if (empty($temp)) {
			throw new Exception("Failed to Create Temporary File");
		}

		// temp file created
		if (file_put_contents($temp, $compiled))
		{
			try
			{
				$engine = new PhpEngine();
				$view = $engine->get($temp, $data);
			}
			catch (Exception $e)
			{
				// show error
				$view = $e->getMessage() . ' on line ' . $e->getLine();
			}
		}

		// remove temp file
		unlink($temp);

		// return
		return $view;
	}
}
```