'use strict';
/* Builds a WordPress import file with ONE page that shows every section pattern,
   in the same order as the Node design-library page, for side-by-side checks.
   Run:  node tools/wp-showcase.js
   Output: wp/out/cdrwa-showcase.xml   (import into LOCAL WordPress only) */
const fs = require('fs');
const path = require('path');

const ROOT = path.join(__dirname, '..');
const PATTERNS = path.join(ROOT, 'wp', 'theme', 'patterns');
const OUT_FILE = path.join(ROOT, 'wp', 'out', 'cdrwa-showcase.xml');
const site = JSON.parse(fs.readFileSync(path.join(ROOT, 'src', 'data', 'site.json'), 'utf8'));
const SITE_URL = site.url.replace(/\/$/, '');

/* Same order as src/data/pages/design-library.json. Add new pattern file names here. */
const ORDER = [
  'hero-split',
  'callout-info', 'callout-tip', 'callout-warning',
  'key-takeaways', 'feature-split', 'comparison-table', 'timeline',
  'authority-badges', 'stats-strip', 'testimonials', 'case-studies', 'expert-profiles',
  'pricing-packages', 'latest-guides', 'image-slider',
  'tabs', 'enquiry-form', 'related-guides'
];

const cdata = (s) => '<![CDATA[' + String(s).replace(/\]\]>/g, ']]]]><![CDATA[>') + ']]>';
const now = new Date().toISOString().slice(0, 19).replace('T', ' ');
const rfc822 = (d) => new Date(d.replace(' ', 'T') + 'Z').toUTCString();

/* patterns contain two kinds of small PHP address; turn them into the real paths */
function resolvePhp(body, name) {
  const out = body
    .replace(
      /<\?php\s+echo\s+esc_url\(\s*wp_make_link_relative\(\s*get_theme_file_uri\(\s*'([^']+)'\s*\)\s*\)\s*\);\s*\?>/g,
      '/wp-content/themes/cdrwa/$1'
    )
    .replace(
      /<\?php\s+echo\s+esc_url\(\s*wp_make_link_relative\(\s*rest_url\(\s*'([^']+)'\s*\)\s*\)\s*\);\s*\?>/g,
      '/wp-json/$1'
    );
  if (/<\?php/.test(out)) console.warn('  [warning] ' + name + ' still contains PHP the showcase script cannot resolve');
  return out;
}

const blocks = [];
for (const name of ORDER) {
  const file = path.join(PATTERNS, name + '.php');
  if (!fs.existsSync(file)) {
    console.warn('  [skipped] pattern file not found: ' + name);
    continue;
  }
  const text = fs.readFileSync(file, 'utf8').replace(/^\uFEFF/, '');
  const body = text.replace(/^<\?php[\s\S]*?\?>\s*/, '').trim();
  blocks.push(resolvePhp(body, name));
}
const content = blocks.join('\n\n');

const meta = (k, v) =>
  '\n    <wp:postmeta>\n      <wp:meta_key>' + cdata(k) + '</wp:meta_key>\n      <wp:meta_value>' +
  cdata(v) + '</wp:meta_value>\n    </wp:postmeta>';

const item =
  '\n  <item>\n' +
  '    <title>' + cdata('Pattern Showcase') + '</title>\n' +
  '    <link>' + SITE_URL + '/pattern-showcase/</link>\n' +
  '    <pubDate>' + rfc822(now) + '</pubDate>\n' +
  '    <dc:creator>' + cdata('admin') + '</dc:creator>\n' +
  '    <guid isPermaLink="false">' + SITE_URL + '/?p=9001</guid>\n' +
  '    <description></description>\n' +
  '    <content:encoded>' + cdata(content) + '</content:encoded>\n' +
  '    <excerpt:encoded>' + cdata('') + '</excerpt:encoded>\n' +
  '    <wp:post_id>9001</wp:post_id>\n' +
  '    <wp:post_date>' + cdata(now) + '</wp:post_date>\n' +
  '    <wp:post_date_gmt>' + cdata(now) + '</wp:post_date_gmt>\n' +
  '    <wp:post_modified>' + cdata(now) + '</wp:post_modified>\n' +
  '    <wp:post_modified_gmt>' + cdata(now) + '</wp:post_modified_gmt>\n' +
  '    <wp:comment_status>' + cdata('closed') + '</wp:comment_status>\n' +
  '    <wp:ping_status>' + cdata('closed') + '</wp:ping_status>\n' +
  '    <wp:post_name>' + cdata('pattern-showcase') + '</wp:post_name>\n' +
  '    <wp:status>' + cdata('publish') + '</wp:status>\n' +
  '    <wp:post_parent>0</wp:post_parent>\n' +
  '    <wp:menu_order>0</wp:menu_order>\n' +
  '    <wp:post_type>' + cdata('page') + '</wp:post_type>\n' +
  '    <wp:post_password>' + cdata('') + '</wp:post_password>\n' +
  '    <wp:is_sticky>0</wp:is_sticky>' +
  meta('_yoast_wpseo_meta-robots-noindex', '1') +
  '\n  </item>';

const xml =
  '<?xml version="1.0" encoding="UTF-8" ?>\n' +
  '<rss version="2.0"\n' +
  '  xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"\n' +
  '  xmlns:content="http://purl.org/rss/1.0/modules/content/"\n' +
  '  xmlns:wfw="http://wellformedweb.org/CommentAPI/"\n' +
  '  xmlns:dc="http://purl.org/dc/elements/1.1/"\n' +
  '  xmlns:wp="http://wordpress.org/export/1.2/">\n' +
  '<channel>\n' +
  '  <title>Pattern Showcase</title>\n' +
  '  <link>' + SITE_URL + '</link>\n' +
  '  <description>Local test page</description>\n' +
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
  item +
  '\n</channel>\n</rss>\n';

fs.mkdirSync(path.dirname(OUT_FILE), { recursive: true });
fs.writeFileSync(OUT_FILE, xml);
console.log('Created ' + OUT_FILE);
console.log('Patterns included: ' + blocks.length);