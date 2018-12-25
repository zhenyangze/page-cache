<?php

namespace Silber\PageCache;

use Exception;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Contracts\Container\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Cache
{
    /**
     * The filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    public $files;

    /**
     * The container instance.
     *
     * @var \Illuminate\Contracts\Container\Container|null
     */
    protected $container = null;

    /**
     * The directory in which to store the cached pages.
     *
     * @var string|null
     */
    protected $cachePath = null;

    /**
     * Constructor.
     *
     * @var \Illuminate\Filesystem\Filesystem  $files
     */
    public function __construct(Filesystem $files)
    {
        $this->files = $files;
    }

    /**
     * Sets the container instance.
     *
     * @param  \Illuminate\Contracts\Container\Container  $container
     * @return $this
     */
    public function setContainer(Container $container)
    {
        $this->container = $container;

        return $this;
    }

    /**
     * Sets the directory in which to store the cached pages.
     *
     * @param  string  $path
     * @return void
     */
    public function setCachePath($path)
    {
        $this->cachePath = rtrim($path, '\/');
    }

    /**
     * Gets the path to the cache directory.
     *
     * @param  string  ...$paths
     * @return string
     *
     * @throws \Exception
     */
    public function getCachePath()
    {
        $base = $this->cachePath ? $this->cachePath : $this->getDefaultCachePath();

        if (is_null($base)) {
            throw new Exception('Cache path not set.');
        }

        return $this->join(array_merge([$base], func_get_args()));
    }

    /**
     * Join the given paths together by the system's separator.
     *
     * @param  string[] $paths
     * @return string
     */
    protected function join(array $paths)
    {
        $trimmed = array_map(function ($path) {
            return trim($path, '/');
        }, $paths);

        return $this->matchRelativity(
            $paths[0], implode('/', array_filter($trimmed))
        );
    }

    /**
     * Makes the target path absolute if the source path is also absolute.
     *
     * @param  string  $source
     * @param  string  $target
     * @return string
     */
    protected function matchRelativity($source, $target)
    {
        return $source[0] == '/' ? '/'.$target : $target;
    }

    /**
     * Caches the given response if we determine that it should be cache.
     *
     * @param  \Symfony\Component\HttpFoundation\Request  $request
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @return $this
     */
    public function cacheIfNeeded(Request $request, Response $response)
    {
        if ($this->shouldCache($request, $response) && $this->canCache($request)) {
            $this->cache($request, $response);
        }

        return $this;
    }

    /**
     * Determines whether the given request/response pair should be cached.
     *
     * @param  \Symfony\Component\HttpFoundation\Request  $request
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @return bool
     */
    public function shouldCache(Request $request, Response $response)
    {
        return $request->isMethod('GET') && $response->getStatusCode() == 200;
    }

    /**
     * Determines whether the given request/response pair can be cached.
     *
     * @param  \Symfony\Component\HttpFoundation\Request  $request
     * @return bool
     */
    public function canCache(Request $request)
    {
        //dd($request->route()->getName());
        $name = $request->route()->getName();
        return $this->isInWhiteList($name) && $this->notInBlackList($name);
    }

    /**
     * isInWhiteList
     *
     * @param $name
     *
     * @return  bool
     */
    protected function isInWhiteList($name)
    {
        return $this->checkInList($name, config('pagecache.whiteList'));
    }
    
    /**
     * notInBlackList
     *
     * @param $name
     *
     * @return  bool
     */
    protected function notInBlackList($name)
    {
        return !$this->checkInList($name, config('pagecache.blackList'));
    }
    /**
     * checkInList
     *
     * @param $name
     * @param $configList
     *
     * @return bool
     */
    protected function checkInList($name, $configList = [])
    {
        if (empty($configList)) {
            return false;
        }
        foreach ($configList as $rule) {
            if (fnmatch($rule, $name)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Cache the response to a file.
     *
     * @param  \Symfony\Component\HttpFoundation\Request  $request
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @return void
     */
    public function cache(Request $request, Response $response)
    {
        list($path, $file) = $this->getDirectoryAndFileNames($request);

        $this->files->makeDirectory($path, 0775, true, true);
        $body = $response->getContent();
        if (config('pagecache.pre_function') && is_callable(config('pagecache.pre_function'))) {
            $preFunction = config('pagecache.pre_function');
            $body = $preFunction($body);
        }
        $this->files->put(
            $this->join([$path, $file]),
            $body,
            true
        );
    }

    /**
     * Remove the cached file for the given slug.
     *
     * @param  string  $slug
     * @return bool
     */
    public function forget($slug)
    {
        return $this->files->delete($this->getCachePath($slug.'.html'));
    }

    /**
     * Fully clear the cache directory.
     *
     * @return bool
     */
    public function clear()
    {
        return $this->files->deleteDirectory($this->getCachePath(), true);
    }

    /**
     * Get the names of the directory and file.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    protected function getDirectoryAndFileNames($request)
    {
        $segments = explode('/', ltrim($request->getPathInfo().$request->getQueryString(), '/'));

        $file = $this->aliasFilename(array_pop($segments)).'.html';

        return [$this->getCachePath(implode('/', $segments)), $file];
    }

    /**
     * Alias the filename if necessary.
     *
     * @param  string  $filename
     * @return string
     */
    protected function aliasFilename($filename)
    {
        return $filename ?: 'pc__index__pc';
    }

    /**
     * Get the default path to the cache directory.
     *
     * @return string|null
     */
    public function getDefaultCachePath()
    {
        if ($this->container && $this->container->bound('path.public')) {
            return $this->container->make('path.public').'/page-cache';
        }
    }
}
