<?php
$ini = parse_ini_file('config.ini');
//GETリクエスト元ログ取得
file_put_contents("log.txt", date("Y-m-d H:i:s")." ".$_SERVER["REMOTE_ADDR"]." ".gethostbyaddr($_SERVER["REMOTE_ADDR"])." GET:".$_SERVER["QUERY_STRING"]."\n", FILE_APPEND | LOCK_EX);
//登録時の、Instagram側の確認用アクセスを受ける
if($_SERVER['REQUEST_METHOD']=='GET' && isset($_GET['hub_verify_token'])&&$_GET['hub_verify_token']==$ini['verify_token'] && isset($_GET['hub_challenge']) && isset($_GET['hub_mode'])&&$_GET['hub_mode']=='subscribe') {
	file_put_contents("log.txt", date("Y-m-d H:i:s")." RETURN HUB_CHALLENGE:".$_GET['hub_challenge']."\n", FILE_APPEND | LOCK_EX);
	//確認用のキーを返却する
	//header("HTTP/1.1 200 OK");
	//header("Status: 200");
	//header("Content-Type: text/plain");
	echo $_GET['hub_challenge'];
	exit;
}
$post = file_get_contents('php://input');
//POSTリクエスト元ログ取得
file_put_contents("log.txt", date("Y-m-d H:i:s")." POST:".$post."\n", FILE_APPEND | LOCK_EX);
if($post===FALSE || $post=='') {
	file_put_contents("log.txt", date("Y-m-d H:i:s")." REQUEST ERROR\n", FILE_APPEND | LOCK_EX);
	die("REQUEST ERROR");
}
$postary = json_decode($post, true);
foreach($postary as $postdata) {
	if(!$postdata || $postdata['object']!='user' || $postdata['changed_aspect']!='media') {
		file_put_contents("log.txt", date("Y-m-d H:i:s")." SUBSCRIPTION POST ERROR\n", FILE_APPEND | LOCK_EX);
		//continue;
		die("SUBSCRIPTION POST ERROR");
	}
	// 登録したsubscription_id
//2016-08-23
//	$subscription_id = $ini['subscription_id'];
//	if($postdata['subscription_id']!=$subscription_id) {
//		file_put_contents("log.txt", date("Y-m-d H:i:s")." SUBSCRIPTION ID ERROR\n", FILE_APPEND | LOCK_EX);
//		//continue;
//		die("SUBSCRIPTION ID ERROR");
//	}
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
		//JSON内容確認
		//file_put_contents("log.txt", date("Y-m-d H:i:s")." $json:".$json."\n", FILE_APPEND | LOCK_EX);
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
	$countfile = "count.txt";
	for($i=$read_count-1; $i>=0; $i--) {
		$jsondata = $jsonary['data'][$i];
		if(!$jsondata) {
			//die("SUBSCRIPTION JSON DATA ERROR");
			file_put_contents("log.txt", date("Y-m-d H:i:s")." SUBSCRIPTION JSON DATA ERROR. jsondata:".json_encode($jsondata)."\n", FILE_APPEND | LOCK_EX);
			continue;
		}
		// 重複取得チェック
		$id = $jsondata['id'];
		$this_id_ary = explode("_", $id);
		$this_id = $this_id_ary[0];
		// $recent_id = file_get_contents($countfile);
		// if($recent_id===FLASE || $this_id<=$recent_id) {
		// 	//file_put_contents("log.txt", date("Y-m-d H:i:s")." SUBSCRIPTION JSON DATA DUPLICATE SKIP.\n", FILE_APPEND | LOCK_EX);
		// 	continue;
		// }
		$recent_id = 0;
		$fp = @fopen($countfile, "r+");
		if($fp && @flock($fp, LOCK_EX)) {
			$recent_id = fgetss($fp);
		}
		if($recent_id==0 || $recent_id===FLASE || $this_id<=$recent_id) {
			//file_put_contents("log.txt", date("Y-m-d H:i:s")." SUBSCRIPTION JSON DATA DUPLICATE SKIP. recent_id:".$recent_id." this_id:".$this_id."\n", FILE_APPEND | LOCK_EX);
			if($fp) {
				flock($fp, LOCK_UN);
				fclose($fp);
			}
			continue;
		}
		//file_put_contents("log.txt", date("Y-m-d H:i:s")." SUBSCRIPTION JSON DATA NO SKIP. recent_id:".$recent_id." this_id:".$this_id."\n", FILE_APPEND | LOCK_EX);

		// 各種情報データ
		$caption_text = $jsondata['caption']['text'];
		$status = (mb_strlen($caption_text, 'UTF-8')>0)?'release':'hold';
		$location_id = $jsondata['location']['id'];
		$location_name = $jsondata['location']['name'];
		if($location_name!="") {
			$location_name = ereg_replace("　"," ",$location_name);
			$latitude = $jsondata['location']['latitude'];
			$longitude = $jsondata['location']['longitude'];
		} else {
			$latitude = "";
			$longitude = "";
		}
		$link = $jsondata['link'];
		$filter = $jsondata['filter'];

		// 件名
		// $subject_max_length = 36;
		// $caption_text_array = explode("\n", $caption_text, 2);
		// if(mb_strlen($location_name, 'UTF-8')>$subject_max_length) {
		// 	$subject = $location_name." ".trim(mb_substr($caption_text_array[0], 0, 6, 'UTF-8')).'...';
		// } else {
		// 	if($location_name!="") {
		// 		$short_caption = trim(mb_substr($caption_text_array[0], 0, $subject_max_length-mb_strlen($location_name, 'UTF-8'), 'UTF-8'));
		// 		$subject = $location_name." ";
		// 		$subject .= (mb_strlen($short_caption, 'UTF-8')>0)?$short_caption.'...':'';
		// 	} else {
		// 		$short_caption = trim(mb_substr($caption_text_array[0], 0, $subject_max_length, 'UTF-8'));
		// 		$subject = (mb_strlen($short_caption, 'UTF-8')>0)?$short_caption.'...':'';
		// 	}
		// }

		//2014/07/25 キーフレーズ抽出、タイトルに付加
		$subject_max_length = $ini['subjectmaxlength'];
		$caption_text_string = str_replace(array("\r\n","\r","\n"), " ", $caption_text);
		$caption_text_string = strip_tags($caption_text_string);
		$caption_text_string = trim($caption_text_string);
		if($location_name!="") {
			if(mb_strlen($location_name, 'UTF-8')>=$subject_max_length) {
				$append_length = 6;
				$subject = trim(mb_substr($location_name, 0, $subject_max_length-($append_length+1), 'UTF-8'))." ".trim(mb_substr($caption_text_string, 0, $append_length, 'UTF-8'));
				$subject = trim($subject)."...";
			} else {
				$subject = $location_name;
			}
		}
		$sentence = $caption_text_string;
		$output = "xml";
		$callback = "";
		require(dirname(__FILE__).'/../yahoo/keyphrase.php');
		$Keyphrase = new Keyphrase();
		$response = $Keyphrase->getKeyphrase($sentence, $output, $callback);
		//file_put_contents("log.txt", date("Y-m-d H:i:s")." ".$response."\n", FILE_APPEND | LOCK_EX);
		$responsexml = simplexml_load_string($response);
		$result_num = count($responsexml->Result);
		for($i=0; $i<$result_num; $i++){
			$result = $responsexml->Result[$i];
			$keyphrase = trim($result->Keyphrase);
			if(mb_strlen($subject." ".$keyphrase, 'UTF-8')<$subject_max_length) {
				if($subject!="") {
					$subject = trim($subject)." ";
				}
				$subject .= $keyphrase;
			}
		}
		if((mb_strlen($subject, 'UTF-8')+4)<$subject_max_length) {
			if($subject!="") {
				$subject = trim($subject)." ";
			}
			$subject .= trim(mb_substr($caption_text_string, 0, $subject_max_length-(mb_strlen($subject, 'UTF-8')+4), 'UTF-8'));
			$subject = trim($subject)."...";
		}

		//$subject = htmlspecialchars($subject, ENT_QUOTES);
		//$location_name = htmlspecialchars($location_name, ENT_QUOTES);
		$caption_text = htmlspecialchars($caption_text, ENT_QUOTES);
		$caption_text = '<p>'.$caption_text.'</p>';
		$caption_text = ereg_replace("\r|\n","</p><p>",$caption_text);
		// 本文
		$location_name = ereg_replace(",","，",$location_name);
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
		 	'categoryid' => $ini['categoryid'],
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
		//file_put_contents($countfile, $this_id, LOCK_EX);
		if($fp) {
			ftruncate($fp, 0);
			fwrite($fp, $this_id);
			fflush($fp);
			flock($fp, LOCK_UN);
			fclose($fp);
		}
		//print $this_id."\n";
		print($contents);
		if(--$write<=0) {
			break;
		}
	}
}
//exec('cd /virtual/girled/public_html/mt; ./tools/run-periodic-tasks');

?>
