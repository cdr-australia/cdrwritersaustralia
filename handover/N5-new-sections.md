N5 NEW SECTION TYPES (add to the registry in H02, section 7). Same rules as N1 to N4.

tabs            fields: alt(bool), heading(text; required), subheading(text),
                items(list of: icon:emoji, title:text required, text:textarea required,
                bullets(list of text), ctaLabel:text, ctaHref:url)
                Minimum 2 items (error below 2). The tab buttons are built from the
                panel headings by JavaScript. All panels are in the HTML.
enquiry-form    fields: alt(bool), heading(text; required), subheading(text),
                endpoint(text; required; a URL starting with "/" or "https://",
                or the word "demo"), submitLabel(text; required),
                topics(list of text; min 1), successTitle(text; required),
                successText(textarea; required)
                The template uses {{site.whatsapp.url}} and {{site.email}}.
related-guides  fields: alt(bool), heading(text; required),
                items(list of: icon:emoji, title:text required, text:text,
                href:url required, read:text)

Both tabs and enquiry-form end with a <script> tag that includes the build id
(see N4, requirement 1).

FUTURE BACKEND WORK (milestone B6, not now): the enquiry endpoint.
  POST, multipart/form-data. Fields: name, email, phone, country, topic, message,
  consent, website. "website" is a hidden trap field; when it is filled in, store
  nothing and answer 200 as if it worked.
  Answers: 200 with JSON on success; 422 with field messages on bad input; 429 when
  rate limited. Allow cross-origin only from the site's own domain.
  The static pages cannot carry a Laravel CSRF token, so this route must be
  excluded from CSRF and protected with rate limiting and the trap field.
  Store only the fields above, send a notification email, and apply the privacy
  policy retention rules. The owner will give details when B6 starts.