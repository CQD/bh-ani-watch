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

        $this->saveAnime($data);
        $this->saveAnimeDailyScore($data);
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

    private function saveAnimeDailyScore($data)
    {
        $ymd = date('Ymd');
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
