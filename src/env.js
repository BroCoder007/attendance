import { defineEnvVars } from '@sveltejs/kit/env';
export const variables = defineEnvVars({
  PUBLIC_TIME_ZONE: {
    public: true,
    schema: (value) => {
      const zone = value || 'UTC';
      try {
        new Intl.DateTimeFormat('en', { timeZone: zone });
      } catch {
        throw new Error('PUBLIC_TIME_ZONE must be a valid IANA timezone.');
      }
      return zone;
    }
  }
});
