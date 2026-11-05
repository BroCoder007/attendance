import { sveltekit } from '@sveltejs/kit/vite';
import adapter from '@sveltejs/adapter-node';
import { defineConfig, loadEnv } from 'vite';
export default defineConfig(({ mode }) => {
  const environment = loadEnv(mode, process.cwd(), '');
  const origin = process.env.ORIGIN || environment.ORIGIN || undefined;
  if (
    origin &&
    (!['http:', 'https:'].includes(new URL(origin).protocol) ||
      new URL(origin).origin !== origin)
  )
    throw new Error(
      'ORIGIN must be an exact http(s) origin without a trailing slash or path.'
    );
  return {
    plugins: [
      sveltekit({
        adapter: adapter(),
        paths: { origin },
        csp: {
          mode: 'auto',
          directives: {
            'default-src': ['self'],
            'img-src': ['self', 'data:'],
            'style-src': ['self', 'unsafe-inline'],
            'frame-ancestors': ['none'],
            'form-action': ['self'],
            'base-uri': ['self']
          }
        }
      })
    ],
    server: { host: '127.0.0.1' }
  };
});
