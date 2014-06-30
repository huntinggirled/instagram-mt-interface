<?php

$ini = parse_ini_file('config.ini');
$client_id = $ini{'client_id'};
$redirect_uri = $ini{'redirect_uri'};
$authorize_uri = 'https://api.instagram.com/oauth/authorize/';
//-----[postするデータ]
$post = "client_id=".$client_id."&redirect_uri=".$redirect_uri."&response_type=code";
echo $post.'\n';

header("Location: {$authorize_uri}?{$post}");

?>