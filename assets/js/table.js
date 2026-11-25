import { TableRow } from '@tiptap/extension-table';
import { CellSelection } from '@tiptap/pm/tables';

export const FooterRow = TableRow.extend({
  addAttributes() {
    return {
      ...this.parent?.(),
      footer: {
        default: false,
        parseHTML: (element) => element.parentElement?.tagName === 'TFOOT',
        renderHTML: (attributes) => attributes.footer ? { class: 'wiki-table-footer' } : {},
      },
    };
  },
});

export function prepareTableContent(html) {
  const document = new DOMParser().parseFromString(html, 'text/html');
  document.querySelectorAll('table').forEach((table) => {
    const footer = table.querySelector(':scope > tfoot');
    if (footer) table.append(footer);
  });
  return document.body.innerHTML;
}

export function serializeTableContent(editor) {
  const document = new DOMParser().parseFromString(editor.getHTML(), 'text/html');
  document.querySelectorAll('table').forEach((table) => {
    const rows = [...table.querySelectorAll(':scope > tbody > tr.wiki-table-footer, :scope > tr.wiki-table-footer')];
    if (!rows.length) return;
    const footer = document.createElement('tfoot');
    rows.forEach((row) => {
      row.classList.remove('wiki-table-footer');
      if (!row.className) row.removeAttribute('class');
      footer.append(row);
    });
    table.append(footer);
  });
  return document.body.innerHTML;
}

export function toggleFooter(editor) {
  const { $from } = editor.state.selection;
  let depth = $from.depth;
  while (depth > 0 && $from.node(depth).type.name !== 'table') depth--;
  if (!depth) return 'Select a table first.';
  const table = $from.node(depth);
  let hasFooter = false;
  table.forEach((row) => { if (row.attrs.footer) hasFooter = true; });
  if (table.childCount < 2 && !hasFooter) return 'Add another row before marking a footer.';
  let hasRowspan = false;
  table.forEach((row) => row.forEach((cell) => {
    if (cell.attrs.rowspan > 1) hasRowspan = true;
  }));
  if (hasRowspan) return 'Split vertically merged cells before changing the footer.';
  editor.chain().focus().command(({ tr, dispatch }) => {
    if (dispatch) {
      let position = $from.before(depth) + 1;
      table.forEach((row, _offset, index) => {
        if ((hasFooter && row.attrs.footer) || (!hasFooter && index === table.childCount - 1)) {
          tr.setNodeMarkup(position, undefined, { ...row.attrs, footer: !hasFooter });
        }
        position += row.nodeSize;
      });
    }
    return true;
  }).run();
  return '';
}

export function mergeWithinSection(editor) {
  const selection = editor.state.selection;
  if (selection instanceof CellSelection) {
    const sections = new Set();
    selection.forEachCell((_cell, position) => {
      const $cell = editor.state.doc.resolve(position);
      for (let depth = $cell.depth; depth > 0; depth--) {
        if ($cell.node(depth).type.name === 'tableRow') {
          sections.add(Boolean($cell.node(depth).attrs.footer));
          break;
        }
      }
    });
    if (sections.size > 1) return 'Cells cannot be merged across the footer boundary.';
  }
  return editor.chain().focus().mergeCells().run() ? '' : 'Select adjacent table cells to merge.';
}
