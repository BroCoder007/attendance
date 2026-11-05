import { error, redirect } from '@sveltejs/kit';
import { ownRoom } from './store.js';
export function requireUser(locals, role) {
  if (!locals.user) redirect(303, '/');
  if (role && locals.user.role !== role)
    error(403, 'This page is not available to your account.');
  return locals.user;
}
export function requireRoom(locals, id) {
  const user = requireUser(locals, 'teacher');
  const room = ownRoom(Number(id), user.id);
  if (!room) error(404, 'Class not found.');
  return room;
}
