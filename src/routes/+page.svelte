<script>
  import { enhance } from '$app/forms';
  let { form } = $props();
  let pending = $state(false);
</script>

<div class="landing">
  <section class="welcome">
    <a class="brand" href="/" aria-label="Rollcall home"
      ><span class="brand-mark">r<span>·</span></span>rollcall</a
    >
    <div class="welcome-copy">
      <p class="eyebrow">A LITTLE LESS ADMIN. A LITTLE MORE CLASS.</p>
      <h1>Every seat.<br /><em>Accounted for.</em></h1>
      <p class="intro">
        Attendance, without the interruption.<br />One clear place to check in
        and carry on.
      </p>
    </div>
    <div class="ledger-art" aria-hidden="true">
      <div><span>THE DAILY REGISTER</span><span>No. 001</span></div>
      <p><span>Arrive.</span><b>01</b></p>
      <p><span>Check in.</span><b>02</b></p>
      <p><span>Get on with it.</span><b class="tick">✓</b></p>
    </div>
    <footer>MADE FOR THE CLASSROOM <span>↗</span></footer>
  </section>
  <section class="login-panel" aria-labelledby="signin-title">
    <div class="login-card">
      <span class="folio">01 / WELCOME BACK</span>
      <h2 id="signin-title">Take your place.</h2>
      <p>Staff and students sign in here.</p>
      {#if form?.error}<p class="notice error" role="alert">
          {form.error}
        </p>{/if}
      <form
        method="POST"
        use:enhance={() => {
          pending = true;
          return async ({ update }) => {
            await update();
            pending = false;
          };
        }}
      >
        <label
          >Email or roll number<input
            name="login"
            autocomplete="username"
            required
            maxlength="100"
            placeholder="Your account ID"
          /></label
        >
        <label
          >Password<input
            name="password"
            type="password"
            autocomplete="current-password"
            required
            maxlength="128"
            placeholder="Your password"
          /></label
        >
        <button class="primary wide" disabled={pending}
          >{pending ? 'Signing in…' : 'Sign in'}<span aria-hidden="true">↗</span
          ></button
        >
      </form>
      <p class="login-help">
        New here or need a reset?<br />Your teacher manages your student
        account.
      </p>
      <div class="login-note">
        <span aria-hidden="true">↳</span>
        <p>
          Check in with a class code, or show your student QR to your teacher.
          Your attendance stays in your account.
        </p>
      </div>
    </div>
    <span class="login-bottom">GOOD TO SEE YOU HERE.</span>
  </section>
</div>
