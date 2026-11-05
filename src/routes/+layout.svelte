<script>
  import '../app.css';
  import '@fontsource-variable/dm-sans';
  import '@fontsource/instrument-serif/latin-400.css';
  import '@fontsource/instrument-serif/latin-400-italic.css';
  import { page } from '$app/state';
  import { timeZone } from '#lib/format.js';
  let { data, children } = $props();
</script>

<svelte:head
  ><title>Rollcall — the classroom ledger</title><meta
    name="description"
    content="A considered space for classroom attendance. Open a window, check in and keep a clear record."
  /></svelte:head
>
<a class="skip" href="#main">Skip to content</a>
{#if data.user}
  <div class="workspace">
    <aside class="sidebar">
      <a
        class="brand brand-light"
        href={data.user.role === 'teacher' ? '/desk' : '/student'}
        aria-label="Rollcall home"
        ><span class="brand-mark">r<span>·</span></span>rollcall</a
      >
      <p class="side-caption">THE CLASSROOM LEDGER</p>
      <nav aria-label="Main navigation">
        <a
          class:current={page.url.pathname === '/desk' ||
            page.url.pathname === '/student'}
          href={data.user.role === 'teacher' ? '/desk' : '/student'}
          ><span>01</span>{data.user.role === 'teacher'
            ? 'Your classroom'
            : 'My attendance'}<span aria-hidden="true">↗</span></a
        >
        {#if data.user.role === 'teacher'}<a href="/desk#roster"
            ><span>02</span>Student roster<span aria-hidden="true">↗</span></a
          >{/if}
      </nav>
      <div class="side-bottom">
        <span class="small-label"
          >SIGNED IN AS {data.user.role === 'teacher'
            ? 'STAFF'
            : 'STUDENT'}</span
        ><strong>{data.user.name}</strong><span class="account-login"
          >{data.user.login}</span
        >
        <form method="POST" action="/logout">
          <button class="signout"
            >Sign out <span aria-hidden="true">↗</span></button
          >
        </form>
      </div>
    </aside>
    <main id="main" class="main-content">
      {@render children()}
      <p class="timezone-note">
        Times shown in {timeZone}. CSV exports use UTC.
      </p>
    </main>
  </div>
{:else}
  <main id="main">{@render children()}</main>
{/if}
