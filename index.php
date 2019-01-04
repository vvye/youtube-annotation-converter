<?php

	$errorMessage = '';

	if (!isset($_POST['submit']))
	{
		$submitted = false;
		$videoUrl = '';
		$overlappingAnnotationBehavior = 'merge';
		$linkAnnotationBehavior = 'add-url';
		$separator = '---';
	}
	else
	{
		$submitted = true;
		// better safe than sorry
		$overlappingAnnotationBehavior = trim(htmlspecialchars(strip_tags($_POST['overlapping-annotations'])));
		$linkAnnotationBehavior = trim(htmlspecialchars(strip_tags($_POST['link-annotations'])));
		$videoUrl = trim(htmlspecialchars(strip_tags($_POST['video_url'])));
		$separator = trim(htmlspecialchars(strip_tags($_POST['separator'])));

		do
		{
			if ($videoUrl === '')
			{
				$errorMessage = 'Enter a video ID or URL.';
				break;
			}

			$videoId = videoIdFromUrl($videoUrl);
			if ($videoId === null)
			{
				$errorMessage = 'The video ID couldn\'t be found.';
				break;
			}

			$annotationXml = annotationXmlFromVideoId($videoId);
			if ($annotationXml === null || ($annotationXml) === '')
			{
				$errorMessage = 'The video couldn\'t be found.';
				break;
			}

			$annotations = annotationsFromXml($annotationXml, $linkAnnotationBehavior);
			if ($annotations === null)
			{
				$errorMessage = 'Something went wrong processing the annotations.';
				break;
			}
			if (empty($annotations))
			{
				$errorMessage = 'No annotations were found in that video.';
				break;
			}

			if ($overlappingAnnotationBehavior === 'merge')
			{
				$separator = trim($_POST['separator']) !== '' ? "\n" . trim($_POST['separator']) . "\n" : "\n";
				$annotations = mergeOverlappingAnnotations($annotations, $separator);
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

		if (curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 200)
		{
			curl_close($ch);
			return null;
		}
		curl_close($ch);

		return $rawXml;
	}


	function annotationsFromXml($xml, $linkAnnotationBehavior)
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
			$text = $xpath->query('./TEXT/text()', $element)[0]->nodeValue ?? null;
			$startTime = $xpath->query('./segment/movingRegion/rectRegion[1]/@t', $element)[0]->nodeValue ?? null;
			$endTime = $xpath->query('./segment/movingRegion/rectRegion[2]/@t', $element)[0]->nodeValue ?? null;
			$yPos = $xpath->query('./segment/movingRegion/rectRegion[1]/@y', $element)[0]->nodeValue ?? null;

			if ($text === null || $startTime === null || $endTime === null || $yPos === null)
			{
				continue;
			}

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
		list($hours, $minutes, $seconds, $fraction) = rawTime($time, '.');
		return str_pad($hours, 2, '0', STR_PAD_LEFT) . ':' . str_pad($minutes, 2, '0', STR_PAD_LEFT)
			. ':' . str_pad($seconds, 2, '0', STR_PAD_LEFT) . ',' . str_pad($fraction, 3, '0');
	}


	function rawTime($time, $fractionSeparator)
	{
		if ($fractionSeparator === '.')
		{
			$fractionSeparator = '\.';
		}

		$timeRegex = '/((?<hours>\d+):)?(?<minutes>\d+):(?<seconds>\d+)' . $fractionSeparator . '(?<fraction>\d+)/i';
		preg_match_all($timeRegex, $time, $matches, PREG_UNMATCHED_AS_NULL);

		$hours = $matches['hours'][0] ?: 0;
		$minutes = $matches['minutes'][0] ?: 0;
		$seconds = $matches['seconds'][0] ?: 0;
		$fraction = $matches['fraction'][0] ?: 0;

		return [$hours, $minutes, $seconds, $fraction];
	}


	function mergeOverlappingAnnotations($annotations, $separator)
	{
		$times = [];
		foreach ($annotations as $annotation)
		{
			$times[] = $annotation['startTime'];
			$times[] = $annotation['endTime'];
		}
		$times = array_unique($times);
		usort($times, 'compareTime');

		$splitAnnotations = [];
		for ($i = 0; $i < count($times) - 1; $i++)
		{
			$currentTime = $times[$i];
			$nextTime = $times[$i + 1];
			$annotationsDuringThisTime = annotationsByTimespan($annotations, $currentTime, $nextTime);
			if (empty($annotationsDuringThisTime))
			{
				continue;
			}

			$text = join($separator, array_map(function ($a) {
				return $a['text'];
			}, $annotationsDuringThisTime));

			$splitAnnotations[] = [
				'text'      => $text,
				'startTime' => $currentTime,
				'endTime'   => $nextTime
			];
		}

		return $splitAnnotations;
	}


	function annotationsByTimespan($annotations, $startTime, $endTime)
	{
		return array_filter($annotations, function ($a) use ($startTime, $endTime) {
			return (compareTime($a['startTime'], $startTime) !== 1) && (compareTime($a['endTime'], $endTime) !== -1);
		});
	}


	function compareTime($time1, $time2)
	{
		list($hours1, $minutes1, $seconds1, $fraction1) = rawTime($time1, ',');
		list($hours2, $minutes2, $seconds2, $fraction2) = rawTime($time2, ',');

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
		<title>YouTube annotation converter</title>
		<link rel="stylesheet" type="text/css" href="style.css" />
		<meta name="description" content="Preserve YouTube annotations by converting them to subtitles (.srt).">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
	</head>
	<body>

		<div class="main">

			<div class="header">
				<h1>YouTube annotation converter</h1>
				<p>Save annotations by converting them to subtitles (.srt)</p>
				<p><a href="https://www.eric-kaiser.net">www.eric-kaiser.net</a> &bull;
					<a href="https://github.com/vvye/youtube-annotation-converter">This tool on GitHub</a></p>
			</div>

			<form method="post" action="<?= basename($_SERVER['SCRIPT_NAME']) ?>">
				<p class="prompt">Video ID or URL:</p>
				<label>
					<input type="text" name="video_url" id="video-url-input"
					       placeholder="https://www.youtube.com/watch?v=oHg5SJYRHA0" value="<?= $videoUrl ?>" />
				</label>
				<div class="options">
					<p class="prompt">When annotations overlap:</p>
					<label class="custom-radio">
						<input type="radio" name="overlapping-annotations" value="merge"
							<?= $overlappingAnnotationBehavior === 'merge' ? 'checked="checked"' : '' ?>>
						<span class="radio-label">
						Merge into one subtitle (separated by: <input type="text" name="separator"
						                                              value="<?= $separator ?>" />)
					</span>
					</label>
					<br />
					<label class="custom-radio">
						<input type="radio" name="overlapping-annotations" value="keep"
							<?= $overlappingAnnotationBehavior === 'keep' ? 'checked="checked"' : '' ?>>
						<span class="radio-label">Keep separate (only one will show up in the video!)</span>
					</label>
				</div>
				<div class="options">
					<p class="prompt">When an annotation contains a link:</p>
					<label class="custom-radio">
						<input type="radio" name="link-annotations" value="add-url"
							<?= $linkAnnotationBehavior === 'add-url' ? 'checked="checked"' : '' ?>>
						<span class="radio-label">Add the URL to the end</span>
					</label>
					<br />
					<label class="custom-radio">
						<input type="radio" name="link-annotations" value="keep-text"
							<?= $linkAnnotationBehavior === 'keep-text' ? 'checked="checked"' : '' ?>>
						<span class="radio-label">Keep only the text</span>
					</label>
					<br />
					<label class="custom-radio">
						<input type="radio" name="link-annotations" value="discard"
							<?= $linkAnnotationBehavior === 'discard' ? 'checked="checked"' : '' ?>>
						<span class="radio-label">
						Discard the annotation
					</span>
					</label>
				</div>
				<button type="submit" class="primary" name="submit" id="convert-button">Convert</button>
			</form>

		</div>

		<?php if ($submitted): ?>
			<div class="output">
				<hr />
				<?php if ($errorMessage !== ''): ?>
					<div class="error msg"><?= $errorMessage ?></div>
				<?php else: ?>
					<label>
						<textarea class="newly-added srt-output" id="srt-output"><?= $srtOutput ?></textarea>
					</label>
					<br />
					<button id="download-button" class="newly-added show-if-js" data-video-id="<?= $videoId ?>"
					        disabled="disabled">
						Download .srt file
					</button>
					<div class="info msg hide-if-js">Enable JavaScript to download the file.</div>
				<?php endif ?>
			</div>
		<?php endif ?>

		<div class="intro">
			<h2>What's this about?</h2>
			<p><a href="https://support.google.com/youtube/answer/7342737">On January 15, 2019, YouTube is getting rid
					of annotations.</a></p>
			<p>Annotations are boxes of text and/or links that could be placed anywhere inside a video. Since May 2017
				you can no longer add new annotations, and on January 15, 2019, even existing annotations will
				disappear. This is really unfortunate for older videos that make heavy use of them, providing
				commentary, background info, or corrections.</p>
			<h2>What does this tool do?</h2>
			<p>If you want to preserve your videos' annotations, you can use this tool to convert them into subtitles
				instead.</p>
			<ol>
				<li>Enter a video URL</li>
				<li>Click "Convert" and get an .srt file containing all the annotation text</li>
				<li>Upload the .srt file in YouTube Studio to add the text as subtitles</li>
			</ol>
			<p>To check which of your videos have annotations, use
				<a href="https://slayweb.com/annofetch/">AnnoFetch</a>.</p>
			<h2>Isn't this a bad idea?</h2>
			<p>Almost certainly! I'm just waiting for someone to tell me why.</p>
			<p>It's probably not the intended use for subtitles, but it seems better than nothing &mdash; at least you
				can keep the annotation text around in some form.</p>
			<h2>Credit is due</h2>
			<p>I wrote all the code myself, but the ideas aren't mine.</p>
			<ul>
				<li>From <a href="https://www.youtube.com/watch?v=LYIzvtjtR90">This video by EposVox</a>, I learned
					YouTube was getting rid of annotations.
				</li>
				<li><a href="https://www.youtube.com/watch?v=MLYaXkpbAVU">This video by KarolaTea</a> linked to <a
							href="https://slayweb.com/annofetch/">AnnoFetch</a> by <a
							href="https://twitter.com/slayweb">slayweb</a>, which shows you which of your videos have
					annotations.
				</li>
				<li><a href="https://github.com/germanger">germanger</a> made the <a
							href="https://github.com/germanger/youtubeannotations-to-srt">original tool</a> to convert
					annotations to subtitles (<a href="https://github.com/germanger/youtubeannotations-to-srt-js">HTML/JS
						version</a>), but they seemed to spit them out in an invalid format, and I decided I'd try
					making my own.
				</li>
				<li>From <a href="https://stefansundin.github.io/youtube-copy-annotations">this web app</a>, which can
					copy annotation data across videos, I learned how to fetch annotation data from YouTube given a
					video ID.
				</li>
			</ul>
		</div>

		<script type="text/javascript" src="main.js"></script>

	</body>
</html>