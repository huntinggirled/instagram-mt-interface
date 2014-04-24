<?php
$ini = parse_ini_file('config.ini');

/*
$baseurl = $ini['dataapicgi'].'/v'.$ini['dataapiversion'].'/sites/'.$ini['blogid'].'/entries';
$json = file_get_contents($baseurl);
$output = json_decode($json);

echo '<ul>',"n";
foreach ( $output->items as $data ){
    echo "t", '<li><a href="' , $data->permalink , '"><span class="date">', date('Y年m月d日', strtotime($data->date)) ,'</span><span class="tit">' , $data->title , '</span></a></li>',"n";
	}
echo '</ul>',"n";
*/
$baseurl = $ini['dataapicgi'].'/v'.$ini['dataapiversion'].'/authentication';
$data = array(
	'username' => $ini['mtusername'],
	'password' => $ini['mtpassword'],
	'clientId' => 'datatest',
);
$data = http_build_query($data);
$header = array(
	'Content-Type: application/x-www-form-urlencoded',
	'Content-Length: '.strlen($data),
	'User-Agent: Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/27.0.1453.116 Safari/537.36',
);
$options = array(
	'http' => array(
		'method' => 'POST',
		'content' => $data,
	)
);
$json = file_get_contents($baseurl, false, stream_context_create($options));
//echo $json;
$output = json_decode($json);
$accessToken = $output->accessToken;
//echo $accessToken;

if($accessToken) {
	$baseurl = $ini['dataapicgi'].'/v'.$ini['dataapiversion'].'/sites/'.$ini['blogid'].'/assets/upload';
	$fileContents = file_get_contents('hg_icon.jpg', FILE_BINARY);
	$boundary = "---------------------".substr(md5(rand(0,32000)), 0, 10);
	$data = "";
	$data .= "--$boundary\r\n";
	$data .= "Content-Disposition: form-data; name=\"path\"\r\n\r\nmt_tmp\r\n";
	$data .= "--$boundary\r\n";
	$data .= "Content-Disposition: form-data; name=\"file\"; filename=\"hg_icon.jpg\"\r\n";
	$data .= "Content-Type: image/jpeg\r\n";
	$data .= "Content-Transfer-Encoding: binary\r\n\r\n";
	$data .= $fileContents . "\r\n";
	$data .= "--$boundary--\r\n";
	$dataLen = strlen($data);
	$header = array(
		'Content-Type: multipart/form-data; boundary='.$boundary,
		'Content-Length: '.$dataLen,
		'X-MT-Authorization: MTAuth accessToken='.$accessToken,
	);
	$options = array(
		'http' => array(
			'method' => 'POST',
			'header' => implode("\r\n", $header),
			'content' => $data,
		)
	);
	$json = file_get_contents($baseurl, false, stream_context_create($options));
	//echo $json;
	$asset = json_decode($json);
	$assetId = $asset->id;
	$assetUrl = $asset->url;

	if($assetId) {
		$baseurl = $ini['dataapicgi'].'/v'.$ini['dataapiversion'].'/sites/'.$ini['blogid'].'/entries';
		$entryjson = array(
			"author" => array(
				"id" => 1
			),
			"class" => "entry",
			"status" => "Draft",
			"title" => "This is an entry.",
			"body" => "Foo bar",
			"more" => "blah blah blah",
			"assets" => array(
				array(
					"id" => $assetId,
					"label" => "Sample Image",
					"description" => "My family portrait.",
					"mimeType" => "image/jpeg",
					"url" => $assetUrl,
				)
			)
		);
		$data = array(
			'entry' => json_encode($entryjson),
		);
		$data = http_build_query($data);
		$header = array(
			'X-MT-Authorization: MTAuth accessToken='.$accessToken,
		);
		$options = array(
			'http' => array(
				'method' => 'POST',
				'header' => implode("\r\n", $header),
				'content' => $data,
			)
		);
		$json = file_get_contents($baseurl, false, stream_context_create($options));
		echo $json;
		$entryId = $json->id;

		$baseurl = $ini['dataapicgi'].'/v'.$ini['dataapiversion'].'/sites/'.$ini['blogid'].'/entries/'.$entryId;
		$entryjson = array(
			"author" => array(
				"id" => 1
			),
			"class" => "entry",
			"status" => "Draft",
			"title" => "This is an entry.",
			"body" => "Foo bar",
			"more" => "blah blah blah",
			"assets" => array(
				array(
					"id" => $assetId,
					"label" => "Sample Image",
					"description" => "My family portrait.",
					"mimeType" => "image/jpeg",
					"url" => $assetUrl,
				)
			)
		);
		$data = array(
			'__method' => 'POST',
		//	'entry' => json_encode($entryjson),
			'entry' => $json,
		);
		$data = http_build_query($data);
		$header = array(
			'X-MT-Authorization: MTAuth accessToken='.$accessToken,
		);
		$options = array(
			'http' => array(
				'method' => 'POST',
				'header' => implode("\r\n", $header),
				'content' => $data,
			)
		);
		$json = file_get_contents($baseurl, false, stream_context_create($options));
		echo $json;

	}
}

?>
