<?php
require __DIR__ . '/../vendor/autoload.php';

use MinorWork\App;

mb_internal_encoding('UTF-8');
date_default_timezone_set('Asia/Taipei');

setupConstants();

putenv('GOOGLE_APPLICATION_CREDENTIALS=' . __DIR__ . '/../credential/bh-app.json');

$app = new App;
$app->handlerClassPrefix = '\Q\BHAniWatch\Controller\\';

$app->handlerAlias([
    'cache' => function($app){
        $etag = md5((string) $app->view);
        header("ETag: {$etag}");
        header("Cache-Control: public, max-age=86400");
    },
]);

$app->setRouting([
    'animeInfo'     => ['/api/anime/{id:[\d]+}',      ['Anime:info', 'cache']],
    'animeList'     => ['/api/anime/list',            ['Anime:list', 'cache']],
    'dailyScore'    => ['/api/score/{startDate}~{endDate}',  ['Anime:dailyScore', 'cache']],
    'jobAnimeList'  => ['/_job/fetchAnimeList',       'Anime:fetchList'],
]);
$app->run();


exit;
/////////////////////////////////////////////

function setupConstants()
{
    if (!defined('GAE_APPLICATION')) {
        define('GAE_APPLICATION', call_user_func(function(){
            // App Engine 輸入的 Application ID 前面會帶 prefix
            // "s~"   代表正式環境
            // "dev~" 代表開發環境
            // 使用時要把 prefix 拔掉才是真正的 application id
            // https://stackoverflow.com/a/5901750
            $appId = $_SERVER['APPLICATION_ID'] ?? 'bh-ani-watch';
            return preg_replace('/^[^~]*~/', '', $appId);
        }));
    }

    $ver = defined('GAE_VERSION') ? GAE_VERSION : '201907141706';
    define('ASSET_VERSION', abs(crc32($ver . 'salt')));
}
