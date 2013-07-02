<?php
$post = file_get_contents('php://input');
if($post===FALSE || $post=='') {
	die("REQUEST ERROR");
}
$postary = json_decode($post, true);

$ini = parse_ini_file('config.ini');

foreach($postary as $postdata) {
	if(!$postdata || $postdata['object']!='user' || $postdata['changed_aspect']!='media') {
		//die("SUBSCRIPTION POST ERROR");
		continue;
	}
	// 登録したsubscription_id
	$subscription_id = $ini['subscriptionid'];
	if($postdata['subscription_id']!=$subscription_id) {
		//die("SUBSCRIPTION ID ERROR");
		continue;
	}
	// 投稿ID(user_id)
	$object_id = $postdata['object_id'];
	// Instagramのuser_id
	//$user_id = "1016279";
	// Instagramのaccess_token
	$access_token = $ini['accesstoken'];
	$read_count = $ini['readcount'];
	$write_count = $ini['writecount'];
	//API endpoint
	$endpoint = "https://api.instagram.com/v1/users/".$object_id."/media/recent/?access_token=".$access_token."&count=".$read_count;
	//file_put_contents("log.txt", date("Y-m-d H:i:s")." ".$endpoint."\n", FILE_APPEND | LOCK_EX);
	//2回リトライ
//	for($i=0; $i<2; $i++) {
//		file_put_contents("log.txt", date("Y-m-d H:i:s")." ".$i."\n", FILE_APPEND | LOCK_EX);
		// 後で戻せるように設定を取得しておく
		$org_timeout = ini_get('default_socket_timeout');
		// 5秒以上かかったらタイムアウトする設定に変更
		$timeout_second = 5;
		ini_set('default_socket_timeout', $timeout_second);
		$json = @file_get_contents($endpoint);
		// 設定を戻す
		ini_set('default_socket_timeout', $org_timeout);
//		if(!($json===false)) break;
//	}
	if($json===false) {
		//die("file_get_contents TIMEOUT ERROR");
		file_put_contents("log.txt", date("Y-m-d H:i:s")." file_get_contents TIMEOUT ERROR.\n", FILE_APPEND | LOCK_EX);
		continue;
	}
	$jsonary = json_decode($json, true);
	for($i=$read_count-1; $i>=0; $i--) {
		$jsondata = $jsonary['data'][$i];
		if(!$jsondata) {
			//die("SUBSCRIPTION JSON DATA ERROR");
			file_put_contents("log.txt", date("Y-m-d H:i:s")." SUBSCRIPTION JSON DATA ERROR.\n", FILE_APPEND | LOCK_EX);
			continue;
		}
		// 重複取得チェック
		$id = $jsondata['id'];
		$this_id_ary = explode("_", $id);
		$this_id = $this_id_ary[0];
		$recent_id = file_get_contents("count.txt");
		if($recent_id===FLASE || $this_id<=$recent_id) {
			//file_put_contents("log.txt", date("Y-m-d H:i:s")." SUBSCRIPTION JSON DATA DUPLICATE SKIP.\n", FILE_APPEND | LOCK_EX);
			continue;
		}

		// 各種情報データ
		$caption_text = $jsondata['caption']['text'];
		$status = (mb_strlen($caption_text, 'UTF-8')>0)?'release':'hold';
		$location_id = $jsondata['location']['id'];
		$location_name = $jsondata['location']['name'];
		if($location_name!="") {
			$latitude = $jsondata['location']['latitude'];
			$longitude = $jsondata['location']['longitude'];
		} else {
			$latitude = "";
			$longitude = "";
		}
		$link = $jsondata['link'];
		$filter = $jsondata['filter'];

		// 件名
		$subject_max_length = 36;
		$caption_text_array = explode("\n", $caption_text, 2);
		if(mb_strlen($location_name, 'UTF-8')>$subject_max_length) {
			$subject = $location_name.' '.trim(mb_substr($caption_text_array[0], 0, 6, 'UTF-8')).'...';
		} else {
			if($location_name!="") {
				$short_caption = trim(mb_substr($caption_text_array[0], 0, $subject_max_length-mb_strlen($location_name, 'UTF-8'), 'UTF-8'));
				$subject = $location_name.' ';
				$subject .= (mb_strlen($short_caption, 'UTF-8')>0)?$short_caption.'1...':$this_id;
			} else {
				$short_caption = trim(mb_substr($caption_text_array[0], 0, $subject_max_length, 'UTF-8'));
				$subject = (mb_strlen($short_caption, 'UTF-8')>0)?$short_caption.'2...':$this_id;
			}
		}
		$subject = htmlspecialchars($subject);
		$caption_text = htmlspecialchars($caption_text);
		$caption_text = '<p>'.$caption_text.'</p>';
		$caption_text = ereg_replace("\r|\n","</p><p>",$caption_text);
		$location_name = htmlspecialchars($location_name);
		// 本文
		$instadata = "[instadata]$latitude,$longitude,$location_id,$location_name,$link,$filter,$id";
		$body = $caption_text.$instadata;
		//$body = ereg_replace("\r|\n","<br />",$body);//Gmail（HTMLメール）対策

		// POSTデータ
		$data = array(
		 	'blogid' => $ini['blogid'],
		 	'authorid' => $ini['authorid'],
		 	'title' => $subject,
		 	'text' => $body,
		 	'status' => ($ini['status'])?$ini['status']:$status,
		);
		// ファイル作成
		$filepatharray = array();
		$imagefilename = $id.'.jpg';
		if($jsondata['images']['standard_resolution']['url']) {
			file_put_contents($imagefilename, file_get_contents($jsondata['images']['standard_resolution']['url']));
			$filepatharray[] = realpath($imagefilename);
		}
		$videofilename = $id.'.mp4';
		if($jsondata['videos']['standard_resolution']['url']) {
			file_put_contents($videofilename, file_get_contents($jsondata['videos']['standard_resolution']['url']));
			$filepatharray[] = realpath($videofilename);
		}
		$data['filepath'] = $filepatharray;
		// POST
		$url = $ini['postcgi'];
		$options = array('http' => array(
			'method' => 'POST',
			'header' => 'User-Agent: Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/27.0.1453.116 Safari/537.36',
			'content' => preg_replace('/%5B[0-9]+%5D/simU', '', http_build_query($data)),
		));
		$contents = file_get_contents($url, false, stream_context_create($options));
		//file_put_contents("log.txt", date("Y-m-d H:i:s")." ".$contents."\n", FILE_APPEND | LOCK_EX);
		$logbody = ereg_replace("\r|\n"," ",$body);
		file_put_contents("log.txt", date("Y-m-d H:i:s")." ".$subject." ".$logbody."\n", FILE_APPEND | LOCK_EX);
		if($jsondata['images']['standard_resolution']['url']) {
			unlink($imagefilename);
		}	
		if($jsondata['videos']['standard_resolution']['url']) {
			unlink($videofilename);
		}
		file_put_contents("count.txt", $this_id, LOCK_EX);
		//print $this_id."\n";
		print($contents);
		if(--$write<=0) {
			break;
		}
	}
}
//exec('cd /virtual/girled/public_html/mt5; ./tools/run-periodic-tasks');
?>	