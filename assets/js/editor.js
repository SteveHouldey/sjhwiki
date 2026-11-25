import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Image from '@tiptap/extension-image';
import { Table, TableKit } from '@tiptap/extension-table';
import CodeBlockLowlight from '@tiptap/extension-code-block-lowlight';
import { all, createLowlight } from 'lowlight';
import TextAlign from '@tiptap/extension-text-align';
import { BackgroundColor, Color, FontFamily, FontSize, TextStyle } from '@tiptap/extension-text-style';
import Subscript from '@tiptap/extension-subscript';
import Superscript from '@tiptap/extension-superscript';
import { FooterRow, mergeWithinSection, prepareTableContent, serializeTableContent, toggleFooter } from './table.js';

const container = document.querySelector('#visual-editor');
const form = document.querySelector('#wiki-page-form');
const textarea = form?.querySelector('textarea[name="body"]');

if (container && form && textarea) {
  const status = container.querySelector('#editor-status');
  const fileInput = container.querySelector('#editor-image');
  const saveButton = form.querySelector('button[type="submit"], button:not([type])');
  const toolbar = container.querySelector('.editor-toolbar');
  const linkPanel = container.querySelector('#editor-link-panel');
  const imagePanel = container.querySelector('#editor-image-panel');
  const tablePanel = container.querySelector('#editor-table-panel');
  const linkInput = container.querySelector('#editor-link-url');
  const imageInput = container.querySelector('#editor-image-url');
  const languageSelect = toolbar.querySelector('[data-command="codeLanguage"]');
  const lowlight = createLowlight(all);
  lowlight.listLanguages().filter((language) => language !== 'plaintext').sort().forEach((language) => {
    languageSelect.add(new Option(language, language));
  });
  const updateToolbar = (current) => {
    const inCode = current.isActive('codeBlock');
    const language = current.getAttributes('codeBlock').language || 'plaintext';
    if (inCode && ![...languageSelect.options].some((option) => option.value === language)) {
      languageSelect.add(new Option(language, language));
    }
    languageSelect.value = language;
    languageSelect.disabled = !inCode;
    toolbar.querySelectorAll('button[data-command]').forEach((button) => {
      const command = button.dataset.command;
      if (['bold', 'italic', 'underline', 'strike', 'subscript', 'superscript', 'bulletList',
        'orderedList', 'blockquote', 'code', 'codeBlock'].includes(command)) {
        button.setAttribute('aria-pressed', String(current.isActive(command)));
      }
    });
  };
  const editor = new Editor({
    element: container.querySelector('#editor-content'),
    extensions: [
      StarterKit.configure({ link: { openOnClick: false }, codeBlock: false }),
      CodeBlockLowlight.configure({ lowlight, defaultLanguage: 'plaintext' }),
      Image,
      TableKit.configure({ table: false, tableRow: false }),
      Table.configure({ resizable: true }),
      FooterRow,
      TextAlign.configure({ types: ['heading', 'paragraph'] }),
      TextStyle, Color, BackgroundColor, FontFamily, FontSize, Subscript, Superscript,
    ],
    content: prepareTableContent(textarea.value),
    editorProps: { attributes: { 'aria-label': 'Page content', role: 'textbox', 'aria-multiline': 'true' } },
    onUpdate: ({ editor: current }) => { textarea.value = serializeTableContent(current); updateToolbar(current); },
    onSelectionUpdate: ({ editor: current }) => updateToolbar(current),
  });
  textarea.value = serializeTableContent(editor);
  textarea.closest('.input').hidden = true;
  container.hidden = false;

  const safeUrl = (value, image = false) => {
    try {
      if (!value.trim()) return false;
      const url = new URL(value, window.location.href);
      return (image ? ['http:', 'https:'] : ['http:', 'https:', 'mailto:']).includes(url.protocol);
    } catch {
      return false;
    }
  };
  const report = (message) => { status.textContent = message; };
  const tableCommand = (command) => report(command() ? '' : 'Select a table first.');
  const commands = {
    bold: () => editor.chain().focus().toggleBold().run(),
    italic: () => editor.chain().focus().toggleItalic().run(),
    underline: () => editor.chain().focus().toggleUnderline().run(),
    strike: () => editor.chain().focus().toggleStrike().run(),
    subscript: () => editor.chain().focus().toggleSubscript().run(),
    superscript: () => editor.chain().focus().toggleSuperscript().run(),
    clear: () => editor.chain().focus().unsetAllMarks().clearNodes().run(),
    bulletList: () => editor.chain().focus().toggleBulletList().run(),
    orderedList: () => editor.chain().focus().toggleOrderedList().run(),
    blockquote: () => editor.chain().focus().toggleBlockquote().run(),
    code: () => editor.chain().focus().toggleCode().run(),
    codeBlock: () => editor.chain().focus().toggleCodeBlock().run(),
    horizontalRule: () => editor.chain().focus().setHorizontalRule().run(),
    left: () => editor.chain().focus().setTextAlign('left').run(),
    center: () => editor.chain().focus().setTextAlign('center').run(),
    right: () => editor.chain().focus().setTextAlign('right').run(),
    link: () => {
      imagePanel.hidden = true;
      tablePanel.hidden = true;
      linkInput.value = editor.getAttributes('link').href || '';
      linkPanel.hidden = false;
      linkInput.focus();
    },
    unlink: () => editor.chain().focus().extendMarkRange('link').unsetLink().run(),
    upload: () => fileInput.click(),
    image: () => {
      linkPanel.hidden = true;
      tablePanel.hidden = true;
      imageInput.value = '';
      imagePanel.hidden = false;
      imageInput.focus();
    },
    table: () => {
      linkPanel.hidden = true;
      imagePanel.hidden = true;
      tablePanel.hidden = false;
      container.querySelector('#editor-table-rows').focus();
    },
    addRow: () => {
      if (editor.getAttributes('tableRow').footer) return report('Select a body row to add a row above the footer.');
      tableCommand(() => editor.chain().focus().addRowAfter().run());
    },
    deleteRow: () => tableCommand(() => editor.chain().focus().deleteRow().run()),
    addColumn: () => tableCommand(() => editor.chain().focus().addColumnAfter().run()),
    deleteColumn: () => tableCommand(() => editor.chain().focus().deleteColumn().run()),
    headerRow: () => tableCommand(() => editor.chain().focus().toggleHeaderRow().run()),
    headerColumn: () => tableCommand(() => editor.chain().focus().toggleHeaderColumn().run()),
    footer: () => report(toggleFooter(editor)),
    mergeCells: () => report(mergeWithinSection(editor)),
    splitCell: () => report(editor.chain().focus().splitCell().run() ? '' : 'Select a merged table cell to split.'),
    deleteTable: () => tableCommand(() => editor.chain().focus().deleteTable().run()),
    undo: () => editor.chain().focus().undo().run(),
    redo: () => editor.chain().focus().redo().run(),
  };
  toolbar.addEventListener('mousedown', (event) => {
    if (event.target.closest('button')) event.preventDefault();
  });
  toolbar.addEventListener('click', (event) => {
    const button = event.target.closest('button[data-command]');
    if (button && commands[button.dataset.command]) commands[button.dataset.command]();
  });
  toolbar.addEventListener('change', (event) => {
    const value = event.target.value;
    switch (event.target.dataset.command) {
      case 'heading':
        if (value === '0') editor.chain().focus().setParagraph().run();
        else editor.chain().focus().toggleHeading({ level: Number(value) }).run();
        break;
      case 'fontFamily':
        if (value) editor.chain().focus().setFontFamily(value).run();
        else editor.chain().focus().unsetFontFamily().run();
        break;
      case 'fontSize':
        if (value) editor.chain().focus().setFontSize(value).run();
        else editor.chain().focus().unsetFontSize().run();
        break;
      case 'color': editor.chain().focus().setColor(value).run(); break;
      case 'backgroundColor': editor.chain().focus().setBackgroundColor(value).run(); break;
      case 'codeLanguage': editor.chain().focus().updateAttributes('codeBlock', { language: value }).run(); break;
    }
  });
  container.addEventListener('click', (event) => {
    const action = event.target.closest('[data-panel-action]')?.dataset.panelAction;
    if (action === 'cancel-link' || action === 'cancel-image' || action === 'cancel-table') {
      linkPanel.hidden = true;
      imagePanel.hidden = true;
      tablePanel.hidden = true;
      editor.commands.focus();
    } else if (action === 'apply-link') {
      const value = linkInput.value.trim();
      if (value && !safeUrl(value)) return report('Enter a valid http, https or mailto link.');
      if (value) editor.chain().focus().extendMarkRange('link').setLink({ href: value }).run();
      else editor.chain().focus().extendMarkRange('link').unsetLink().run();
      linkPanel.hidden = true;
      report('');
    } else if (action === 'apply-image') {
      const value = imageInput.value.trim();
      if (!safeUrl(value, true)) return report('Enter a valid http or https image URL.');
      editor.chain().focus().setImage({ src: value }).run();
      imagePanel.hidden = true;
      report('');
    } else if (action === 'insert-table') {
      const rows = Number(container.querySelector('#editor-table-rows').value);
      const cols = Number(container.querySelector('#editor-table-columns').value);
      if (!Number.isInteger(rows) || !Number.isInteger(cols) || rows < 1 || cols < 1 ||
          rows > 20 || cols > 20 || rows * cols > 200) {
        return report('Choose 1–20 rows and columns, with at most 200 cells.');
      }
      const withHeaderRow = container.querySelector('#editor-table-header').checked;
      if (!editor.chain().focus().insertTable({ rows, cols, withHeaderRow }).run()) {
        return report('A table cannot be inserted at the current position.');
      }
      tablePanel.hidden = true;
      report('');
    }
  });
  [linkInput, imageInput].forEach((input) => input.addEventListener('keydown', (event) => {
    if (event.key === 'Enter') {
      event.preventDefault();
      input.parentElement.querySelector('[data-panel-action^="apply-"]').click();
    } else if (event.key === 'Escape') {
      event.preventDefault();
      input.parentElement.querySelector('[data-panel-action^="cancel-"]').click();
    }
  }));
  fileInput.addEventListener('change', async () => {
    const file = fileInput.files[0];
    if (!file) return;
    if (file.size > 20 * 1024 * 1024) {
      report('Images must be no larger than 20 MiB.');
      fileInput.value = '';
      return;
    }
    const token = form.querySelector('input[name="_csrfToken"]')?.value;
    const data = new FormData();
    data.append('image', file);
    saveButton.disabled = true;
    report('Uploading image...');
    try {
      const response = await fetch(container.dataset.uploadUrl, {
        method: 'POST', body: data, headers: { 'X-CSRF-Token': token || '' },
      });
      if (!response.ok) {
        const result = response.headers.get('content-type')?.includes('application/json') ? await response.json() : null;
        throw new Error(result?.error || `Upload failed (HTTP ${response.status}). Check PHP upload limits.`);
      }
      const result = await response.json();
      editor.chain().focus().setImage({ src: result.url, alt: file.name }).run();
      report(result.synced ? 'Image uploaded.' : 'Image uploaded; Git synchronization is pending.');
    } catch (error) {
      report(error instanceof Error ? error.message : 'Image upload failed.');
    } finally {
      saveButton.disabled = false;
      fileInput.value = '';
    }
  });
  form.addEventListener('submit', (event) => {
    textarea.value = serializeTableContent(editor);
    if (new TextEncoder().encode(textarea.value).length > 60000) {
      event.preventDefault();
      report('Page content must be at most 60,000 bytes. Images are stored separately.');
    }
  });
}
