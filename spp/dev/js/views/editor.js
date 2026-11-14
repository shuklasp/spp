/**
 * EditorView Component
 * 
 * Replaced by native SPPCoder (CoderView) to eliminate external CDN dependencies (Monaco from cdnjs).
 * Preserves backwards compatibility for any callers importing ./editor.js.
 */
import CoderView from './coder.js';

export default class EditorView extends CoderView {}
