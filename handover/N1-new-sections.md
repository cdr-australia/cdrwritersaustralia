N1 NEW SECTION TYPES (add to the registry in H02, section 7)
Five new types. Their Node templates already exist and use only logic-less Mustache
with has_* flags. The PHP builder must render them with no template changes.

New field type: select
    FIELD shape: { "key", "label", "type": "select", "options": [ ... ], "required" }
    Value must be one of the options.

callout           fields: alt(bool), variant(select: info, tip, warning; required),
                  icon(emoji), title(text), text(textarea; required)
key-takeaways     fields: alt(bool), icon(emoji), heading(text; required),
                  items(list of text; min 1)
feature-split     fields: alt(bool), heading(text), subheading(text),
                  items(list of: icon:emoji, image:url, imageAlt:text,
                  title:text required, text:textarea required, href:url,
                  linkLabel:text)
comparison-table  fields: alt(bool), heading(text; required), subheading(text),
                  columns(list of: name:text required, featured:bool),
                  rows(list of: label:text required, values(list of text))
                  Rule: every row's "values" must have exactly as many entries as
                  "columns". This is an ERROR in PageValidator.
timeline          fields: alt(bool), heading(text; required), subheading(text),
                  items(list of: label:text, title:text required,
                  text:textarea required)

Template features the builder must support (the parity test will confirm):
    inverted sections {{^image}}, sections over a string value such as {{#heading}},
    and {{id}} resolving from the parent context inside those sections.

A page may be marked "indexable": false and "noindex": true (already in H02). The
page "design-library" uses both; it is an internal reference page.