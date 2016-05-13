<?php
namespace App\Providers\Tautr;

class TautrServiceProvider {

    private $apiUrl = "https://www.tautr.com/api";
    private $apiKey = "";

    public function __construct($apiKey) {
        $this->apiKey = $apiKey;
    }

    public function entry($data = []) {
        return $this->apiUrl.'?'.http_build_query(array_merge([
            'token' => $this->apiKey,
            'page'  => 1
        ], $data));
    }

    public function randomImage($page = 1)  {
        $content = file_get_contents($this->entry(['page' => $page]));
        $images  = json_decode($content);
        $randKey = array_rand($images->items, 1);

        return $this->replaceToTwimgSuffix($images->items[$randKey]->media_url);
    }

    public function randomImages($page = 1)  {
        $content  = file_get_contents($this->entry(['page' => $page]));
        $images   = json_decode($content);
        $randKeys = array_rand($images->items, 3);

        $imageUrls = array_map(function($k) use ($images) {
            return $this->replaceToTwimgSuffix($images->items[$k]->media_url);
        }, $randKeys);

        return $imageUrls;
    }

    private function replaceToTwimgSuffix($imageUrl) {
        $filename = basename($imageUrl);

        return "http://pbs.twimg.com/media/".$filename;
    }

}
