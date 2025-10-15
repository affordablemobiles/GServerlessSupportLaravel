<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GServerlessPublishAssetsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'g-serverless:publish-assets';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleans up old assets and re-publishes the latest versioned assets for package JavaScript files.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Cleaning up old GServerlessSupport assets...');

        // 2. Define the public path for your package's assets.
        $publicPath = public_path('vendor/g-serverless-support');

        // 3. Delete the entire directory to remove all old assets.
        if (File::isDirectory($publicPath)) {
            File::deleteDirectory($publicPath);
            $this->info("Successfully deleted: {$publicPath}");
        }

        $this->info('Publishing latest GServerlessSupport assets...');

        // 4. Call the vendor:publish command to copy the new assets over.
        $this->call('vendor:publish', [
            '--tag'   => 'gss-js-assets',
            '--force' => true,
        ]);

        $this->info('GServerlessSupport assets have been updated successfully.');

        return 0;
    }
}
