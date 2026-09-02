import { CadastralDigitizerElement } from './cadastral-digitizer.js';

if (!customElements.get('cadastral-digitizer')) {
  customElements.define('cadastral-digitizer', CadastralDigitizerElement);
}

export {
  CadastralDigitizerElement,
  type DigitizerMode,
  type DraftGeometry,
} from './cadastral-digitizer.js';
export {
  normalizeDigitizerConfig,
  type DigitizerConfig,
  type NormalizedDigitizerConfig,
} from './config.js';
export { GeometryHistory } from './editor/GeometryHistory.js';
export { replacePolygonVertex, type PolygonGeometry } from './editor/VertexEditor.js';
