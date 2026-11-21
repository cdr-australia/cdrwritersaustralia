'use strict';
/* =========================================================
   CDR Writers Australia - static site builder
   Reads /src (data + templates), writes finished HTML to /dist
   PART 1: helpers + section rendering
   ========================================================= */
const fs = require('fs');
const path = require('path');
const Mustache = require('mustache');
/* escape only what HTML needs; keep "/" and "=" readable in URLs */
Mustache.escape = (s) =>
  String(s).replace(/[&<>"']/g, (c) =>
    ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

const ROOT = __dirname;
const SRC = path.join(ROOT, 'src');
const DIST = path.join(ROOT, 'dist');
const BUILD_ID = Date.now().toString(36);

/* ---------- small helpers ---------- */
const readText = (p) => fs.readFileSync(p, 'utf8');
const readJSON = (p) => JSON.parse(readText(p));
const ensureDir = (p) => fs.mkdirSync(p, { recursive: true });

function writeOut(rel, content) {
  const file = path.join(DIST, rel);
  ensureDir(path.dirname(file));
  fs.writeFileSync(file, content);
}

function copyDir(from, to) {
  if (!fs.existsSync(from)) return;
  ensureDir(to);
  for (const entry of fs.readdirSync(from, { withFileTypes: true })) {
    const s = path.join(from, entry.name);
    const d = path.join(to, entry.name);
    entry.isDirectory() ? copyDir(s, d) : fs.copyFileSync(s, d);
  }
}

/* ---------- partials (header, footer, ...) ---------- */
function loadPartials() {
  const dir = path.join(SRC, 'templates', 'partials');
  const out = {};
  for (const f of fs.readdirSync(dir)) {
    if (f.endsWith('.html')) out[path.basename(f, '.html')] = readText(path.join(dir, f));
  }
  return out;
}

/* ---------- section data: optional shared source + inline overrides ---------- */
function getSectionData(section, page) {
  let data = {};
  if (section.source) data = readJSON(path.join(SRC, 'data', section.source));
  data = { ...data, ...(section.data || {}) };
  if (Array.isArray(data.items)) {
    if (section.filter) {
      const [key, val] = Object.entries(section.filter)[0];
      data.items = data.items.filter((i) => i[key] === val);
    }
    if (section.excludeSelf && page) {
      data.items = data.items.filter((i) => i.href !== page.path);
    }
  }
  return data;
}
/* adds has_<key> = true/false next to every array, at any depth */
function addFlags(node) {
  if (Array.isArray(node)) { node.forEach(addFlags); return node; }
  if (node && typeof node === 'object') {
    for (const k of Object.keys(node)) {
      if (Array.isArray(node[k])) node['has_' + k] = node[k].length > 0;
      addFlags(node[k]);
    }
  }
  return node;
}
/* ---------- render ONE section; never throws ---------- */
function renderSection(section, ctx) {
  if (section.enabled === false) return '';
  try {
    const tplPath = path.join(SRC, 'templates', 'sections', section.type + '.html');
    const tpl = readText(tplPath);
    const view = { ...addFlags(getSectionData(section, ctx.page)), id: section.id, site: ctx.site, page: ctx.page };
    return Mustache.render(tpl, view, ctx.partials);
  } catch (err) {
    console.warn(`  [skipped] section "${section.id}" (${section.type}): ${err.message}`);
    return '';
  }
}

/* ===== END OF PART 1 - paste PART 2 directly below this line ===== */
/* =========================================================
   PART 2: JSON-LD, page assembly, SEO files, main()
   ========================================================= */

/* ---------- structured data (JSON-LD) ---------- */
function buildJsonLd(page, ctx) {
  const { site } = ctx;
  const url = site.url.replace(/\/$/, '');
  const pageUrl = url + page.path;
  const graph = [];

  graph.push({
    '@type': 'ProfessionalService',
    '@id': url + '/#organization',
    name: site.name,
    alternateName: site.altName,
    url: url + '/',
    logo: url + (site.logo || '/assets/icons/icon-512.png'),
    description: site.tagline,
    telephone: site.phone,
    email: site.email,
    address: site.address
      ? {
          '@type': 'PostalAddress',
          streetAddress: site.address.street,
          addressLocality: site.address.locality,
          addressRegion: site.address.region,
          postalCode: site.address.postcode,
          addressCountry: site.address.country
        }
      : undefined,
    areaServed: 'Worldwide',
    sameAs: (site.socials || []).map((s) => s.url)
  });

  graph.push({
    '@type': 'WebSite',
    '@id': url + '/#website',
    url: url + '/',
    name: site.name,
    publisher: { '@id': url + '/#organization' },
    inLanguage: 'en-AU'
  });

  graph.push({
    '@type': 'WebPage',
    '@id': pageUrl + '#webpage',
    url: pageUrl,
    name: page.title,
    description: page.description,
    isPartOf: { '@id': url + '/#website' },
    about: { '@id': url + '/#organization' },
    inLanguage: 'en-AU'
  });

  if (page.service) {
    graph.push({
      '@type': 'Service',
      '@id': pageUrl + '#service',
      name: page.service.name,
      serviceType: page.service.type,
      description: page.description,
      provider: { '@id': url + '/#organization' },
      areaServed: page.service.area || 'AU'
    });
  }

  if (page.article) {
    graph.push({
      '@type': 'Article',
      '@id': pageUrl + '#article',
      headline: page.article.headline || page.title,
      description: page.description,
      datePublished: page.article.published,
      dateModified: page.article.modified || page.article.published,
      author: { '@id': url + '/#organization' },
      publisher: { '@id': url + '/#organization' },
      mainEntityOfPage: { '@id': pageUrl + '#webpage' },
      image: url + (page.ogImage || site.ogImage || '/assets/icons/og-image.png')
    });
  }

  if (Array.isArray(page.breadcrumbs) && page.breadcrumbs.length) {
    graph.push({
      '@type': 'BreadcrumbList',
      itemListElement: page.breadcrumbs.map((b, i) => ({
        '@type': 'ListItem',
        position: i + 1,
        name: b.name,
        item: url + b.path
      }))
    });
  }

  /* FAQPage built automatically from any enabled "faq" section */
  const faqItems = [];
  for (const s of page.sections || []) {
    if (s.type === 'faq' && s.enabled !== false) {
      try {
        const d = getSectionData(s);
        (d.items || []).forEach((i) => faqItems.push(i));
      } catch (e) { /* ignore, section will be skipped at render too */ }
    }
  }
  if (faqItems.length) {
    graph.push({
      '@type': 'FAQPage',
      mainEntity: faqItems.map((i) => ({
        '@type': 'Question',
        name: i.q,
        acceptedAnswer: { '@type': 'Answer', text: i.a }
      }))
    });
  }

  return { '@context': 'https://schema.org', '@graph': graph };
}

/* ---------- assemble one full HTML page ---------- */
function buildPage(page, ctx) {
  const sections = (page.sections || [])
    .slice()
    .sort((a, b) => (a.order ?? 0) - (b.order ?? 0));

    /* crumbs: marks the last item so it renders as plain text, not a link */
  page.crumbs = (page.breadcrumbs || []).map((b, i, a) => ({ ...b, last: i === a.length - 1 }));
  const body = sections.map((s) => renderSection(s, { ...ctx, page })).join('\n');
  const base = ctx.site.url.replace(/\/$/, '');

  const view = {
    site: ctx.site,
    nav: ctx.nav,
    page,
    body,
    canonical: base + page.path,
    ogImage: base + (page.ogImage || ctx.site.ogImage || '/assets/icons/icon-512.png'),
    jsonld: JSON.stringify(buildJsonLd(page, ctx)),
    year: new Date().getFullYear(),
    buildId: BUILD_ID
  };

  return Mustache.render(ctx.layout, view, ctx.partials);
}

/* "/" -> index.html ; "/services/" -> services/index.html */
function outPathFor(pagePath) {
  if (pagePath.endsWith('.html')) return pagePath.replace(/^\//, '');
  const clean = pagePath.replace(/^\/|\/$/g, '');
  return clean ? path.join(clean, 'index.html') : 'index.html';
}

/* ---------- sitemap, robots, llms.txt ---------- */
function buildSeoFiles(pages, ctx) {
  const base = ctx.site.url.replace(/\/$/, '');
  const today = new Date().toISOString().slice(0, 10);

  const urls = pages
    .filter((p) => p.indexable !== false)
    .map(
      (p) =>
        `  <url>\n    <loc>${base}${p.path}</loc>\n    <lastmod>${today}</lastmod>\n    <priority>${p.priority || '0.7'}</priority>\n  </url>`
    )
    .join('\n');

  writeOut(
    'sitemap.xml',
    `<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n${urls}\n</urlset>\n`
  );

  writeOut('robots.txt', `User-agent: *\nAllow: /\n\nSitemap: ${base}/sitemap.xml\n`);

  const llms = [
    `# ${ctx.site.name}`,
    '',
    `> ${ctx.site.tagline}`,
    '',
    '## Pages',
    ...pages
      .filter((p) => p.indexable !== false)
      .map((p) => `- [${p.title}](${base}${p.path}): ${p.description}`),
    ''
  ].join('\n');
  writeOut('llms.txt', llms);
}

/* ---------- main ---------- */
function main() {
  console.log('Building CDR Writers Australia...');
  fs.rmSync(DIST, { recursive: true, force: true });
  ensureDir(DIST);

  const ctx = {
    site: readJSON(path.join(SRC, 'data', 'site.json')),
    nav: readJSON(path.join(SRC, 'data', 'nav.json')),
    partials: loadPartials(),
    layout: readText(path.join(SRC, 'templates', 'layout.html'))
  };

  /* each page file is isolated: one bad JSON never stops the others */
  const pagesDir = path.join(SRC, 'data', 'pages');
  const pages = [];
  for (const f of fs.readdirSync(pagesDir).filter((x) => x.endsWith('.json'))) {
    try {
      pages.push(readJSON(path.join(pagesDir, f)));
    } catch (err) {
      console.warn(`  [skipped] page file ${f}: ${err.message}`);
    }
  }

  for (const page of pages) {
    try {
      writeOut(outPathFor(page.path), buildPage(page, ctx));
      console.log(`  built ${page.path}`);
    } catch (err) {
      console.warn(`  [failed] page ${page.path}: ${err.message}`);
    }
  }

  buildSeoFiles(pages, ctx);

  copyDir(path.join(SRC, 'assets'), path.join(DIST, 'assets'));
  copyDir(path.join(SRC, 'static'), DIST);

  /* stamp the service worker with this build id so caches refresh */
  const swFile = path.join(DIST, 'sw.js');
  if (fs.existsSync(swFile)) {
    fs.writeFileSync(swFile, readText(swFile).replace(/__BUILD__/g, BUILD_ID));
  }

  console.log(`Done. Upload the contents of /dist to public_html.`);
}

main();