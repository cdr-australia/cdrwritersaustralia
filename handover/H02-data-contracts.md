H02 DATA CONTRACTS AND SECTION REGISTRY (file 3 of 8)

These JSON formats already exist in a working front end. DO NOT CHANGE THEM.
The admin reads and writes exactly these shapes.

1. FILE KEYS
    Pages:     data/pages/<name>.json      file key "pages/<name>"
    Shared:    data/shared/<name>.json     file key "shared/<name>"
    Singles:   data/site.json, data/nav.json  file keys "site", "nav"
    The <name> of a page file is chosen when the page is created (a slug) and NEVER
    changes. The page's public "path" may change later (a 301 redirect is created).

2. PAGE FILE
    {
      "path": "/services/cdr-writing/",     required, unique across all pages
      "title": "...",                       required, aim <= 60 chars (warn)
      "description": "...",                 required, aim <= 160 chars (warn)
      "priority": "0.9",                    optional string, "0.1" to "1.0"
      "indexable": true,                    optional, false = left out of sitemap
      "noindex": false,                     optional, true = robots noindex meta
      "ogImage": "/assets/icons/og-image.png",   optional
      "service": { "name": "...", "type": "...", "area": "AU" },   optional
      "article": { "headline": "...", "published": "YYYY-MM-DD",
                   "modified": "YYYY-MM-DD" },                     optional
      "breadcrumbs": [ { "name": "Home", "path": "/" }, ... ],
      "sections": [ SECTION, ... ]
    }
    Special page: path "/404.html" is written as a file (404.html), not a folder.
    Normal path "/a/b/" is written as a/b/index.html. Path "/" is index.html.

3. SECTION OBJECT
    {
      "id": "hero",              required, unique within the page, pattern [a-z0-9-]+
      "type": "page-hero",       required, must exist in the registry
      "order": 1,                required integer, ascending = render order
      "enabled": true,           optional, false = skipped at render
      "source": "shared/faq.json",   optional, a shared file to load as base data
      "filter": { "tag": "Core" },   optional, keeps only items where item.tag == "Core"
      "excludeSelf": true,           optional, drops items whose href equals the page path
      "data": { ... }            optional, fields of the section type. If "source" is
                                 set, "data" is merged OVER the source (data wins).
    }
    Merge rule: view = source JSON, then override with data. filter and excludeSelf
    apply only to an "items" list.

4. SHARED FILES
    shared/services.json     heading, subheading, items[ icon, title, text, href, tag ]
                             tag is "Core" or "Use"
    shared/faq.json          heading, items[ q, a ]
    shared/guides.json       heading, subheading, items[ icon, title, text, href, read ]
    shared/authorities.json  heading, subheading, rows[ service, authority, full,
                             flag, country ]
    shared/cta.json          heading, text, primary{ label, href, icon },
                             secondary{ label, href, icon }

5. SITE FILE (site.json)
    name, altName, shortName, url, tagline, poweredBy{ name, url }, themeColor,
    logo, ogImage, email, phone, whatsapp{ label, number, url },
    address{ street, locality, region, postcode, country },
    offices[ flag, country, phone, head ], socials[ name, icon, url ], disclaimer

6. NAV FILE (nav.json)
    header[ label, href, icon ], cta{ label, href },
    bottom[ label, href, icon ],
    footer[ { title, links[ label, href ] } ]

7. SECTION REGISTRY (site/registry/sections.json, created in milestone B2)
    One JSON object keyed by section type. It drives every admin form.
    Entry shape:
        "faq": {
          "label": "FAQ", "icon": "?", "allowSource": true, "allowFilter": false,
          "fields": [ FIELD, ... ]
        }
    FIELD shape: { "key", "label", "type", "required", "max", "min", "of" }
    Field types:
        text      single line string
        textarea  multi-line string (no HTML allowed)
        emoji     a short string, normally one emoji
        url       a path starting with "/" or a full https:// or tel: or mailto: URL
        date      YYYY-MM-DD
        slug      [a-z0-9-]+
        bool      true or false
        list      array. "of" is either "text" (list of strings) or an array of FIELDs
                  (list of objects)
        object    nested object. "of" is an array of FIELDs
    Create the registry file with exactly these 12 types and fields:

    hero            fields: eyebrow(text), h1(text, required), sub(text, required),
                    badges(list of icon:emoji, text:text),
                    ctaPrimary(object: label, href:url, icon:emoji),
                    ctaSecondary(object: label, href:url, icon:emoji)
    page-hero       fields: icon(emoji), h1(text, required), sub(text),
                    badges(list of icon:emoji, text:text)
                    note: the breadcrumb comes from the page's breadcrumbs, not data
    services-grid   allowSource, allowFilter. fields: heading(required),
                    subheading, items(list of icon:emoji, title, text, href:url, tag)
    icon-grid       fields: alt(bool), heading(required), subheading,
                    items(list of icon:emoji, title, text:textarea)
    process-steps   fields: heading(required), subheading,
                    items(list of icon:emoji, title, text:textarea)
    content-block   fields: alt(bool), icon(emoji), heading(required),
                    paragraphs(list of textarea), bullets(list of text)
    rich-text       fields: updated(text), updatedISO(date), readTime(text),
                    intro(textarea), toc(bool),
                    sections(list of anchor:slug, icon:emoji, heading,
                    paragraphs(list of textarea), bullets(list of text),
                    note:textarea)
    cta-banner      allowSource. fields: heading(required), text(textarea),
                    primary(object: label, href:url, icon:emoji),
                    secondary(object: label, href:url, icon:emoji)
    authority-table allowSource. fields: heading(required), subheading,
                    rows(list of service, authority, full, flag, country)
    contact-cards   fields: heading(required), subheading
                    note: reads phone, email, address and offices from site.json
    faq             allowSource. fields: heading(required),
                    items(list of q:text required, a:textarea required)
                    note: items feed FAQPage structured data automatically
    guide-list      allowSource, allowFilter, allowExcludeSelf. fields: alt(bool),
                    heading(required), subheading,
                    items(list of icon:emoji, title, text:textarea, href:url, read)

8. VALIDATION RULES (PageValidator)
    ERRORS (block publish):
        path missing, not starting with "/", duplicated by another page, or using a
          reserved first segment: admin, assets, data, storage, vendor, sw.js,
          manifest.webmanifest, sitemap.xml, robots.txt, llms.txt
          (exception: the exact path "/404.html" is allowed)
        file name <name> not matching ^[a-z0-9]+(?:-[a-z0-9]+)*$
        title or description empty
        section id missing, invalid or duplicated; type not in the registry
        order not an integer
        source file does not exist; filter or excludeSelf used on a type that does
          not allow it
        required registry field empty; wrong field type; url field with a bad scheme
        zero or more than one ENABLED section of type "hero" or "page-hero"
          (every page needs exactly one H1)
        article.published or modified not a valid date
        breadcrumbs: last item's path must equal the page path
    WARNINGS (allow publish, show to the editor):
        title over 60 or description over 160 characters
        description under 70 characters
        an enabled section with no content in a required list
    No raw HTML is allowed in any text field. Strip or reject "<" and ">".

9. IDENTIFIERS
    Never invent new keys. Unknown keys found in existing JSON must be preserved
    unchanged on save (round-trip safe). Preserve key order where practical and use
    pretty-printed JSON (2-space indent, UTF-8, no escaped slashes, no escaped
    unicode: use JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).

END OF H02. Reply only: RECEIVED H02