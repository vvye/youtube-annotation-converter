<?php

	$error = false;

	if (!isset($_POST['submit']))
	{
		$submitted = false;
		$videoUrl = '';
	}
	else
	{
		$submitted = true;
		do
		{
			$videoUrl = trim(htmlspecialchars(strip_tags($_POST['video_url'])));
			$videoId = videoIdFromUrl($videoUrl);
			if ($videoId === null)
			{
				$error = true;
				break;
			}

			$annotationXml = annotationXmlFromVideoId($videoId);

			$linkAnnotationBehavior = $_POST['link-annotations'];
			$annotations = annotationsFromXml($annotationXml, $linkAnnotationBehavior);
			if ($annotations === null)
			{
				$error = true;
				break;
			}

			if ($_POST['concurrent-annotations'] === 'merge')
			{
				$separator = trim($_POST['separator']) !== '' ? "\n" . trim($_POST['separator']) . "\n" : "\n";
				$annotations = mergeConcurrentAnnotations($annotations, $separator);
			}

			$subtitles = [];
			$i = 1;
			foreach ($annotations as $annotation)
			{
				$subtitle = $i++ . "\r\n" . $annotation['startTime'] . ' --> ' . $annotation['endTime']
					. "\r\n" . $annotation['text'];
				$subtitles[] = $subtitle;
			}
			$srtOutput = join("\r\n\r\n", $subtitles);

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


	function annotationsFromXml($xml, $linkAnnotationBehavior = 'keep-text')
	{
		$annotations = [];

		$xmlDoc = new DOMDocument();
		$xmlDoc->loadXML($xml);
		$xpath = new DOMXpath($xmlDoc);

		$elements = $xpath->query('/document/annotations/annotation');
		if ($elements === null)
		{
			return null;
		}

		foreach ($elements as $element)
		{
			$text = $xpath->query('./TEXT/text()', $element)[0]->nodeValue;
			$startTime = $xpath->query('./segment/movingRegion/rectRegion[1]/@t', $element)[0]->nodeValue;
			$endTime = $xpath->query('./segment/movingRegion/rectRegion[2]/@t', $element)[0]->nodeValue;
			$yPos = $xpath->query('./segment/movingRegion/rectRegion[1]/@y', $element)[0]->nodeValue;

			if ($startTime > $endTime)
			{
				list($startTime, $endTime) = [$endTime, $startTime];
			}

			$logData = $xpath->query('./@log_data', $element)[0]->nodeValue;
			$logDataArray = [];
			parse_str($logData, $logDataArray);
			$hasLink = isset($logDataArray['link']);
			if ($hasLink)
			{
				$linkUrl = $logDataArray['link'];
				if ($linkAnnotationBehavior === 'add-url')
				{
					$text .= ': ' . $linkUrl;
				}
				else if ($linkAnnotationBehavior === 'discard')
				{
					continue;
				}
			}

			$annotations[] = [
				'text'      => formatText($text),
				'startTime' => formatTime($startTime),
				'endTime'   => formatTime($endTime),
				'yPos'      => (double)$yPos
			];
		}

		usort($annotations, function ($a, $b) {
			$comp = compareTime($a['startTime'], $b['startTime']);
			if ($comp === 0)
			{
				$comp = $a['yPos'] <=> $b['yPos'];
			}
			return $comp;
		});

		return $annotations;
	}


	function formatText($text)
	{
		$text = trim($text);
		$text = preg_replace('/\n\s+/', "\n", $text);
		return $text;
	}


	function formatTime($time)
	{
		list($hours, $minutes, $seconds, $fraction) = rawTime($time);
		return str_pad($hours, 2, '0', STR_PAD_LEFT) . ':' . str_pad($minutes, 2, '0', STR_PAD_LEFT)
			. ':' . str_pad($seconds, 2, '0', STR_PAD_LEFT) . ',' . str_pad($fraction, 3, '0');
	}


	function rawTime($time)
	{
		list($hours, $minutes, $seconds) = explode(':', $time);
		list($seconds, $fraction) = explode('.', $seconds);
		return [$hours, $minutes, $seconds, $fraction];
	}


	function mergeConcurrentAnnotations($annotations, $separator)
	{
		$annotationsByTimespan = [];
		foreach ($annotations as $annotation)
		{
			// discard fractional parts of start and end time
			$key = substr($annotation['startTime'], 0, -4) . '-' . substr($annotation['endTime'], 0, -4);
			if (!isset($annotationsByTimespan[$key]))
			{
				$annotationsByTimespan[$key] = $annotation;
			}
			else
			{
				$annotationsByTimespan[$key]['text'] .= $separator . $annotation['text'];
			}
		}
		return array_values($annotationsByTimespan);
	}


	function compareTime($time1, $time2)
	{
		list($hours1, $minutes1, $seconds1, $fraction1) = rawTime($time1);
		list($hours2, $minutes2, $seconds2, $fraction2) = rawTime($time2);

		if (($hours1 <=> $hours2) !== 0)
		{
			return $hours1 <=> $hours2;
		}
		if (($minutes1 <=> $minutes2) !== 0)
		{
			return $minutes1 <=> $minutes2;
		}
		if (($seconds1 <=> $seconds2) !== 0)
		{
			return $seconds1 <=> $seconds2;
		}
		return $fraction1 <=> $fraction2;
	}

?>

<!doctype html>
<html lang="en">
	<head>
		<meta charset="utf-8">
		<title>Youtube annotation converter</title>
		<link rel="stylesheet" type="text/css" href="style.css" />
	</head>
	<body>

		<div class="header">
			<h1>Youtube annotation converter</h1>
			<p>converts annotations to subtitles (.srt)</p>
			<p><a href="https://www.eric-kaiser.net">eric-kaiser.net</a></p>
		</div>

		<form method="post" action="<?= basename($_SERVER['SCRIPT_NAME']) ?>">
			<p class="prompt">Video ID or URL:</p>
			<label>
				<input type="text" name="video_url" id="video-url-input"
				       placeholder="https://www.youtube.com/watch?v=oHg5SJYRHA0" value="<?= $videoUrl ?>" />
			</label>
			<div class="options">
				<p class="prompt">When annotations have the same start and end time:</p>
				<label class="custom-radio">
					<input type="radio" name="concurrent-annotations" value="merge" checked="checked">
					<span class="radio-label">
						Merge into one caption (separated by: <input type="text" name="separator" value="---" />)
					</span>
				</label>
				<br />
				<label class="custom-radio">
					<input type="radio" name="concurrent-annotations" value="keep">
					<span class="radio-label">Keep separate (only one will show up in the video!)</span>
				</label>
			</div>
			<div class="options">
				<p class="prompt">When an annotation contains a link:</p>
				<label class="custom-radio">
					<input type="radio" name="link-annotations" value="add-url" checked="checked">
					<span class="radio-label">Add the URL to the end</span>
				</label>
				<br />
				<label class="custom-radio">
					<input type="radio" name="link-annotations" value="keep-text">
					<span class="radio-label">Keep only the text</span>
				</label>
				<br />
				<label class="custom-radio">
					<input type="radio" name="link-annotations" value="discard">
					<span class="radio-label">
						Discard the annotation
					</span>
				</label>
			</div>
			<button type="submit" class="primary" name="submit" id="convert-button">Convert</button>
		</form>

		<?php if ($submitted): ?>
			<hr />
			<?php if ($error): ?>
				<div>failed</div>
			<?php else: ?>
				<label>
					<textarea class="output" id="srt-output"><?= $srtOutput ?></textarea>
				</label>
				<br />
				<button id="download-button" data-video-id="<?= $videoId ?>" disabled="disabled">
					Download .srt file
				</button>
				<div class="no-js info">Enable JavaScript to download the file.</div>
			<?php endif ?>
		<?php endif ?>

		<script type="text/javascript" src="misc.js"></script>

	</body>
</html>