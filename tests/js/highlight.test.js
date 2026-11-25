import assert from 'node:assert/strict';
import { test } from 'node:test';
import { JSDOM } from 'jsdom';

const dom = new JSDOM('<!doctype html><html><body></body></html>', { url: 'https://wiki.example/' });
globalThis.document = dom.window.document;
Object.defineProperty(globalThis, 'navigator', { value: dom.window.navigator, configurable: true });
const { enhanceCodeBlocks } = await import('../../assets/js/highlight.js');

function render(body) {
  document.body.innerHTML = body;
  enhanceCodeBlocks();
}

test('highlights a chosen language and copies exact code without line numbers', async () => {
  const text = 'const answer = 42;\n\nconsole.log("<tag>");\n';
  document.body.innerHTML = '<article class="markdown-body"><pre><code class="language-javascript"></code></pre></article>';
  document.querySelector('code').textContent = text;
  let copied;
  Object.defineProperty(navigator, 'clipboard', {
    configurable: true,
    value: { writeText: async (value) => { copied = value; } },
  });
  enhanceCodeBlocks();
  enhanceCodeBlocks();
  const block = document.querySelector('.code-block');
  assert.equal(document.querySelectorAll('.code-block').length, 1);
  assert.match(block.querySelector('.code-block-header').textContent, /JavaScript/);
  assert.equal(block.querySelector('.code-block-lines').textContent, '1\n2\n3\n4');
  assert.equal(block.querySelector('.code-block-lines').getAttribute('aria-hidden'), 'true');
  assert.equal(block.querySelector('code').textContent, text);
  assert.equal(block.querySelector('.hljs-keyword').textContent, 'const');
  assert.equal(block.querySelector('code tag'), null);
  block.querySelector('button').click();
  await new Promise(setImmediate);
  assert.equal(copied, text);
  assert.equal(block.querySelector('[role=status]').textContent, 'Code copied.');
  assert.equal(block.querySelector('button').disabled, false);
});

test('handles plaintext and clipboard failures, including unavailable clipboard', async () => {
  render('<div class="markdown-body"><pre><code class="language-plaintext">Hello\nworld</code></pre></div>');
  const block = document.querySelector('.code-block');
  assert.match(block.querySelector('.code-block-header').textContent, /Plain text/i);
  assert.equal(block.querySelector('.code-block-lines').textContent, '1\n2');
  Object.defineProperty(navigator, 'clipboard', {
    configurable: true,
    value: { writeText: async () => { throw new Error('Permission denied'); } },
  });
  block.querySelector('button').click();
  await new Promise(setImmediate);
  assert.match(block.querySelector('[role=status]').textContent, /Copy failed/);
  assert.equal(block.querySelector('button').disabled, false);
  Object.defineProperty(navigator, 'clipboard', { configurable: true, value: undefined });
  block.querySelector('button').click();
  await new Promise(setImmediate);
  assert.match(block.querySelector('[role=status]').textContent, /copy it manually/);
});

test('enhances revision previews but not revision source and labels legacy aliases', () => {
  render('<div class="revision"><div class="markdown-body">' +
    '<pre><code class="language-js">const x = 1;</code></pre><pre><code>x = 1;</code></pre></div>' +
    '<pre>Original source</pre></div>');
  assert.equal(document.querySelectorAll('.code-block').length, 2);
  assert.match(document.querySelector('.code-block-header').textContent, /JavaScript/);
  assert.match(document.querySelectorAll('.code-block-header')[1].textContent, /Auto:|Plain text/);
  assert.equal(document.querySelector('.revision > pre').textContent, 'Original source');
});
