for (let elem of document.querySelectorAll('.no-js')) {
    elem.style.display = 'none';
}

let videoUrlInput = document.getElementById('video-url-input');
let convertButton = document.getElementById('convert-button');
let outputTextarea = document.getElementById('srt-output');
let downloadButton = document.getElementById('download-button');

(videoUrlInput.onchange = videoUrlInput.onkeypress = videoUrlInput.onpaste = videoUrlInput.oninput = function () {
    if (!videoUrlInput.value.trim()) {
        convertButton.setAttribute('disabled', 'disabled');
    } else {
        convertButton.removeAttribute('disabled');
    }
})();

if (downloadButton) {
    downloadButton.removeAttribute('disabled');
    downloadButton.onclick = function () {
        let srtOutput = outputTextarea.value;
        let videoId = this.getAttribute('data-video-id');
        offerDownload(srtOutput, videoId + '_annotations.srt');
    };
}

function offerDownload(data, filename) {
    // https://stackoverflow.com/a/33542499/3972493
    let blob = new Blob([data], {type: 'text/srt'});
    if (window.navigator.msSaveOrOpenBlob) {
        window.navigator.msSaveBlob(blob, filename);
    } else {
        let elem = window.document.createElement('a');
        elem.href = window.URL.createObjectURL(blob);
        elem.download = filename;
        document.body.appendChild(elem);
        elem.click();
        document.body.removeChild(elem);
    }
}