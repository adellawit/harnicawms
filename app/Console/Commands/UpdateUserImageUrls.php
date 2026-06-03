<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

class UpdateUserImageUrls extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:update-image-urls';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update user image URLs from localhost to production domain';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Updating user image URLs...');

        // Ensure we're using HTTPS URL
        $baseUrl = config('app.url');
        if (!str_starts_with($baseUrl, 'https://')) {
            $baseUrl = str_replace('http://', 'https://', $baseUrl);
        }

        $users = User::whereNotNull('url_image')
            ->where(function ($query) {
                $query->where('url_image', 'like', '%localhost%')
                    ->orWhere('url_image', 'like', '%127.0.0.1%')
                    ->orWhere('url_image', 'like', 'http://%');
            })
            ->get();

        $updated = 0;

        foreach ($users as $user) {
            $oldUrl = $user->url_image;
            
            // Extract path from URL
            $path = parse_url($oldUrl, PHP_URL_PATH);
            
            if (empty($path)) {
                // Try to extract path manually
                $parts = explode('/', $oldUrl);
                $pathIndex = array_search('assets', $parts);
                if ($pathIndex !== false) {
                    $path = '/' . implode('/', array_slice($parts, $pathIndex));
                } else {
                    // Use default
                    $newUrl = $baseUrl . '/assets/img/wms/avatar/user-default.jpg';
                }
            }
            
            if (!isset($newUrl)) {
                $newUrl = $baseUrl . $path;
            }

            // Update only if URL changed
            if ($oldUrl !== $newUrl) {
                $user->url_image = $newUrl;
                $user->save();
                $updated++;
                
                $this->line("Updated user {$user->username}: {$oldUrl} -> {$newUrl}");
            }
        }

        $this->info("Updated {$updated} user image URLs.");
        
        return Command::SUCCESS;
    }
}
