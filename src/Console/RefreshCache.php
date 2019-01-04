<?php

namespace Silber\PageCache\Console;

use Illuminate\Support\Facades\Storage;
use Silber\PageCache\Cache;
use Illuminate\Console\Command;

/**
 *
 */
class RefreshCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'page-cache:refresh {prefixKey?}';

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

    /**
     * checkTimeout
     *
     * @param $cache
     *
     * @return
     */
    protected function checkTimeout($cache)
    {
        if (!$cache->files->exists(config_path('pagecache.php'))) {
            @file_put_contents(config_path('pagecache.php'), "<?php\n\n return " . var_export([
                'timeout' => [
                    '' => 60 * 60 * 24 * 30,
                ]
            ], true) . ';');
        }
        $timeOutConfig = config('pagecache.timeout');
        $path = $cache->getDefaultCachePath();
        foreach ($this->getFiles($path) as $file) {
            $fullPath = $file->getPathname();
            $relatePath = str_ireplace($path . '/', '', $fullPath);
            $createTime = $file->getMTime();

            foreach ((array)$timeOutConfig as $category => $time) {
                if (empty($category) && empty($time)) {
                    continue;
                }
                if (stripos('X' . $relatePath, 'X' . $category) === 0 && time() > $createTime + $time) {
                    $this->deleteFile($cache, $fullPath);
                    break;
                }
            }
        }
        $this->info('Finish! page-cache check timeout');
    }

    /**
     * deleteFile
     *
     * @param $cache
     * @param $file
     *
     * @return
     */
    protected function deleteFile($cache, $file)
    {
        $cache->files->delete($file);
    }

    /**
     * clearCategory
     *
     * @param $cache
     * @param $prefixKey
     *
     * @return
     */
    protected function clearCategory($cache, $prefixKey)
    {
        $path = $cache->getDefaultCachePath();
        foreach ($this->getFiles($path) as $file) {
            $fullPath = $file->getPathname();
            $relatePath = str_ireplace($path . '/', '', $fullPath);

            if (stristr($relatePath, $prefixKey)) {
                $this->deleteFile($cache, $fullPath);
            }
        }
        $this->info('Finish! page-cache clear category');
    }

    /**
     * getFiles
     *
     * @param $path
     *
     * @return
     */
    protected function getFiles($path)
    {
        $dir = new \RecursiveDirectoryIterator($path);
        foreach (new \RecursiveIteratorIterator($dir) as $v) {
            $fileName = $v->getBaseName();
            if ($fileName != '.' && $fileName != '..') {
                yield $v;
            }
        }
    }
}
