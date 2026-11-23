'use strict';
/* Creates a WordPress import file (WXR) from the static site.
   Run "npm run build" first, then:  node tools/wp-export.js
   Output: wp/out/cdrwa-content.xml */
const fs = require('fs');
const path = require('path');

const ROOT = path.join(__dirname, '..');
const SRC = path.join(ROOT, 'src');
const DIST = path.join(ROOT, 'dist');
const OUT_FILE = path.join(ROOT, 'wp', 'out', 'cdrwa-content.xml');

const readJSON = (p) => JSON.parse(fs.readFileSync(p, 'utf8'));
const site = readJSON(path.join(SRC, 'data', 'site.json'));
const SITE_URL = site.url.replace(/\/$/, '');

/* ---------- small helpers ---------- */
const esc = (s) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
const cdata = (s) => '<![CDATA[' + String(s).replace(/\]\]>/g, ']]]]><![CDATA[>') + ']]>';
const stamp = () => new Date().toISOString().slice(0, 19).replace('T', ' ');
const rfc822 = (d) => new Date(d.replace(' ', 'T') + 'Z').toUTCString();

function sectionData(s) {
  let data = {};
  if (s.source) data = readJSON(path.join(SRC, 'data', s.source));
  return { ...data, ...(s.data || {}) };
}
const activeSections = (page) =>
  (page.sections || [])
    .filter((s) => s.enabled !== false)
    .sort((a, b) => (a.order ?? 0) - (b.order ?? 0));

/* ---------- sections taken from the built static page ---------- */
function distSections(pagePath) {
  const file = path.join(DIST, pagePath.replace(/^\//, ''), 'index.html');
  if (!fs.existsSync(file)) throw new Error('Built page not found: ' + file + ' (run npm run build)');
  const html = fs.readFileSync(file, 'utf8');
  const main = html.match(/<main id="main"[^>]*>([\s\S]*?)<\/main>/);
  if (!main) throw new Error('No <main> found in ' + file);
  return main[1].match(/<section\b[\s\S]*?<\/section>/g) || [];
}
function findFragment(fragments, id) {
  const re = new RegExp('^<section[^>]*\\bid="' + id + '"');
  return fragments.find((f) => re.test(f));
}
const htmlBlock = (html) => '<!-- wp:html -->\n' + html + '\n<!-- /wp:html -->';

/* ---------- native block builders (used for guide posts) ---------- */
const para = (text, cls) =>
  '<!-- wp:paragraph' + (cls ? ' {"className":"' + cls + '"}' : '') + ' -->\n' +
  '<p' + (cls ? ' class="' + cls + '"' : '') + '>' + esc(text) + '</p>\n<!-- /wp:paragraph -->';

const heading2 = (text, anchor) =>
  '<!-- wp:heading -->\n<h2 class="wp-block-heading"' + (anchor ? ' id="' + anchor + '"' : '') + '>' +
  esc(text) + '</h2>\n<!-- /wp:heading -->';

const list = (items) =>
  '<!-- wp:list -->\n<ul class="wp-block-list">' +
  items.map((i) => '<!-- wp:list-item -->\n<li>' + esc(i) + '</li>\n<!-- /wp:list-item -->').join('') +
  '</ul>\n<!-- /wp:list -->';

const toc = (secs) =>
  '<!-- wp:group {"className":"toc"} -->\n<div class="wp-block-group toc">' +
  '<!-- wp:paragraph {"className":"toc__title"} -->\n<p class="toc__title">📑 On this page</p>\n<!-- /wp:paragraph -->' +
  '<!-- wp:list {"ordered":true} -->\n<ol class="wp-block-list">' +
  secs.map((s) => '<!-- wp:list-item -->\n<li><a href="#' + s.anchor + '">' + esc(s.heading) + '</a></li>\n<!-- /wp:list-item -->').join('') +
  '</ol>\n<!-- /wp:list --></div>\n<!-- /wp:group -->';

const details = (q, a) =>
  '<!-- wp:details -->\n<details class="wp-block-details"><summary>' + esc(q) + '</summary>' +
  '<!-- wp:paragraph -->\n<p>' + esc(a) + '</p>\n<!-- /wp:paragraph --></details>\n<!-- /wp:details -->';

function richTextBlocks(d) {
  const out = [];
  if (d.updated) out.push(para('🕒 Updated ' + d.updated + (d.readTime ? ' · ⏱️ ' + d.readTime : ''), 'meta'));
  if (d.intro) out.push(para(d.intro, 'lead'));
  if (d.toc && d.sections) out.push(toc(d.sections));
  for (const s of d.sections || []) {
    out.push(heading2((s.icon ? s.icon + ' ' : '') + s.heading, s.anchor));
    (s.paragraphs || []).forEach((t) => out.push(para(t)));
    if (s.bullets && s.bullets.length) out.push(list(s.bullets));
    if (s.note) out.push(para('💡 ' + s.note, 'callout'));
  }
  return out;
}

/* ---------- structured data (FAQPage and Service only; Yoast does the rest) ---------- */
const ldBlock = (obj) =>
  '<!-- wp:html -->\n<script type="application/ld+json">' +
  JSON.stringify(obj).replace(/</g, '\\u003c') + '</script>\n<!-- /wp:html -->';

function schemaBlocks(page) {
  const out = [];
  const faq = [];
  for (const s of activeSections(page)) {
    if (s.type === 'faq') faq.push(...(sectionData(s).items || []));
  }
  if (faq.length) {
    out.push(ldBlock({
      '@context': 'https://schema.org',
      '@type': 'FAQPage',
      mainEntity: faq.map((i) => ({
        '@type': 'Question', name: i.q, acceptedAnswer: { '@type': 'Answer', text: i.a }
      }))
    }));
  }
  if (page.service) {
    out.push(ldBlock({
      '@context': 'https://schema.org',
      '@type': 'Service',
      name: page.service.name,
      serviceType: page.service.type,
      description: page.description,
      provider: { '@type': 'ProfessionalService', name: site.name, url: SITE_URL + '/' },
      areaServed: page.service.area || 'AU'
    }));
  }
  return out;
}

/* ---------- page and post content ---------- */
function pageContent(page) {
  const fragments = distSections(page.path);
  const out = [];
  for (const s of activeSections(page)) {
    const frag = findFragment(fragments, s.id);
    if (!frag) { console.warn('  [skipped] ' + page.path + ' section "' + s.id + '" not found in built page'); continue; }
    out.push(htmlBlock(frag));
  }
  out.push(...schemaBlocks(page));
  return out.join('\n\n');
}

function postContent(page) {
  const fragments = distSections(page.path);
  const out = [];
  for (const s of activeSections(page)) {
    if (s.type === 'page-hero' || s.type === 'guide-list') continue; /* template supplies these */
    if (s.type === 'rich-text') {
      out.push(...richTextBlocks(sectionData(s)));
    } else if (s.type === 'faq') {
      const d = sectionData(s);
      out.push(heading2('❓ ' + d.heading));
      (d.items || []).forEach((i) => out.push(details(i.q, i.a)));
    } else {
      const frag = findFragment(fragments, s.id);
      if (frag) out.push(htmlBlock(frag));
    }
  }
  out.push(...schemaBlocks(page));
  return out.join('\n\n');
}

/* ---------- WXR output ---------- */
const meta = (k, v) =>
  '\n    <wp:postmeta>\n      <wp:meta_key>' + cdata(k) + '</wp:meta_key>\n      <wp:meta_value>' +
  cdata(v) + '</wp:meta_value>\n    </wp:postmeta>';

function item(o) {
  return '\n  <item>\n' +
    '    <title>' + cdata(o.title) + '</title>\n' +
    '    <link>' + SITE_URL + o.path + '</link>\n' +
    '    <pubDate>' + rfc822(o.date) + '</pubDate>\n' +
    '    <dc:creator>' + cdata('admin') + '</dc:creator>\n' +
    '    <guid isPermaLink="false">' + SITE_URL + '/?p=' + o.id + '</guid>\n' +
    '    <description></description>\n' +
    '    <content:encoded>' + cdata(o.content) + '</content:encoded>\n' +
    '    <excerpt:encoded>' + cdata(o.excerpt || '') + '</excerpt:encoded>\n' +
    '    <wp:post_id>' + o.id + '</wp:post_id>\n' +
    '    <wp:post_date>' + cdata(o.date) + '</wp:post_date>\n' +
    '    <wp:post_date_gmt>' + cdata(o.date) + '</wp:post_date_gmt>\n' +
    '    <wp:post_modified>' + cdata(o.date) + '</wp:post_modified>\n' +
    '    <wp:post_modified_gmt>' + cdata(o.date) + '</wp:post_modified_gmt>\n' +
    '    <wp:comment_status>' + cdata('closed') + '</wp:comment_status>\n' +
    '    <wp:ping_status>' + cdata('closed') + '</wp:ping_status>\n' +
    '    <wp:post_name>' + cdata(o.slug) + '</wp:post_name>\n' +
    '    <wp:status>' + cdata('publish') + '</wp:status>\n' +
    '    <wp:post_parent>' + (o.parent || 0) + '</wp:post_parent>\n' +
    '    <wp:menu_order>' + (o.order || 0) + '</wp:menu_order>\n' +
    '    <wp:post_type>' + cdata(o.type) + '</wp:post_type>\n' +
    '    <wp:post_password>' + cdata('') + '</wp:post_password>\n' +
    '    <wp:is_sticky>0</wp:is_sticky>' +
    meta('_yoast_wpseo_title', o.seoTitle) +
    meta('_yoast_wpseo_metadesc', o.seoDesc) +
    '\n  </item>';
}

/* ---------- main ---------- */
const pagesDir = path.join(SRC, 'data', 'pages');
const all = fs.readdirSync(pagesDir)
  .filter((f) => f.endsWith('.json'))
  .map((f) => readJSON(path.join(pagesDir, f)))
  .filter((p) => p.path !== '/404.html')
  .sort((a, b) => a.path.length - b.path.length);

const now = stamp();
let nextId = 101;
const idByPath = {};
const pageList = all.filter((p) => !p.article);
const postList = all.filter((p) => p.article);

pageList.forEach((p) => { idByPath[p.path] = nextId++; });

function parentPathOf(p) {
  const parts = p.split('/').filter(Boolean);
  parts.pop();
  return parts.length ? '/' + parts.join('/') + '/' : null;
}
const lastCrumb = (p) => (p.breadcrumbs && p.breadcrumbs.length ? p.breadcrumbs[p.breadcrumbs.length - 1].name : 'Page');

const items = [];
let order = 0;
for (const p of pageList) {
  try {
    const isHome = p.path === '/';
    const isGuidesHub = p.path === '/guides/';
    const parentPath = parentPathOf(p.path);
    items.push(item({
      id: idByPath[p.path],
      type: 'page',
      title: isHome ? 'Home' : lastCrumb(p),
      slug: isHome ? 'home' : p.path.split('/').filter(Boolean).pop(),
      path: p.path,
      date: now,
      parent: parentPath && idByPath[parentPath] ? idByPath[parentPath] : 0,
      order: order++,
      content: isGuidesHub ? '' : pageContent(p),
      seoTitle: p.title,
      seoDesc: p.description
    }));
  } catch (err) {
    console.warn('  [failed] page ' + p.path + ': ' + err.message);
  }
}
for (const p of postList) {
  try {
    const hero = (p.sections || []).find((s) => s.type === 'page-hero');
    items.push(item({
      id: nextId++,
      type: 'post',
      title: lastCrumb(p),
      slug: p.path.split('/').filter(Boolean).pop(),
      path: p.path,
      date: (p.article.published || now.slice(0, 10)) + ' 09:00:00',
      content: postContent(p),
      excerpt: hero && hero.data ? hero.data.sub : '',
      seoTitle: p.title,
      seoDesc: p.description
    }));
  } catch (err) {
    console.warn('  [failed] post ' + p.path + ': ' + err.message);
  }
}

const xml =
  '<?xml version="1.0" encoding="UTF-8" ?>\n' +
  '<rss version="2.0"\n' +
  '  xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"\n' +
  '  xmlns:content="http://purl.org/rss/1.0/modules/content/"\n' +
  '  xmlns:wfw="http://wellformedweb.org/CommentAPI/"\n' +
  '  xmlns:dc="http://purl.org/dc/elements/1.1/"\n' +
  '  xmlns:wp="http://wordpress.org/export/1.2/">\n' +
  '<channel>\n' +
  '  <title>' + esc(site.name) + '</title>\n' +
  '  <link>' + SITE_URL + '</link>\n' +
  '  <description>' + esc(site.tagline) + '</description>\n' +
  '  <pubDate>' + rfc822(now) + '</pubDate>\n' +
  '  <language>en-AU</language>\n' +
  '  <wp:wxr_version>1.2</wp:wxr_version>\n' +
  '  <wp:base_site_url>' + SITE_URL + '</wp:base_site_url>\n' +
  '  <wp:base_blog_url>' + SITE_URL + '</wp:base_blog_url>\n' +
  '  <wp:author>\n' +
  '    <wp:author_id>1</wp:author_id>\n' +
  '    <wp:author_login>' + cdata('admin') + '</wp:author_login>\n' +
  '    <wp:author_email>' + cdata('admin@example.com') + '</wp:author_email>\n' +
  '    <wp:author_display_name>' + cdata('admin') + '</wp:author_display_name>\n' +
  '    <wp:author_first_name>' + cdata('') + '</wp:author_first_name>\n' +
  '    <wp:author_last_name>' + cdata('') + '</wp:author_last_name>\n' +
  '  </wp:author>' +
  items.join('') +
  '\n</channel>\n</rss>\n';

fs.mkdirSync(path.dirname(OUT_FILE), { recursive: true });
fs.writeFileSync(OUT_FILE, xml);
console.log('Created ' + OUT_FILE);
console.log('Pages: ' + pageList.length + ', Posts: ' + postList.length + ', items written: ' + items.length);