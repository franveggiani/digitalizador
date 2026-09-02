import { describe, expect, it } from 'vitest';
import { createInitialEditorState, editorReducer } from '../src/state.js';

describe('editor state machine', () => {
  it('starts idle and enters select mode explicitly', () => {
    const initial = createInitialEditorState();
    expect(initial.mode).toBe('IDLE');

    const selected = editorReducer(initial, { type: 'ENTER_SELECT' });
    expect(selected.mode).toBe('SELECT');
  });

  it('does not enter modify without a selected parcel', () => {
    const initial = createInitialEditorState();
    expect(() => editorReducer(initial, { type: 'ENTER_MODIFY' })).toThrowError('MODIFY_REQUIRES_SELECTION');
  });
});
