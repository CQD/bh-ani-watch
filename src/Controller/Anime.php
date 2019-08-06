<?php

namespace Q\BHAniWatch\Controller;

use Google\Cloud\Datastore\DatastoreClient;
use Google\Cloud\Datastore\Query\Query;
use Q\BHAniWatch\Helper\Api;

class Anime
{
    public function info($app, $params, $prevOutput)
    {
        $store = $this->getStore();
        $key = $store->key('anime', $params['id']);
        $anime = $store->lookup($key);

        $data = ($anime)
            ? $anime->get()
            : null;

        header('Content-Type: application/json');
        $app->view = json_encode($data);
    }

    public function list($app, $params, $prevOutput)
    {
        $store = $this->getStore();
        $all = [];

        $cursor = '';
        while (true) {
            $query = $store->query()
                ->kind('anime')
                ->start($cursor);

            $res = $store->runQuery($query);

            $cursor = false;
            foreach ($res as $anime) {
                $id = (int) $anime->key()->pathEndIdentifier();
                $title = $anime->title;
                $order = $anime->order;
                $all[$order] = [
                    'id' => $id,
                    'title' => $title,
                ];
                $cursor = $anime->cursor();
            }

            if (!$cursor) break;
        }

        krsort($all, SORT_NUMERIC);

        header('Content-Type: application/json');
        $app->view = json_encode(array_values($all));
    }

    public function dailyScore($app, $params, $prevOutput)
    {
        $startDatetime = strtotime($params['startDate']) ?: false;
        $endDdatetime = strtotime($params['endDate']) ?: false;
        if (!$startDatetime || !$endDdatetime) {
            $app->view = '{}';
            return;
        }
        $startDatetime -= 86400; // 往前多取一天，好處理 Diff

        $store = $this->getStore();

        $keys = [];
        for ($now = $startDatetime; $now <= $endDdatetime;  $now += 86400) {
            $keys[] = $store->key('anime_daily_score', date('Ymd', $now));
        }
        $keys = array_slice($keys, 0, 100); // 避免一次拉太多資料的限制器

        $result = $store->lookupBatch($keys);
        $data = [];
        foreach ($result['found'] ?? [] as $entity) {
            $now = strtotime($entity['date']);
            $data[date('Y-m-d', $now)] = [
                'popular' => $entity['popular'] ? json_decode($entity['popular'], true) : null,
            ];
        }

        ksort($data);

        $yesterdayScores = ['popular' => []];
        foreach ($data as $date => $scores) {
            foreach ($scores['popular'] as $id => $popular) {
                $popularDiff = ('2019-07-14' === $date)
                    ? 0
                    : $popular - ($yesterdayScores['popular'][$id] ?? 0);
                $data[$date]['popular-diff'][$id] = $popularDiff;
            }
            $yesterdayScores = $scores;
        }

        unset($data[date('Y-m-d', $startDatetime)]); // 把多取的那一天的資料砍掉

        ksort($data);
        $app->view = json_encode($data, JSON_FORCE_OBJECT);
    }

    public function fetchList($app, $params, $prevOutput)
    {
        $api = new Api;

        $data = [];
        $page = 1;
        while ($result = $api->call('/v1/list.php', ['page' => $page])) {
            foreach ($result as $row) {
                $data[] = $row;
            }
            $page++;
            usleep(200 * 1000);
        }

        $ymd = date('Ymd');
        $this->saveAnime($data);
        $this->saveAnimeDailyScore($data, $ymd);
        $app->view = 'done';
    }

    private function saveAnime($data)
    {
        $store = $this->getStore();

        $step = 30;
        $order = count($data);

        for ($offset = 0; $offset < count($data); $offset += $step) {
            $chunk = array_slice($data, $offset, $step);
            $rows = [];

            foreach ($chunk as $anime) {
                $key = $store->key('anime', $anime['anime_sn']);
                $row = [
                    'anime_sn' => (int) $anime['anime_sn'] ?? 0,
                    'title' => (string) $anime['title'] ?? '不明',
                    'info' => (string) $anime['info'] ?? '不明',
                    'popular' => (int) $anime['popular'] ?? 0,
                    'score' => (double) $anime['score'] ?? 0,
                    'order' => (int) ($order--),
                ];
                $row = $store->entity($key, $row, [
                    'excludeFromIndexes' => ['info', 'title'],
                ]);
                $rows[] = $row;
            }

            $store->upsertBatch($rows);
        }
    }

    private function saveAnimeDailyScore($data, $ymd)
    {
        $store = $this->getStore();

        $score = [];
        $popular = [];
        foreach ($data as $anime) {
            $animeSn = (int) $anime['anime_sn'] ?? 0;
            $popular[$animeSn] = (int) $anime['popular'];
            $score[$animeSn] = (int) $anime['score'];
        }

        $key = $store->key('anime_daily_score', $ymd);

        $entity = [
            'date' => (int) $ymd,
            'popular' => json_encode($popular),
            'score' => json_encode($score),
        ];
        $entity = $store->entity($key, $entity, [
            'excludeFromIndexes' => ['popular', 'score'],
        ]);
        $store->upsert($entity);
    }

    private function getStore()
    {
        static $store = null;
        $store = $store ?: $datastore = new DatastoreClient([
            'projectId' => GAE_APPLICATION,
        ]);

        return $store;
    }
}
