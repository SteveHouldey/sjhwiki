import hljs from 'highlight.js';

hljs.configure({
  languages: ['bash', 'css', 'html', 'javascript', 'json', 'php', 'python', 'sql', 'typescript', 'xml'],
  ignoreUnescapedHTML: true,
});

export function enhanceCodeBlocks(root = document) {
  root.querySelectorAll('.markdown-body pre > code').forEach((code) => {
    const pre = code.parentElement;
    if (pre.parentElement.classList.contains('code-block')) return;

    const languageClass = [...code.classList].find((name) => name.startsWith('language-'));
    const selectedLanguage = languageClass?.slice('language-'.length);
    hljs.highlightElement(code);
    const language = selectedLanguage || code.result?.language;
    const languageName = language && hljs.getLanguage(language)?.name;

    const wrapper = document.createElement('div');
    wrapper.className = 'code-block';
    const header = document.createElement('div');
    header.className = 'code-block-header';
    const label = document.createElement('span');
    label.textContent = languageName ? `${selectedLanguage ? '' : 'Auto: '}${languageName}` : 'Plain text';
    const status = document.createElement('span');
    status.className = 'code-block-status';
    status.setAttribute('role', 'status');
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'code-block-copy';
    button.textContent = 'Copy code';
    button.addEventListener('click', async () => {
      button.disabled = true;
      try {
        if (!navigator.clipboard?.writeText) throw new Error('Clipboard unavailable');
        await navigator.clipboard.writeText(code.textContent);
        status.textContent = 'Code copied.';
      } catch {
        status.textContent = 'Copy failed. Select the code and copy it manually.';
      } finally {
        button.disabled = false;
      }
    });
    header.append(label, status, button);
    const numbers = document.createElement('span');
    numbers.className = 'code-block-lines';
    numbers.setAttribute('aria-hidden', 'true');
    numbers.textContent = Array.from({ length: code.textContent.split('\n').length }, (_, index) => index + 1).join('\n');
    pre.before(wrapper);
    wrapper.append(header, pre);
    pre.prepend(numbers);
  });
}

enhanceCodeBlocks();
