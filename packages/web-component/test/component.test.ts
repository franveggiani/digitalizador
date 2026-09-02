import { describe, expect, it } from 'vitest';
import '../src/index.js';
import { CadastralDigitizerElement } from '../src/cadastral-digitizer.js';

describe('<cadastral-digitizer>', () => {
  it('registers a framework-agnostic custom element exactly once', () => {
    expect(customElements.get('cadastral-digitizer')).toBe(CadastralDigitizerElement);
  });

  it('renders the drafting instrument shell in Shadow DOM', async () => {
    const element = document.createElement('cadastral-digitizer') as CadastralDigitizerElement;
    document.body.append(element);
    await element.updateComplete;

    expect(element.shadowRoot?.querySelector('[data-role="map"]')).not.toBeNull();
    expect(element.shadowRoot?.querySelector('[data-role="tool-rail"]')).not.toBeNull();
    expect(element.shadowRoot?.querySelector('[data-role="inspector"]')).not.toBeNull();
    expect(element.shadowRoot?.querySelector('[data-role="status"]')).not.toBeNull();

    element.remove();
  });
});
