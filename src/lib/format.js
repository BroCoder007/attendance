import { PUBLIC_TIME_ZONE } from '$app/env/public';
export const timeZone = PUBLIC_TIME_ZONE;
export const dateTime = (value) =>
  new Intl.DateTimeFormat('en-IN', {
    dateStyle: 'medium',
    timeStyle: 'short',
    timeZone
  }).format(new Date(value));
export const shortTime = (value) =>
  new Intl.DateTimeFormat('en-IN', {
    hour: '2-digit',
    minute: '2-digit',
    timeZone
  }).format(new Date(value));
