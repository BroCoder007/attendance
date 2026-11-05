<script>
  import { enhance } from '$app/forms';
  import { refreshAll } from '$app/navigation';
  import { onMount } from 'svelte';
  import Notice from '#lib/Notice.svelte';
  import Scanner from '#lib/Scanner.svelte';
  import { dateTime, shortTime } from '#lib/format.js';
  let { data, form } = $props();
  let qr = $state('');
  let search = $state('');
  let clock = $state(Date.now());
  let refreshing = $state(false);
  onMount(() => {
    const clockTimer = setInterval(() => (clock = Date.now()), 1000);
    const refreshTimer = setInterval(async () => {
      if (!document.hidden && !refreshing) {
        refreshing = true;
        try {
          await refreshAll();
        } finally {
          refreshing = false;
        }
      }
    }, 15000);
    return () => {
      clearInterval(clockTimer);
      clearInterval(refreshTimer);
    };
  });
  let open = $derived(!data.room.closed && data.room.ends > clock);
  let codeSeconds = $derived(
    Math.min(
      120,
      Math.max(0, Math.ceil((data.room.code_expires - clock) / 1000))
    )
  );
  let present = $derived(new Set(data.records.map((r) => r.student_id)));
  let people = $derived(
    data.students
      .filter((s) => s.active || present.has(s.id))
      .filter((s) =>
        `${s.name} ${s.login}`.toLowerCase().includes(search.toLowerCase())
      )
  );
  let markable = $derived(
    data.students.filter((s) => s.active && !present.has(s.id))
  );
  let records = $derived(new Map(data.records.map((r) => [r.student_id, r])));
</script>

<header class="page-heading compact">
  <a class="back-link" href="/desk">← Back to your classroom</a>
  <p class="eyebrow">
    CLASS REGISTER / No. {String(data.room.id).padStart(3, '0')}
  </p>
  <h1 class="subject-title">{data.room.subject}</h1>
  <div class="room-meta">
    <span class:live={open} class="status">{open ? 'OPEN NOW' : 'CLOSED'}</span
    ><span
      >{dateTime(data.room.starts)} · closes {shortTime(data.room.ends)}</span
    >
  </div>
</header>
<Notice {form} />
<div class="room-grid">
  <section class="code-panel" aria-labelledby="code-title">
    <p class="eyebrow" id="code-title">THE CLASS CHECK-IN CODE</p>
    {#if open}<div
        class:expired={!codeSeconds}
        class="class-code"
        aria-label={codeSeconds
          ? `Class code ${data.room.code}`
          : 'Code expired'}
      >
        {codeSeconds ? data.room.code : '— — —'}
      </div>
      <p>
        {codeSeconds
          ? `Refreshes on request · valid for ${codeSeconds}s`
          : 'This code has expired. Generate a new one below.'}
      </p>
      <div class="button-row">
        <form method="POST" action="?/rotate" use:enhance>
          <button class="secondary">New code ↻</button>
        </form>
        <form method="POST" action="?/close" use:enhance>
          <button class="text-button">Close window</button>
        </form>
      </div>{:else}<h2 class="closed-title">Register<br /><em>closed.</em></h2>
      <p>Attendance is saved. Download the report whenever you need it.</p>{/if}
    <div class="code-foot">
      <span aria-hidden="true">↳</span>
      <p>
        {data.room.cidr
          ? `Student check-in is restricted to ${data.room.cidr}.`
          : 'Students can check in from any network.'} Staff can also mark students
        in person.
      </p>
    </div>
  </section>
  <section class="present-panel">
    <span class="folio">IN THE ROOM</span>
    <div class="present-number">
      {data.records.length.toString().padStart(2, '0')}<span>present</span>
    </div>
    <p>
      {data.students.filter((s) => s.active).length} students currently on your active
      roster
    </p>
    <a class="primary" href={`/export/${data.room.id}`}
      >Download CSV <span aria-hidden="true">↓</span></a
    >
    <p class="field-help">
      Includes current roster and recorded students. Times use UTC in the
      export.
    </p>
  </section>
</div>
{#if open}<section class="staff-checkin">
    <div>
      <p class="eyebrow">IN-PERSON CHECK-IN</p>
      <h2>A name.<br /><em>A quick check.</em></h2>
      <p class="muted">Use a student’s live QR or mark them manually.</p>
    </div>
    <div>
      <Scanner onscan={(value) => (qr = value)} />
      <form
        method="POST"
        action="?/mark"
        use:enhance={() =>
          async ({ update }) => {
            await update();
            qr = '';
          }}
      >
        <label
          >Student QR text<textarea
            name="qr"
            bind:value={qr}
            rows="2"
            maxlength="500"
            placeholder="Scan or paste the student’s current QR text"
          ></textarea></label
        ><label
          >Or select a student<select name="student" disabled={Boolean(qr)}
            ><option value="">Choose a student</option
            >{#each markable as student}<option value={student.id}
                >{student.name} · {student.login}</option
              >{/each}</select
          ></label
        ><button class="primary" disabled={!markable.length && !qr}
          >Mark present <span aria-hidden="true">✓</span></button
        >
      </form>
    </div>
  </section>{/if}
<section>
  <div class="section-heading">
    <h2><span>01</span>Class attendance</h2>
    <label class="search-label"
      ><span class="sr-only">Search attendance</span><input
        type="search"
        bind:value={search}
        placeholder="Find a name or roll number"
      /></label
    >
  </div>
  <p class="field-help">
    Updates every 15 seconds while this page is visible. “Not recorded” means no
    check-in has been saved for this window.
  </p>
  <div class="table-wrap">
    <table>
      <thead
        ><tr
          ><th>Student</th><th>Status</th><th>Checked in</th><th>Method</th></tr
        ></thead
      ><tbody
        >{#each people as student}{@const record = records.get(student.id)}<tr
            ><td
              ><strong>{student.name}</strong><span class="cell-sub"
                >{student.login}</span
              ></td
            ><td
              ><span class:live={Boolean(record)} class="status"
                >{record ? 'PRESENT' : 'NOT RECORDED'}</span
              ></td
            ><td>{record ? shortTime(record.marked_at) : '—'}</td><td
              >{record
                ? { code: 'Class code', staff: 'Staff', qr: 'Student QR' }[
                    record.method
                  ]
                : '—'}</td
            ></tr
          >{:else}<tr
            ><td colspan="4" class="muted"
              >No students to show. Add your students from the roster on your
              staff desk.</td
            ></tr
          >{/each}</tbody
      >
    </table>
  </div>
</section>
<footer class="page-footer">
  <span>ROLLCALL / CLASS {data.room.id}</span><span>One clear record.</span>
</footer>
