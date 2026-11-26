N4 NEW SECTION TYPE (add to the registry in H02, section 7). Same rules as N1 to N3.

image-slider   fields: alt(bool), heading(text; required), subheading(text),
               interval(number; milliseconds; minimum 1500; default 4000),
               items(list of: image:url, imageAlt:text, icon:emoji, title:text,
               text:text, href:url)
               Warning (not an error) when there are fewer than 5 items.
               "image" is optional; without it a placeholder tile with "icon" shows.
               If "image" is set, "imageAlt" is required.

Builder requirements:
  1. The template ends with a <script> tag whose URL includes the build id:
       /assets/js/sections/image-slider.js?v={{buildId}}
     So the PHP builder must pass "buildId" (the same value used for the CSS and JS
     links in the layout) into every section's view, exactly as the Node builder now does.
  2. Item field names must not clash with section field names: items use
     "imageAlt", never "alt" (alt is the section's background flag).
  3. The static HTML works without JavaScript. The script only adds the interactive
     behaviour and the controls.