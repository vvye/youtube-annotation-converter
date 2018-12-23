<!doctype html>
<html lang="en">
	<head>
		<meta charset="utf-8">
		<title>Youtube annotation converter</title>
	</head>
	<body>

		<h1>Youtube annotation converter</h1>

		<form method="post" action="<?= basename($_SERVER['SCRIPT_NAME']) ?>">
			<label>
				Video ID or URL:
				<input type="text" name="video_url" placeholder="https://www.youtube.com/watch?v=oHg5SJYRHA0" />
			</label>
			<button type="submit" name="submit">Convert</button>
		</form>

		<?php

			if (isset($_POST['submit']))
			{
				do
				{
					$videoUrl = trim($_POST['video_url']);
					$videoId = videoIdFromUrl($videoUrl);
					if ($videoId === null)
					{
						echo 'failed';
						break;
					}

					$annotationXml = annotationXmlFromVideoId($videoId);


				} while (false);
			}


			function videoIdFromUrl($url)
			{
				if (preg_match('/^[a-zA-Z0-9-_]{11}$/', $url))
				{
					return $url;
				}

				// https://stackoverflow.com/a/3393008/3972493
				parse_str(parse_url($url, PHP_URL_QUERY), $vars);
				return $vars['v'] ?? null;
			}

			function annotationXmlFromVideoId($videoId)
			{
				$annotationDataUrl = 'https://www.youtube.com/annotations_invideo?video_id=' . urlencode($videoId);

				$ch = curl_init();
				curl_setopt_array($ch, [
					CURLOPT_RETURNTRANSFER => 1,
					CURLOPT_URL            => $annotationDataUrl,
				]);
				$rawXml = curl_exec($ch);
				curl_close($ch);

				return $rawXml;
			}

		?>

	</body>
</html>