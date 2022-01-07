<?php
$to_scan     = json_decode(file_get_contents("toscan.json"));
$past_urls   = json_decode(file_get_contents("spider.json"));
$url_count   = count($to_scan);
$new_urls    = array();
$curl_arr    = array();
$curl_master = curl_multi_init();
for ($i = 0; $i < $url_count; $i++) {
	$base         = $to_scan[$i];
	$curl_arr[$i] = curl_init($base);
	curl_setopt($curl_arr[$i], CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($curl_arr[$i], CURLOPT_FOLLOWLOCATION, TRUE);
	curl_setopt($curl_arr[$i], CURLOPT_CONNECTTIMEOUT, 20);
	curl_setopt($curl_arr[$i], CURLOPT_TIMEOUT, 20);
	curl_multi_add_handle($curl_master, $curl_arr[$i]);
}

do {
	curl_multi_exec($curl_master, $running);
} while ($running > 0);

echo "<pre>";
for ($i = 0; $i < $url_count; $i++) {
	$html = curl_multi_getcontent($curl_arr[$i]);
	preg_match_all('/href="(.*?)"/', $html, $urls1);
	preg_match_all('/src="(.*?)"/', $html, $urls2);
	$urls = array_merge($urls1[1], $urls2[1]);
	if (count($urls) == 0) {
		unset($new_urls[$i]);
		continue;
	}

	foreach ($urls as &$url) {
		$base     = $to_scan[$i];
		$protocol = explode(":", $base)[0];
		$host     = "$protocol://" . explode("/", $base)[2];
		if (substr($url, 0, 2) === "//") {
			$url = "$protocol:$url"; # for "//google.com/search" urls
		}

		if (substr($url, 0, 1) === "/") {
			$url = "$host$url"; # for "/search" urls
		} else if (!strpos($url, ":")) {
			$url = "$base/$url"; # for "search" urls
		}

		if (strpos($url, "http") !== 0) {
			$url = $to_scan[0]; # don't allow "data:" or "javascript:" or "file:///" or "ftp://" or etc urls
		}

		$url = rtrim(
			explode(
				"?",
				explode(
					"#",
					$url
				)[0]
			)[0],
			"/"
		); # deal with parameters, hashes, and trailing slashes
		echo $url;
		echo "<br>";
	}

	$new_urls = array_merge($new_urls, array_values($urls));
}

echo "</pre>";

$file = fopen("spider.json", 'w+') or die("Failed to open file");
flock($file, LOCK_EX) or die("Failed to lock file");
fwrite(
	$file,
	json_encode(
		array_values(
			array_merge($past_urls, $new_urls)
		)
	)
) or die("Could not write to file");
fclose($file);

$file2 = fopen("toscan.json", 'w+') or die("Failed to open file2");
flock($file2, LOCK_EX) or die("Failed to lock file2");
fwrite(
	$file2,
	json_encode(
		array_values(
			$new_urls
		)
	)
) or die("Could not write to file2");
fclose($file2);

echo "<a href='spider.json'>Success</a>";

