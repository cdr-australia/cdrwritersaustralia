N2 NEW SECTION TYPES (add to the registry in H02, section 7). Same rules as N1.

authority-badges  fields: alt(bool), heading(text; required),
                  items(list of: label:text required, href:url)
stats-strip       fields: alt(bool), heading(text), subheading(text),
                  items(list of: value:text required, label:text required)
testimonials      fields: alt(bool), heading(text; required), subheading(text),
                  items(list of: quote:textarea required, initials:text required,
                  name:text required, role:text)
case-studies      fields: alt(bool), heading(text; required), subheading(text),
                  items(list of: tag:text, title:text required,
                  challenge:textarea required, approach:textarea required,
                  outcome:textarea required, href:url, linkLabel:text)
expert-profiles   fields: alt(bool), heading(text; required), subheading(text),
                  items(list of: initials:text required, image:url, imageAlt:text,
                  name:text required, role:text required, credentials:text,
                  bio:textarea required, href:url, linkLabel:text)

Notes: the sample data is obviously fake placeholder text. No Review or
AggregateRating structured data is generated for testimonials.