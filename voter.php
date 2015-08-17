<?php
set_time_limit(0);
class StrawPoll {
	public function __construct() {
	}

	public function vote($id, $amount = -1, $vote = 0, $proxyList = null, $timeout = 8, $showErrors = false) {
		$mh = curl_multi_init();
		$chs = array();
		$proxies = file($proxyList);
		$post = '{"pollId":'.$id.',"votes":['.$vote.']}';
		$headers = array(
			'Host: strawpoll.me',
            'Accept: application/json, text/plain, */*',
            'Accept-Language: en-US,en;q=0.5',
            'Content-Type: application/json;charset=utf-8',
            'Content-Length: '.strlen($post),
            'Connection: keep-alive',
            'Pragma: no-cache',
            'Cache-Control: no-cache'
		);
		$loop = false;
		if($amount == -1) {
			$loop = true;
			$amount = 100;
		}
		$used = array();
		for($i = 0; $i < $amount; $i++) {
			$ch = $this->makeCurl('http://strawpoll.me/api/v2/votes');
			$chs[] = $ch;
			if(isset($proxies)) {
                
				if(count($proxies) < 1) {
					if($showErrors) {
						echo 'Out of proxies :(' . PHP_EOL;
					}
					break;
				}
				$key = array_keys($proxies)[mt_rand(0, count($proxies) - 1)];
				$proxy = $proxies[$key];
				unset($proxies[$key]);
				$proxyType = CURLPROXY_HTTP;
					$proxy = trim($proxy);
					$parts = explode(':', $proxy);
					if(isset($parts[0], $parts[1])) {
						$proxyIP = $parts[0];
						$proxyPort = $parts[1];
					} else {
						$i--;
						continue;
					}
					if(isset($parts[2])) {
						$proxyType = strtoupper($proxyType) == 'SOCKS5' ? CURLPROXY_SOCKS5 : CURLPROXY_HTTP;
					}
				if(isset($used[$proxyIP])) {
					$i--;
					continue;
				}
				$used[$proxyIP] = true;
				if(!filter_var($proxyIP, FILTER_VALIDATE_IP,
										 FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
				   || (!ctype_digit($proxyPort) || ($proxyPort < 0 || $proxyPort > 65535))) {
					$i--;
					continue;
				}
				curl_setopt_array($ch, array(
					CURLOPT_PROXY => $proxyIP . ':' . $proxyPort,
					CURLOPT_PROXYTYPE => $proxyType
				));
			}
			curl_setopt_array($ch, array(
				CURLOPT_POSTFIELDS => $post,
				CURLOPT_POST => true,
				CURLOPT_HTTPHEADER => $headers,
				CURLOPT_REFERER => 'http://strawpoll.me/'.$id,
				CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 6.3; Win64; x64; rv:41.0) Gecko/20100101 Firefox/41.0',
				CURLOPT_TIMEOUT => $timeout,
                CURLOPT_ENCODING => 'gzip, deflate'
			));
			curl_multi_add_handle($mh, $ch);
		}
		$running = null;
		$votes = 0;
		$j = 0;
		$results = array();
		do {
			while(($exec = curl_multi_exec($mh, $running)) == CURLM_CALL_MULTI_PERFORM);
			if($exec != CURLM_OK) {
				break;
			}
			while($ch = curl_multi_info_read($mh)) {
				$j++;
				$ch = $ch['handle'];
				$error = curl_error($ch);
				if(!$error) {
					$resp = curl_multi_getcontent($ch);
					$out = json_decode($resp, true);
					if(!isset($out['success'])) {
						if($showErrors) {
							echo 'Didn\'t vote. Invalid response.' . PHP_EOL;
							echo $resp . PHP_EOL;
						}
					} else {
						if($out['success'] == true) {
							$votes++;
							echo '[' . $votes . '] Voted' . PHP_EOL;
						}
					}
					$results[] = $out;
				} else {
					$results[] = $error;
					if($showErrors) {
						echo $error . PHP_EOL;
					}
				}
				curl_multi_remove_handle($mh, $ch);
				curl_close($ch);
			}
		} while($running);
		curl_multi_close($mh);
		if($loop) {
			$this->vote($id, -1, $vote, $proxyList, $timeout, $showErrors);
			return array('results' => $results, 'votes' => $votes);
		}
		return array('results' => $results, 'votes' => $votes, 'total' => $amount);
	}

	public function makeCurl($url) {
		$cookie = dirname(__FILE__) . '/strawpoll.txt';
		$ch = curl_init($url);
		curl_setopt_array($ch, array(
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_COOKIEFILE => $cookie,
			CURLOPT_COOKIEJAR => $cookie
		));
		return $ch;
	}
}

$sp = new StrawPoll();
$votes = $sp->vote($argv[1], -1, $argv[2], './proxies.txt', 8, false);
echo 'Successfully voted ' . $votes['votes'] . '/' . $votes['total'] . ' time(s)';
