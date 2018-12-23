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
			$annotations = annotationsFromXml($annotationXml);
			if ($annotations === null)
			{
				$error = true;
				break;
			}

			$annotations = mergeConcurrentAnnotations($annotations);

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


	function annotationsFromXml($xml)
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

			$annotations[] = [
				'text'      => formatText($text),
				'startTime' => formatTime($startTime),
				'endTime'   => formatTime($endTime),
				'yPos'      => (double)$yPos
			];
		}

		usort($annotations, function ($a, $b) {
			$comp = $a['startTime'] <=> $b['startTime'];
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
		list($hours, $minutes, $seconds) = explode(':', $time);
		list($seconds, $fraction) = explode('.', $seconds);
		return str_pad($hours, 2, '0', STR_PAD_LEFT) . ':' . str_pad($minutes, 2, '0', STR_PAD_LEFT)
			. ':' . str_pad($seconds, 2, '0', STR_PAD_LEFT) . ',' . str_pad($fraction, 3, '0');
	}


	function mergeConcurrentAnnotations($annotations)
	{
		$separator = "\n---\n";

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

?>

<!doctype html>
<html lang="en">
	<head>
		<meta charset="utf-8">
		<title>Youtube annotation converter</title>
		<link rel="stylesheet" type="text/css" href="style.css" />
	</head>
	<body>

		<h1>Youtube annotation converter</h1>

		<form method="post" action="<?= basename($_SERVER['SCRIPT_NAME']) ?>">
			<p class="prompt">Video ID or URL:</p>
			<label>
				<input type="text" name="video_url" placeholder="https://www.youtube.com/watch?v=oHg5SJYRHA0"
				       value="<?= $videoUrl ?>" />
			</label>
			<div class="options">
				<p class="prompt">When annotations start and end around the same time:</p>
				<label class="custom-radio">
					<input type="radio" name="concurrent-annotations" id="merge" checked="checked">
					<span class="radio-label">
						Merge into one caption (separated by: <input type="text" id="separator" value="---" />)
					</span>
				</label>
				<br />
				<label class="custom-radio">
					<input type="radio" name="concurrent-annotations" id="keep">
					<span class="radio-label">Keep separate (only one will show up in the video!)</span>
				</label>
			</div>
			<div class="options">
				<p class="prompt">When an annotation contains a link:</p>
				<label class="custom-radio">
					<input type="radio" name="link-annotations" id="add-url" checked="checked">
					<span class="radio-label">Add the URL to the end</span>
				</label>
				<br />
				<label class="custom-radio">
					<input type="radio" name="link-annotations" id="keep-text">
					<span class="radio-label">Keep only the text</span>
				</label>
				<br />
				<label class="custom-radio">
					<input type="radio" name="link-annotations" id="discard">
					<span class="radio-label">
						Discard the annotation
					</span>
				</label>
			</div>
			<button type="submit" class="primary" name="submit">Convert</button>
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
			<button id="download-button" data-video-id="<?= $videoId ?>">Download .srt file</button>
			<script type="text/javascript" src="srt-download.js"></script>
		<?php endif ?>
		<?php endif ?>

	</body>
</html>