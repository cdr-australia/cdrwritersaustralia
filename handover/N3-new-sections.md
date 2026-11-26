N3 NEW SECTION TYPES (add to the registry in H02, section 7). Same rules as N1 and N2.

IMPORTANT CHANGE TO VALIDATION (H02, section 8): "hero-split" also provides the page's
H1. The rule becomes: exactly ONE enabled section of type hero, hero-split or
page-hero per page.

hero-split         fields: eyebrow(text), h1(text; required), sub(text; required),
                   badges(list of: icon:emoji, text:text),
                   ctaPrimary(object: label, href:url, icon:emoji),
                   ctaSecondary(object: label, href:url, icon:emoji),
                   image(url), imageAlt(text),
                   panel(list of: icon:emoji, title:text, text:text)
                   Note: "panel" is shown only when "image" is empty.
pricing-packages   fields: alt(bool), heading(text; required), subheading(text),
                   footnote(text),
                   items(list of: name:text required, badge:text, featured:bool,
                   price:text required, period:text, summary:text,
                   features(list of text), ctaLabel:text required,
                   ctaHref:url required)
                   Prices are plain text (any currency or "Request a quote").
                   No Offer or Product structured data is generated.
latest-guides      fields: alt(bool), heading(text; required), subheading(text),
                   viewAll(object: label, href:url),
                   items(list of: icon:emoji, title:text required, date:text,
                   read:text, text:textarea required, href:url required)
                   Note: items are entered by hand for now. A later change may fill
                   them from the newest blog posts.