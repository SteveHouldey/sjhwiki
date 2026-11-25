import { copyFile, mkdir, writeFile } from 'node:fs/promises';
import { build } from 'esbuild';
import hljs from 'highlight.js';

await mkdir('webroot/js', { recursive: true });
await mkdir('webroot/css', { recursive: true });
await mkdir('webroot/css/fonts', { recursive: true });
await copyFile('node_modules/bootstrap/dist/css/bootstrap.min.css', 'webroot/css/bootstrap.min.css');
await copyFile('node_modules/bootstrap-icons/font/bootstrap-icons.min.css', 'webroot/css/bootstrap-icons.min.css');
await copyFile('node_modules/bootstrap-icons/font/fonts/bootstrap-icons.woff', 'webroot/css/fonts/bootstrap-icons.woff');
await copyFile('node_modules/bootstrap-icons/font/fonts/bootstrap-icons.woff2', 'webroot/css/fonts/bootstrap-icons.woff2');
await copyFile('node_modules/highlight.js/styles/vs2015.min.css', 'webroot/css/highlight.min.css');
const languages = [...new Set(hljs.listLanguages().flatMap((name) =>
  [name, ...(hljs.getLanguage(name).aliases || [])].filter((value) => /^[a-z0-9_-]+$/i.test(value)),
))].sort();
await writeFile('resources/highlight-languages.json', `${JSON.stringify(languages)}\n`);
await build({
  entryPoints: { 'wiki-editor': 'assets/js/editor.js', 'wiki-highlight': 'assets/js/highlight.js' },
  outdir: 'webroot/js',
  bundle: true,
  minify: true,
  target: ['es2020'],
});
