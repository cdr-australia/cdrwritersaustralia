<?php
/**
 * Title: Latest guides
 * Slug: cdrwa/latest-guides
 * Description: Your three newest guides as cards, with a link to all guides. Updates itself.
 * Categories: cdrwa-sections
 * Keywords: guides, blog, latest, posts, news
 * Viewport Width: 1200
 */
?>
<!-- wp:group {"tagName":"section","className":"section section--alt"} -->
<section class="wp-block-group section section--alt">
<!-- wp:group {"className":"container"} -->
<div class="wp-block-group container">

<!-- wp:group {"className":"section__head section__head--split"} -->
<div class="wp-block-group section__head section__head--split">
<!-- wp:group -->
<div class="wp-block-group">
<!-- wp:heading {"className":"section__title"} -->
<h2 class="wp-block-heading section__title">Latest guides</h2>
<!-- /wp:heading -->
<!-- wp:paragraph {"className":"section__sub"} -->
<p class="section__sub">Our newest guides for engineers preparing a CDR.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
<!-- wp:paragraph {"className":"section__link"} -->
<p class="section__link"><a href="/guides/">View all guides →</a></p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->

<!-- wp:query {"queryId":3,"query":{"perPage":3,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":false}} -->
<div class="wp-block-query">
<!-- wp:post-template {"className":"guide-grid"} -->
<!-- wp:group {"className":"card"} -->
<div class="wp-block-group card">
<!-- wp:post-time-to-read {"displayAsRange":false,"className":"chip chip--tag"} /-->
<!-- wp:post-title {"level":3,"isLink":true,"className":"card__title"} /-->
<!-- wp:post-date {"className":"card__meta"} /-->
<!-- wp:post-excerpt {"moreText":"Read guide →","className":"card__text"} /-->
</div>
<!-- /wp:group -->
<!-- /wp:post-template -->
</div>
<!-- /wp:query -->

</div>
<!-- /wp:group -->
</section>
<!-- /wp:group -->