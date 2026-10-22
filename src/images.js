import { configurationId } from './selection.js';
import { defaultSelection } from './catalog.js';
export const referenceId=configurationId(defaultSelection);
// Only exact, validated full renders may be registered here. No recoloring.
export const renders = {};
export function imageFor(selection, localReference) { const id=configurationId(selection); return renders[id] || (id===referenceId ? localReference : null); }
