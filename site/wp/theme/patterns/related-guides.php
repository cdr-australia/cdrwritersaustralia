<?php
/**
 * Title: Related guides
 * Slug: cdrwa/related-guides
 * Description: A "keep reading" list of the newest guides, leaving out the guide being read.
 * Categories: cdrwa-sections
 * Keywords: related, guides, keep reading, posts, links
 * Viewport Width: 1200
 */
?>
<!-- wp:group {"tagName":"section","className":"section"} -->
<section class="wp-block-group section">
<!-- wp:group {"className":"container container--narrow"} -->
<div class="wp-block-group container container--narrow">

<!-- wp:heading {"className":"related__title"} -->
<h2 class="wp-block-heading related__title">Keep reading</h2>
<!-- /wp:heading -->

<!-- wp:query {"queryId":4,"query":{"perPage":3,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":false}} -->
<div class="wp-block-query">
<!-- wp:post-template {"className":"related cdrwa-related"} -->
<!-- wp:group {"className":"related__item"} -->
<div class="wp-block-group related__item">
<!-- wp:group {"className":"related__body"} -->
<div class="wp-block-group related__body">
<!-- wp:post-title {"level":3,"isLink":true,"className":"related__name"} /-->
<!-- wp:post-excerpt {"moreText":"","excerptLength":18,"className":"related__text"} /-->
</div>
<!-- /wp:group -->
<!-- wp:post-time-to-read {"displayAsRange":false,"className":"related__meta"} /-->
<!-- wp:paragraph {"className":"related__arrow"} -->
<p class="related__arrow">→</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
<!-- /wp:post-template -->
</div>
<!-- /wp:query -->

</div>
<!-- /wp:group -->
</section>
<!-- /wp:group -->