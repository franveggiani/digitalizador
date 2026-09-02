export type EditorMode = 'IDLE' | 'SELECT' | 'MODIFY';

export interface EditorState {
  readonly mode: EditorMode;
  readonly selectedParcelExternalId?: string;
}

export type EditorAction =
  | { readonly type: 'ENTER_SELECT' }
  | { readonly type: 'SELECT_PARCEL'; readonly externalId: string }
  | { readonly type: 'ENTER_MODIFY' };

export function createInitialEditorState(): EditorState {
  return { mode: 'IDLE' };
}

export function editorReducer(state: EditorState, action: EditorAction): EditorState {
  switch (action.type) {
    case 'ENTER_SELECT':
      return { ...state, mode: 'SELECT' };
    case 'SELECT_PARCEL':
      return { ...state, selectedParcelExternalId: action.externalId };
    case 'ENTER_MODIFY':
      if (!state.selectedParcelExternalId) {
        throw new Error('MODIFY_REQUIRES_SELECTION');
      }
      return { ...state, mode: 'MODIFY' };
  }
}
