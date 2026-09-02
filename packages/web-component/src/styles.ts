import { css } from 'lit';

export const digitizerStyles = css`
  :host {
    --digitizer-bg: #dfe5e8;
    --digitizer-panel: #f6f8f9;
    --digitizer-ink: #172126;
    --digitizer-muted: #63727a;
    --digitizer-line: #aeb9be;
    --digitizer-active: #0b6477;
    --digitizer-accent: #b6602b;
    --digitizer-map: #e9edef;
    display: block;
    width: 100%;
    height: 100%;
    min-height: 520px;
    color: var(--digitizer-ink);
    font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    container-type: inline-size;
  }

  * { box-sizing: border-box; }

  .shell {
    display: grid;
    grid-template-columns: 54px minmax(0, 1fr) 260px;
    grid-template-rows: minmax(0, 1fr) 32px;
    width: 100%;
    height: 100%;
    min-height: 520px;
    overflow: hidden;
    border: 1px solid var(--digitizer-line);
    background: var(--digitizer-bg);
  }

  .tool-rail {
    grid-column: 1;
    grid-row: 1;
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 8px 6px;
    border-right: 1px solid var(--digitizer-line);
    background: #29363c;
  }

  .tool {
    width: 40px;
    height: 40px;
    border: 1px solid transparent;
    border-radius: 4px;
    background: transparent;
    color: #e6ecee;
    font: 600 11px/1 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    cursor: pointer;
  }

  .tool:hover { background: #36464e; }
  .tool[aria-pressed="true"] { border-color: #7fc3cf; background: var(--digitizer-active); }
  .tool:disabled { opacity: .35; cursor: default; }
  .tool:focus-visible, input:focus-visible, button:focus-visible { outline: 2px solid #7fc3cf; outline-offset: 1px; }

  .map-wrap {
    grid-column: 2;
    grid-row: 1;
    position: relative;
    min-width: 0;
    min-height: 0;
    background: var(--digitizer-map);
  }

  .map { position: absolute; inset: 0; overflow: hidden; touch-action: none; }
  .map .ol-viewport { position: relative; overflow: hidden; width: 100%; height: 100%; touch-action: none; }
  .map .ol-layer { position: absolute; inset: 0; }
  .map canvas { display: block; }

  .empty-hint {
    position: absolute;
    inset: 0;
    display: grid;
    place-items: center;
    pointer-events: none;
    color: var(--digitizer-muted);
    font-size: 13px;
    letter-spacing: .01em;
  }

  .inspector {
    grid-column: 3;
    grid-row: 1;
    padding: 14px;
    overflow: auto;
    border-left: 1px solid var(--digitizer-line);
    background: var(--digitizer-panel);
  }

  .eyebrow {
    margin: 0 0 4px;
    color: var(--digitizer-muted);
    font: 600 10px/1.3 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    text-transform: uppercase;
    letter-spacing: .12em;
  }

  h2 {
    margin: 0 0 18px;
    font-size: 17px;
    line-height: 1.2;
    font-weight: 650;
    overflow-wrap: anywhere;
  }

  .section { padding: 12px 0; border-top: 1px solid #d8dee1; }
  .row { display: grid; grid-template-columns: 1fr auto; gap: 8px; margin: 5px 0; font-size: 12px; }
  .row span:first-child { color: var(--digitizer-muted); }
  .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-variant-numeric: tabular-nums; }

  .vertex-form { display: grid; gap: 8px; }
  .vertex-form label { display: grid; gap: 4px; color: var(--digitizer-muted); font-size: 11px; }
  .vertex-form input {
    width: 100%;
    padding: 7px 8px;
    border: 1px solid var(--digitizer-line);
    border-radius: 3px;
    background: white;
    color: var(--digitizer-ink);
    font: 12px/1.2 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  }

  .apply {
    padding: 8px 10px;
    border: 0;
    border-radius: 3px;
    background: var(--digitizer-active);
    color: white;
    font-weight: 650;
    cursor: pointer;
  }

  .status {
    grid-column: 1 / 4;
    grid-row: 2;
    display: grid;
    grid-template-columns: minmax(210px, 1fr) auto auto auto;
    align-items: center;
    gap: 18px;
    padding: 0 10px;
    border-top: 1px solid var(--digitizer-line);
    background: #1e292e;
    color: #dfe7ea;
    font: 10px/1 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-variant-numeric: tabular-nums;
  }

  .snap-on { color: #8fd2bd; }
  .snap-off { color: #98a6ac; }

  @container (max-width: 760px) {
    .shell { grid-template-columns: 48px minmax(0, 1fr); grid-template-rows: minmax(360px, 1fr) auto 32px; }
    .tool-rail { grid-column: 1; grid-row: 1 / 3; }
    .map-wrap { grid-column: 2; grid-row: 1; }
    .inspector { grid-column: 2; grid-row: 2; border-left: 0; border-top: 1px solid var(--digitizer-line); max-height: 220px; }
    .status { grid-column: 1 / 3; grid-row: 3; grid-template-columns: 1fr auto; gap: 8px; }
    .status .optional { display: none; }
  }
`;
