<?php

namespace Silber\PageCache\Console;

use Illuminate\Support\Facades\Storage;
use Silber\PageCache\Cache;
use Illuminate\Console\Command;

class RefreshCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'page-cache:Refresh {prefixKey?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh the page cache by timeout or category.';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $cache = $this->laravel->make(Cache::class);

        $prefixKey = $this->argument('prefixKey');
        if (empty($prefixKey)) {
            // checkout timeout
            $this->checkTimeout($cache);
        } else {
            // clear category
            $this->clearCategory($cache, $prefixKey);
        }
    }

    protected function checkTimeout($cache)
    {
        $timeOutConfig = config('pagecache.timeout');
        $path = $cache->getDefaultCachePath();
        $files = $cache->files->allFiles($path);
        foreach($files as $file) {
            $relatePath = $file->getRelativePathname();
            $fullPath = $file->getPathName();
            $createTime = $file->getCTime();

            foreach((array)$timeOutConfig as $category => $time) {
                if (empty($category) && empty($time)) {
                    continue;
                }
                if (stripos('X' . $relatePath, 'X' . $category) == 0 && time() > $createTime + $time) {
                    $this->deleteFile($cache, $fullPath);
                    break;
                }
            }
        }
        $this->info('Finish! page-cache check timeout');
    }

    protected function deleteFile($cache, $file)
    {
        $cache->files->delete($file);
    }

    protected function clearCategory($cache, $prefixKey)
    {
        $path = $cache->getDefaultCachePath();
        $files = $cache->files->allFiles($path);
        foreach($files as $file) {
            $relatePath = $file->getRelativePathname();
            $fullPath = $file->getPathName();

            if (stristr($relatePath, $prefixKey)) {
                $this->deleteFile($cache, $fullPath);
            }
        }
        $this->info('Finish! page-cache clear category');
    }

}
