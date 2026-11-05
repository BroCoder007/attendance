<script>
  import { enhance } from '$app/forms';
  import { refreshAll } from '$app/navigation';
  import { onMount } from 'svelte';
  import Notice from '#lib/Notice.svelte';
  import { dateTime, shortTime } from '#lib/format.js';
  let { data, form } = $props();
  let clock = $state(Date.now());
  let refreshing = $state(false);
  onMount(() => {
    const timer = setInterval(() => (clock = Date.now()), 1000);
    return () => clearInterval(timer);
  });
  async function refresh() {
    refreshing = true;
    try {
      await refreshAll();
    } finally {
      refreshing = false;
    }
  }
</script>

<header class="page-heading">
  <p class="eyebrow">STUDENT DESK / {data.user.login}</p>
  <div class="heading-line">
    <h1>Here.<br /><em>And ready.</em></h1>
    <p>Your class. Your check-in.<br />Your record of showing up.</p>
  </div>
</header>
<Notice {form} />
<div class="student-grid">
  <section>
    <div class="section-heading">
      <h2><span>01</span>Check in</h2>
      <button
        type="button"
        class="text-button"
        onclick={refresh}
        disabled={refreshing}>{refreshing ? 'Refreshing…' : 'Refresh ↻'}</button
      >
    </div>
    {#each data.rooms as room}<article class="student-class">
        <span class:live={room.ends > clock} class="status"
          >{room.marked_at
            ? 'YOU’RE PRESENT'
            : room.ends > clock
              ? 'OPEN NOW'
              : 'WINDOW ENDED'}</span
        >
        <h3>{room.subject}</h3>
        <p class="muted">
          Window closes at {shortTime(room.ends)}{room.cidr
            ? ' · Classroom network required'
            : ''}
        </p>
        {#if room.marked_at}<p class="checked-in">
            <span aria-hidden="true">✓</span>Checked in at {shortTime(
              room.marked_at
            )}
          </p>{:else if room.ends > clock}<form
            class="checkin-form"
            method="POST"
            action="?/checkin"
            use:enhance
          >
            <input type="hidden" name="room" value={room.id} /><label
              >Six-digit class code<input
                name="code"
                inputmode="numeric"
                pattern={'[0-9]{6}'}
                required
                minlength="6"
                maxlength="6"
                autocomplete="off"
                placeholder="000000"
              /></label
            ><button class="primary"
              >I’m here <span aria-hidden="true">↗</span></button
            >
          </form>{/if}
      </article>{:else}<div class="empty">
        <span class="empty-symbol" aria-hidden="true">↳</span>
        <h3>Nothing open just yet.</h3>
        <p>
          Your teacher will open a check-in window when class begins. Refresh to
          check again.
        </p>
      </div>{/each}
  </section>
  <section class="qr-panel" aria-labelledby="qr-title">
    <p class="eyebrow">YOUR IN-PERSON PASS</p>
    <h2 id="qr-title">Show up.<br /><em>Show this.</em></h2>
    {#if clock < data.qrExpires}<img
        src={data.qrImage}
        width="240"
        height="240"
        alt="Your short-lived student check-in QR code"
      />
      <p>
        Show this code to your teacher.<br />Valid for {Math.min(
          120,
          Math.max(0, Math.ceil((data.qrExpires - clock) / 1000))
        )} seconds.
      </p>{:else}<div class="qr-expired">
        QR expired.<br />Refresh for a new pass.
      </div>{/if}<button
      type="button"
      class="secondary"
      onclick={refresh}
      disabled={refreshing}>Refresh my QR ↻</button
    >
    <details>
      <summary>QR text for manual scanning</summary><textarea
        class="qr-text"
        readonly
        value={data.qr}
        aria-label="Student QR text"></textarea>
    </details>
    <p class="field-help">
      Only share this with your teacher. They confirm your presence; the code
      alone does not check you in.
    </p>
  </section>
</div>
<section>
  <div class="section-heading">
    <h2><span>02</span>Your attendance</h2>
    <span class="muted">Latest 100 check-ins</span>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Class</th><th>Date & time</th><th>Check-in</th></tr></thead
      ><tbody
        >{#each data.history as record}<tr
            ><td><strong>{record.subject}</strong></td><td
              >{dateTime(record.marked_at)}</td
            ><td
              >{{ code: 'Class code', staff: 'Staff', qr: 'Student QR' }[
                record.method
              ]}</td
            ></tr
          >{:else}<tr
            ><td colspan="3" class="muted"
              >Your first check-in will appear here.</td
            ></tr
          >{/each}</tbody
      >
    </table>
  </div>
</section>
<footer class="page-footer">
  <span>ROLLCALL / MY ATTENDANCE</span><span>Good to have you here.</span>
</footer>
