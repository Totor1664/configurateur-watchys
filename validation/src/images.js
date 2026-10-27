import { configurationId } from './selection.js?v=five-dials-1';
import { defaultSelection } from './catalog.js?v=five-dials-1';
export const referenceId=configurationId(defaultSelection);
// Only exact, validated full renders may be registered here. No recoloring.
export const renders = {};
export function imageFor(selection, localReference) { const id=configurationId(selection); return renders[id] || (id===referenceId ? localReference : null); }
