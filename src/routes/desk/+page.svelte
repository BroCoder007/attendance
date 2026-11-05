<script>
  import { enhance } from '$app/forms';
  import { dateTime } from '#lib/format.js';
  import Notice from '#lib/Notice.svelte';
  let { data, form } = $props();
  let search = $state('');
  let clock = $state(Date.now());
  import { onMount } from 'svelte';
  onMount(() => {
    const timer = setInterval(() => (clock = Date.now()), 15000);
    return () => clearInterval(timer);
  });
  const open = (room) => !room.closed && room.ends > clock;
  let active = $derived(data.rooms.filter(open));
  let students = $derived(
    data.students.filter((student) =>
      `${student.name} ${student.login}`
        .toLowerCase()
        .includes(search.toLowerCase())
    )
  );
</script>

<header class="page-heading">
  <p class="eyebrow">STAFF DESK / YOUR CLASSROOM</p>
  <div class="heading-line">
    <h1>Ready when<br /><em>you are.</em></h1>
    <p>Open the register.<br />Make room for learning.</p>
  </div>
</header>
<Notice {form} />
<div class="summary-strip">
  <div>
    <strong>{active.length.toString().padStart(2, '0')}</strong><span
      >Open windows</span
    >
  </div>
  <div>
    <strong
      >{data.students
        .filter((s) => s.active)
        .length.toString()
        .padStart(2, '0')}</strong
    ><span>Students in your roster</span>
  </div>
  <div>
    <strong>{data.rooms.length.toString().padStart(2, '0')}</strong><span
      >Classes recorded</span
    >
  </div>
</div>
<div class="desk-grid">
  <section aria-labelledby="register-title">
    <div class="section-heading">
      <h2 id="register-title"><span>01</span>The register</h2>
      <span class="muted">{data.rooms.length} classes</span>
    </div>
    {#if !data.rooms.length}<div class="empty">
        <span class="empty-symbol" aria-hidden="true">↳</span>
        <h3>A fresh register.</h3>
        <p>
          Start your first attendance window. Your class records will live here.
        </p>
      </div>{/if}
    <div class="session-list">
      {#each data.rooms as room}<a class="session-row" href={`/room/${room.id}`}
          ><div>
            <span class:live={open(room)} class="status"
              >{open(room) ? 'OPEN NOW' : 'CLOSED'}</span
            >
            <h3>{room.subject}</h3>
            <p>{dateTime(room.starts)}</p>
          </div>
          <div class="row-end">
            <strong>{room.present}<small> present</small></strong><span
              class="arrow"
              aria-hidden="true">↗</span
            >
          </div></a
        >{/each}
    </div>
  </section>
  <section class="create-panel" aria-labelledby="new-title">
    <p class="eyebrow">LET’S BEGIN</p>
    <h2 id="new-title">Open a<br /><em>class window.</em></h2>
    <form method="POST" action="?/create" use:enhance>
      <label
        >Class / subject<input
          name="subject"
          required
          maxlength="120"
          placeholder="e.g. Data structures · Section A"
        /></label
      ><label
        >Check-in window<select name="minutes"
          ><option value="5">5 minutes</option><option value="10" selected
            >10 minutes</option
          ><option value="15">15 minutes</option><option value="30"
            >30 minutes</option
          ><option value="60">60 minutes</option></select
        ></label
      ><label
        >Classroom network <span class="optional">Optional</span><input
          name="cidr"
          placeholder="e.g. 192.168.1.0/24"
          maxlength="80"
        /><span class="field-help"
          >Leave blank to allow any network. A network restriction supports
          attendance checks; it cannot prove physical presence.</span
        ></label
      ><button class="primary wide"
        >Open attendance <span aria-hidden="true">↗</span></button
      >
    </form>
  </section>
</div>
<section id="roster" class="roster-section" aria-labelledby="roster-title">
  <div class="section-heading">
    <h2 id="roster-title"><span>02</span>Your people</h2>
    <label class="search-label"
      ><span class="sr-only">Search roster</span><input
        type="search"
        bind:value={search}
        placeholder="Find a name or roll number"
      /></label
    >
  </div>
  <div class="table-wrap">
    <table>
      <thead
        ><tr><th>Name / roll number</th><th>Account</th><th>Manage</th></tr
        ></thead
      ><tbody
        >{#each students as student}<tr
            ><td
              ><strong>{student.name}</strong><span class="cell-sub"
                >{student.login}</span
              ></td
            ><td
              ><span class:live={student.active} class="status"
                >{student.active ? 'ACTIVE' : 'PAUSED'}</span
              ></td
            ><td
              ><details>
                <summary>Account options</summary>
                <div class="account-actions">
                  <form method="POST" action="?/manage" use:enhance>
                    <input type="hidden" name="id" value={student.id} /><input
                      type="hidden"
                      name="operation"
                      value="password"
                    /><label
                      >New password for {student.name}<input
                        type="password"
                        name="password"
                        required
                        minlength="12"
                        maxlength="128"
                        autocomplete="new-password"
                        placeholder="At least 12 characters"
                      /></label
                    ><button class="secondary">Reset password</button>
                  </form>
                  <form method="POST" action="?/manage" use:enhance>
                    <input type="hidden" name="id" value={student.id} /><input
                      type="hidden"
                      name="operation"
                      value="toggle"
                    /><button class="text-button"
                      >{student.active
                        ? 'Pause account'
                        : 'Reactivate account'}</button
                    >
                  </form>
                </div>
              </details></td
            ></tr
          >{:else}<tr
            ><td colspan="3" class="muted"
              >{search
                ? 'No students match that search.'
                : 'Your roster is empty. Add a student below.'}</td
            ></tr
          >{/each}</tbody
      >
    </table>
  </div>
  <details class="add-student">
    <summary>Add a student <span aria-hidden="true">+</span></summary>
    <form class="inline-form" method="POST" action="?/student" use:enhance>
      <label
        >Full name<input
          name="name"
          required
          maxlength="100"
          autocomplete="name"
        /></label
      ><label
        >Roll number<input
          name="login"
          required
          minlength="2"
          maxlength="100"
          autocomplete="off"
        /></label
      ><label
        >Initial password<input
          type="password"
          name="password"
          minlength="12"
          maxlength="128"
          autocomplete="new-password"
          required
        /></label
      ><button class="primary">Add to roster ↗</button>
    </form>
    <p class="field-help">
      Use a unique password of at least 12 characters and share it privately.
      Pausing an account keeps its past attendance.
    </p>
  </details>
</section>
<footer class="page-footer">
  <span>ROLLCALL / THE CLASSROOM LEDGER</span><span>One clear record.</span>
</footer>
