<script>
  import { page } from '$app/state';
</script>

<div class="error-page">
  <p class="eyebrow">ROLLCALL / {page.status}</p>
  <h1>Let’s get you<br /><em>back on track.</em></h1>
  <p>{page.error?.message || 'Something went wrong. Please try again.'}</p>
  <a class="primary" href="/">Back to my desk ↗</a>
</div>
