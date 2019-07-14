<?php

namespace Q\BHAniWatch\Helper;

class Api
{
    const BASE = 'https://api.gamer.com.tw/mobile_app/anime/';

    public function call($path, $params = [])
    {
        $queryString = ($params)
            ? http_build_query($params)
            : '';

        $uri = sprintf('%s/%s%s',
            rtrim(Api::BASE, '/'),
            ltrim($path, '/'),
            $queryString ? "?{$queryString}" : ''
        );

        return $this->get($uri);
    }

    private function get($uri)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $uri);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $body = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($body, true);

        if (!is_array($result)) {
            var_dump($result);
            $result = false;
            trigger_error(sprintf('拉 API 失敗，回傳資料為 %s', var_export($body, true)));
        }

        return $result;
    }
}
