<?php
/**
 * Title: Tabs
 * Slug: cdrwa/tabs
 * Description: Tabbed content. Each panel becomes a tab. The pattern loads its own script.
 * Categories: cdrwa-sections
 * Keywords: tabs, panels, switch, content
 * Viewport Width: 1200
 */
?>
<!-- wp:group {"tagName":"section","className":"section"} -->
<section class="wp-block-group section">
<!-- wp:group {"className":"container"} -->
<div class="wp-block-group container">

<!-- wp:group {"className":"section__head"} -->
<div class="wp-block-group section__head">
<!-- wp:heading {"className":"section__title"} -->
<h2 class="wp-block-heading section__title">Tabbed content</h2>
<!-- /wp:heading -->
<!-- wp:paragraph {"className":"section__sub"} -->
<p class="section__sub">Switch between panels. All the text stays on the page for search engines.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->

<!-- wp:group {"className":"tabs"} -->
<div class="wp-block-group tabs">

<!-- wp:group {"tagName":"article","className":"tabs__panel"} -->
<article class="wp-block-group tabs__panel">
<!-- wp:heading {"level":3,"className":"tabs__title"} -->
<h3 class="wp-block-heading tabs__title">🧭 First tab</h3>
<!-- /wp:heading -->
<!-- wp:group {"className":"tabs__body"} -->
<div class="wp-block-group tabs__body">
<!-- wp:paragraph {"className":"tabs__text"} -->
<p class="tabs__text">Sample text for the first tab. Replace this with your own content.</p>
<!-- /wp:paragraph -->
<!-- wp:list {"className":"checklist"} -->
<ul class="wp-block-list checklist">
<!-- wp:list-item -->
<li>First point goes here</li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li>Second point goes here</li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li>Third point goes here</li>
<!-- /wp:list-item -->
</ul>
<!-- /wp:list -->
<!-- wp:buttons -->
<div class="wp-block-buttons">
<!-- wp:button {"className":"cta-accent"} -->
<div class="wp-block-button cta-accent"><a class="wp-block-button__link wp-element-button" href="/contact/">Get a Free Quote</a></div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons -->
</div>
<!-- /wp:group -->
</article>
<!-- /wp:group -->

<!-- wp:group {"tagName":"article","className":"tabs__panel"} -->
<article class="wp-block-group tabs__panel">
<!-- wp:heading {"level":3,"className":"tabs__title"} -->
<h3 class="wp-block-heading tabs__title">🛠️ Second tab</h3>
<!-- /wp:heading -->
<!-- wp:group {"className":"tabs__body"} -->
<div class="wp-block-group tabs__body">
<!-- wp:paragraph {"className":"tabs__text"} -->
<p class="tabs__text">Sample text for the second tab. Replace this with your own content.</p>
<!-- /wp:paragraph -->
<!-- wp:list {"className":"checklist"} -->
<ul class="wp-block-list checklist">
<!-- wp:list-item -->
<li>First point goes here</li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li>Second point goes here</li>
<!-- /wp:list-item -->
</ul>
<!-- /wp:list -->
<!-- wp:buttons -->
<div class="wp-block-buttons">
<!-- wp:button {"className":"cta-accent"} -->
<div class="wp-block-button cta-accent"><a class="wp-block-button__link wp-element-button" href="/contact/">Get a Free Quote</a></div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons -->
</div>
<!-- /wp:group -->
</article>
<!-- /wp:group -->

<!-- wp:group {"tagName":"article","className":"tabs__panel"} -->
<article class="wp-block-group tabs__panel">
<!-- wp:heading {"level":3,"className":"tabs__title"} -->
<h3 class="wp-block-heading tabs__title">🌍 Third tab</h3>
<!-- /wp:heading -->
<!-- wp:group {"className":"tabs__body"} -->
<div class="wp-block-group tabs__body">
<!-- wp:paragraph {"className":"tabs__text"} -->
<p class="tabs__text">Sample text for the third tab. Replace this with your own content.</p>
<!-- /wp:paragraph -->
<!-- wp:list {"className":"checklist"} -->
<ul class="wp-block-list checklist">
<!-- wp:list-item -->
<li>First point goes here</li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li>Second point goes here</li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li>Third point goes here</li>
<!-- /wp:list-item -->
</ul>
<!-- /wp:list -->
<!-- wp:buttons -->
<div class="wp-block-buttons">
<!-- wp:button {"className":"cta-accent"} -->
<div class="wp-block-button cta-accent"><a class="wp-block-button__link wp-element-button" href="/contact/">Get a Free Quote</a></div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons -->
</div>
<!-- /wp:group -->
</article>
<!-- /wp:group -->

</div>
<!-- /wp:group -->

</div>
<!-- /wp:group -->

<!-- wp:html -->
<script src="<?php echo esc_url( wp_make_link_relative( get_theme_file_uri( 'assets/js/tabs.js' ) ) ); ?>" defer></script>
<!-- /wp:html -->

</section>
<!-- /wp:group -->