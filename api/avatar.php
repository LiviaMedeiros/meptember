<?php
require_once('conf.php');

$data = json_decode(file_get_contents('php://input'), true);

function err() {
	exit();
}

function get_twipic($id) {
	$url = 'https://api.twitter.com/1.1/users/show.json';
	$ts = time();
	$oauth = [
		'oauth_consumer_key' => CONF_TWITTER['consumer_key'],
		'oauth_nonce' => $ts,
		'oauth_signature_method' => 'HMAC-SHA1',
		'oauth_timestamp' => $ts,
		'oauth_token' => CONF_TWITTER['oauth_access_token'],
		'oauth_version' => '1.0'
	];
	$query = ['screen_name' => $id];
	$oauth['oauth_signature'] = base64_encode(hash_hmac(
		algo: 'sha1',
		data: implode('&', array_map('rawurlencode', [
			'GET',
			$url,
			http_build_query(
				data: array_merge($oauth, $query),
				encoding_type: PHP_QUERY_RFC3986
			)
		])),
		key: implode('&', array_map('rawurlencode', [
			CONF_TWITTER['consumer_secret'],
			CONF_TWITTER['oauth_access_token_secret']
		])),
		binary: true
	));
	curl_setopt_array($ch = curl_init(), [
		CURLOPT_HTTPHEADER => [
			'Authorization: OAuth '.implode(', ', array_map(fn($key, $val) => $key.'="'.rawurlencode($val).'"', array_keys($oauth), $oauth))
		],
		CURLOPT_URL => $url.'?'.http_build_query(
			data: $query,
			encoding_type: PHP_QUERY_RFC3986
		),
		CURLOPT_RETURNTRANSFER => true
	]);
	($json = curl_exec($ch)) || err();
	curl_close($ch);
	($data = json_decode($json, true)) || err();
	($ava = $data['profile_image_url']) || err();
	$result = str_replace(
		search: ['http://', '_normal'],
		replace: ['https://', ''],
		subject: $ava
	);
	header('X-Mep-Source: '.$result);
	return $result;
}

function get_ytpic($id) {
	curl_setopt_array($ch = curl_init(), [
		CURLOPT_URL => 'https://www.googleapis.com/youtube/v3/channels?'.http_build_query([
			'part' => 'snippet',
			'id' => $id,
			'fields' => 'items/snippet/thumbnails',
			'key' => CONF_YOUTUBE['key']
		]),
		CURLOPT_RETURNTRANSFER => true,
	]);
	($json = curl_exec($ch)) || err();
	curl_close($ch);
	($data = json_decode($json, true)) || err();
	($ava = $data['items'][0]['snippet']['thumbnails']['high']['url']) || err();
	$result = $ava;
	header('X-Mep-Source: '.$result);
	return $result;
}

function get_dcpic($id) {
	curl_setopt_array($ch = curl_init(), [
		CURLOPT_HTTPHEADER => [
			'Authorization: Bot '.CONF_DISCORD['token']
		],
		CURLOPT_URL => 'https://discord.com/api/v10/users/'.$id,
		CURLOPT_RETURNTRANSFER => true,
	]);
	($json = curl_exec($ch)) || err();
	curl_close($ch);
	($data = json_decode($json, true)) || err();
	($ava = $data['avatar']) || err();
	$result = 'https://cdn.discordapp.com/avatars/'.$id.'/'.$ava;
	header('X-Mep-Source: '.$result);
	return $result;
}


$data || err();
($value = $data['value']) || err();
($type = $data['type']) || err();

switch($type) {
	case 'twitter':
		$ava = get_twipic($value);
		break;
	case 'youtube':
		$ava = get_ytpic($value);
		break;
	case 'discord':
		$ava = get_dcpic($value);
		break;
	default:
		err();
}

curl_setopt_array($ch = curl_init(), [
	CURLOPT_HEADERFUNCTION => function($ch, $header) {
		preg_match('/^[Cc]ontent-[Tt]ype:/', $header) && header($header);
		return strlen($header);
	},
	CURLOPT_URL => $ava,
	CURLOPT_RETURNTRANSFER => true
]);
($body = curl_exec($ch)) || err();
curl_close($ch);
echo $body;
exit();
