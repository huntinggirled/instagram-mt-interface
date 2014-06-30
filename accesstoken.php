<?php

$ini = parse_ini_file('config.ini');
$client_id = $ini['client_id'];
$client_secret = $ini['client_secret'];
$redirect_uri = $ini['redirect_uri'];
$token_uri = 'https://api.instagram.com/oauth/access_token';
$code = $ini['code'];
//-----[postするデータ]
$post = "client_id=".$client_id."&client_secret=".$client_secret."&grant_type=authorization_code&redirect_uri=".$redirect_uri."&code=".$code;
echo $post.'\n';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $token_uri);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

$res = curl_exec($ch);
echo $res;
$json = json_decode($res);
curl_close($ch);

echo "access_token=".$json->access_token;
echo "username=".$json->user->username;
echo "profile_picture=".$json->user->profile_picture;
echo "id=".$json->user->id;
echo "full_name=".$json->user->full_name;

?>