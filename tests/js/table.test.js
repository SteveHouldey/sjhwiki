import assert from 'node:assert/strict';
import { test } from 'node:test';
import { JSDOM } from 'jsdom';

const dom = new JSDOM('<!doctype html><html><body></body></html>', { pretendToBeVisual: true });
for (const name of ['window', 'document', 'DOMParser', 'Node', 'Element', 'HTMLElement', 'MutationObserver']) {
  globalThis[name] = dom.window[name];
}
Object.defineProperty(globalThis, 'navigator', { value: dom.window.navigator, configurable: true });
globalThis.getComputedStyle = dom.window.getComputedStyle.bind(dom.window);
globalThis.requestAnimationFrame = dom.window.requestAnimationFrame.bind(dom.window);
globalThis.cancelAnimationFrame = dom.window.cancelAnimationFrame.bind(dom.window);

const { Editor } = await import('@tiptap/core');
const { default: StarterKit } = await import('@tiptap/starter-kit');
const { TableKit } = await import('@tiptap/extension-table');
const { CellSelection } = await import('@tiptap/pm/tables');
const {
  FooterRow, mergeWithinSection, prepareTableContent, serializeTableContent, toggleFooter,
} = await import('../../assets/js/table.js');

function createEditor(content) {
  return new Editor({
    element: document.createElement('div'),
    extensions: [StarterKit, TableKit.configure({ tableRow: false }), FooterRow],
    content: prepareTableContent(content),
  });
}

test('preserves footer rows, body order and column widths through editing', () => {
  const editor = createEditor(
    '<table><tfoot><tr><td colwidth="123"><p>Footer one</p></td></tr>' +
    '<tr><td colwidth="123"><p>Footer two</p></td></tr></tfoot>' +
    '<tbody><tr><th colwidth="123"><p>Header</p></th></tr></tbody></table>',
  );
  const result = new DOMParser().parseFromString(serializeTableContent(editor), 'text/html');
  assert.deepEqual([...result.querySelectorAll('tr')].map((row) => row.textContent),
    ['Header', 'Footer one', 'Footer two']);
  assert.equal(result.querySelectorAll('tfoot tr').length, 2);
  assert.equal(result.querySelector('th').getAttribute('colwidth'), '123');

  editor.commands.setTextSelection(3);
  assert.equal(toggleFooter(editor), '');
  const withoutFooter = new DOMParser().parseFromString(serializeTableContent(editor), 'text/html');
  assert.equal(withoutFooter.querySelector('tfoot'), null);
  assert.deepEqual([...withoutFooter.querySelectorAll('tr')].map((row) => row.textContent),
    ['Header', 'Footer one', 'Footer two']);
  editor.destroy();
});

test('rejects a footer on a single row or a table with vertically merged cells', () => {
  const oneRow = createEditor('<table><tbody><tr><td><p>Only</p></td></tr></tbody></table>');
  oneRow.commands.setTextSelection(3);
  assert.match(toggleFooter(oneRow), /another row/);
  oneRow.destroy();

  const merged = createEditor(
    '<table><tbody><tr><td rowspan="2"><p>Both</p></td><td><p>Top</p></td></tr>' +
    '<tr><td><p>Bottom</p></td></tr></tbody></table>',
  );
  merged.commands.setTextSelection(3);
  assert.match(toggleFooter(merged), /vertically merged/);
  merged.destroy();
});

test('prevents merging body cells with footer cells', () => {
  const editor = createEditor(
    '<table><tbody><tr><td><p>Body</p></td></tr></tbody>' +
    '<tfoot><tr><td><p>Footer</p></td></tr></tfoot></table>',
  );
  const footerCell = 2 + editor.state.doc.firstChild.firstChild.nodeSize;
  editor.view.dispatch(editor.state.tr.setSelection(CellSelection.create(editor.state.doc, 2, footerCell)));
  assert.match(mergeWithinSection(editor), /footer boundary/);
  const result = new DOMParser().parseFromString(serializeTableContent(editor), 'text/html');
  assert.equal(result.querySelector('tfoot').textContent, 'Footer');
  assert.equal(result.querySelector('tbody').textContent, 'Body');
  editor.destroy();
});

test('does not mark rows in a nested table as an outer footer', () => {
  const html = '<table><tfoot><tr><td><table><tbody><tr><td>Nested</td></tr></tbody></table></td></tr></tfoot></table>';
  const parsed = new DOMParser().parseFromString(html, 'text/html');
  const [outer, nested] = parsed.querySelectorAll('tr');
  const footerAttribute = FooterRow.config.addAttributes.call({ parent: () => ({}) }).footer;
  assert.equal(footerAttribute.parseHTML(outer), true);
  assert.equal(footerAttribute.parseHTML(nested), false);
});
