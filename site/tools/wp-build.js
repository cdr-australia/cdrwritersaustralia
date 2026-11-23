'use strict';
/* Builds the WordPress theme from the static site.
   Usage:
     node tools/wp-build.js "C:\path\to\wordpress\wp-content\themes\cdrwa"
   The target folder name must be "cdrwa". Run "npm run build" first. */
const fs = require('fs');
const path = require('path');

const ROOT = path.join(__dirname, '..');
const DIST = path.join(ROOT, 'dist');
const THEME_SRC = path.join(ROOT, 'wp', 'theme');
const OUT = path.join(ROOT, 'wp', 'out', 'cdrwa');
const ASSETS = path.join(ROOT, 'src', 'assets');

function pick(html, re, label) {
  const m = html.match(re);
  if (!m) throw new Error('Could not find ' + label + ' in dist/index.html');
  return m[0].trim();
}
const wrap = (html) => '<!-- wp:html -->\n' + html + '\n<!-- /wp:html -->\n';
const mk = (p) => fs.mkdirSync(p, { recursive: true });

const indexFile = path.join(DIST, 'index.html');
if (!fs.existsSync(indexFile)) {
  throw new Error('dist/index.html not found. Run "npm run build" first.');
}
const html = fs.readFileSync(indexFile, 'utf8');

/* 1. fresh output with the hand-written theme files */
fs.rmSync(OUT, { recursive: true, force: true });
mk(OUT);
fs.cpSync(THEME_SRC, OUT, { recursive: true });

/* 2. CSS, JS and icons from the static site */
mk(path.join(OUT, 'assets', 'css'));
for (const f of ['tokens', 'base', 'app-shell', 'sections']) {
  fs.copyFileSync(path.join(ASSETS, 'css', f + '.css'), path.join(OUT, 'assets', 'css', f + '.css'));
}
mk(path.join(OUT, 'assets', 'js'));
fs.copyFileSync(path.join(ASSETS, 'js', 'nav.js'), path.join(OUT, 'assets', 'js', 'nav.js'));
fs.copyFileSync(path.join(ASSETS, 'js', 'sections', 'faq.js'), path.join(OUT, 'assets', 'js', 'faq.js'));
if (fs.existsSync(path.join(ASSETS, 'icons'))) {
  fs.cpSync(path.join(ASSETS, 'icons'), path.join(OUT, 'assets', 'icons'), { recursive: true });
}

/* 3. template parts taken from the built homepage */
const skip = pick(html, /<a class="skip-link"[\s\S]*?<\/a>/, 'skip link');
const header = pick(html, /<header class="app-bar">[\s\S]*?<\/header>/, 'header');
const drawer = pick(html, /<div class="drawer-overlay"[\s\S]*?<\/aside>/, 'drawer');
const footer = pick(html, /<footer class="site-footer">[\s\S]*?<\/footer>/, 'footer');
const bottom = pick(html, /<nav class="bottom-nav"[\s\S]*?<\/nav>/, 'bottom navigation');
const fab = pick(html, /<a class="fab"[\s\S]*?<\/a>/, 'floating button');
const snack = pick(html, /<div class="snackbar"[\s\S]*?<\/div>/, 'snackbar');

mk(path.join(OUT, 'parts'));
const parts = {
  header: skip + '\n' + header,
  drawer: drawer,
  footer: footer,
  'bottom-nav': bottom + '\n' + fab + '\n' + snack
};
for (const [name, markup] of Object.entries(parts)) {
  fs.writeFileSync(path.join(OUT, 'parts', name + '.html'), wrap(markup));
}
console.log('Theme built in ' + OUT);

/* 4. optional: install into WordPress */
const target = process.argv[2];
if (target) {
  if (path.basename(target) !== 'cdrwa') {
    throw new Error('The target folder must be named "cdrwa": ' + target);
  }
  fs.cpSync(OUT, target, { recursive: true, force: true });
  console.log('Installed to ' + target);
}