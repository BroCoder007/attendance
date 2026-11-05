<script>
  let { form } = $props();
</script>

{#if form?.error}<p class="notice error" role="alert">{form.error}</p>{/if}
{#if form?.message}<p class="notice success" role="status">
    {form.message}
  </p>{/if}
