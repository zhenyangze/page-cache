<?php

return [
    'cache' => env('PAGE_CACHE', true),
    'timeout' => [
        //'index' => 60 * 60 * 24 * 30,
        '' => 60 * 60 * 24 * 30, // 最晚一个月更新一次
    ],
    'whiteList' => [
        'web.*',
    ],
    'blackList' => [
    ],
    'domainList' => [
        '*',
    ],
    'meta' => 'page-cache',
    'pre_function' => function ($html, $path) {
        $cacheTag = config('pagecache.meta', 'page-cache');
        $html = str_ireplace([
            '</head>',
        ], [
            '<meta name="' . $cacheTag . '" content="true" /></head>',
        ], $html);
        return $html;
    },
];
