<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// 1. Get your Free API Key from RapidAPI (Instagram Data Scraper)
$api_key = "YOUR_RAPID_API_KEY";
$username = "ttj_nextgenjewels";

$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL => "https://instagram-scraper-api2.p.rapidapi.com/v1/medias?username_or_id_or_url=$username",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "X-RapidAPI-Host: instagram-scraper-api2.p.rapidapi.com",
        "X-RapidAPI-Key: $api_key"
    ],
]);

$response = curl_exec($curl);
$data = json_decode($response, true);
curl_close($curl);

$output = [];

// 2. Filter for only the last 5 items
if (isset($data['data']['items'])) {
    foreach (array_slice($data['data']['items'], 0, 5) as $item) {
        $output[] = [
            "thumbnail" => $item['thumbnail_url'],
            "shortcode" => $item['code'], // Used to open the Reel/Post
            "is_video"  => $item['is_video']
        ];
    }
    echo json_encode(["status" => "success", "data" => $output]);
} else {
    echo json_encode(["status" => "error", "message" => "Could not fetch feed"]);
}
?>