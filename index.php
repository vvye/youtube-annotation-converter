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
			<button type="submit">Convert</button>
		</form>

	</body>
</html>