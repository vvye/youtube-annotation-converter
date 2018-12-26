<h1>YouTube annotation converter</h1>
<p><a href="http://misc.eric-kaiser.net/youtube-annotation-converter">
	http://misc.eric-kaiser.net/youtube-annotation-converter</a></p>
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
