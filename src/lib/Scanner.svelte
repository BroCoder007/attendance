<script>
  import { onDestroy } from 'svelte';
  let { onscan } = $props();
  let video;
  let stream;
  let timer;
  let scanning = $state(false);
  let message = $state('');
  function stop() {
    clearTimeout(timer);
    stream?.getTracks().forEach((track) => track.stop());
    stream = null;
    scanning = false;
  }
  async function start() {
    message = '';
    if (!('BarcodeDetector' in window)) {
      message =
        'Camera scanning is not supported in this browser. Paste the student QR text below or select the student manually.';
      return;
    }
    try {
      const formats = await window.BarcodeDetector.getSupportedFormats();
      if (!formats.includes('qr_code'))
        throw new Error('QR scanning is unavailable in this browser.');
      stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: 'environment', width: { ideal: 640 } },
        audio: false
      });
      video.srcObject = stream;
      await video.play();
      scanning = true;
      const detector = new window.BarcodeDetector({ formats: ['qr_code'] });
      const scan = async () => {
        try {
          const codes = await detector.detect(video);
          if (codes.length) {
            onscan(codes[0].rawValue);
            stop();
            message = 'QR captured. Review and mark the student present.';
            return;
          }
        } catch {
          stop();
          message =
            'Unable to read the camera. Use QR text or select a student.';
          return;
        }
        if (stream) timer = setTimeout(scan, 300);
      };
      timer = setTimeout(scan, 300);
    } catch {
      stop();
      message =
        'Camera unavailable. Allow camera access on HTTPS, paste QR text or select a student.';
    }
  }
  onDestroy(stop);
</script>

<div class="scanner">
  <video
    bind:this={video}
    class:visible={scanning}
    muted
    playsinline
    aria-label="Student QR camera preview"><track kind="captions" /></video
  ><button type="button" class="secondary" onclick={scanning ? stop : start}
    >{scanning ? 'Stop camera' : 'Scan student QR'}
    <span aria-hidden="true">⌗</span></button
  >{#if message}<p class="field-help" role="status">{message}</p>{/if}
</div>
