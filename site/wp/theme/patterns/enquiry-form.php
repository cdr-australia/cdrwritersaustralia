<?php
/**
 * Title: Enquiry form
 * Slug: cdrwa/enquiry-form
 * Description: A custom enquiry form with checks, a spam trap and a thank-you message. It loads its own script.
 * Categories: cdrwa-sections
 * Keywords: form, contact, enquiry, quote, message
 * Viewport Width: 1200
 */
?>
<!-- wp:html -->
<section class="section section--alt">
  <div class="container container--narrow">
    <header class="section__head">
      <h2 class="section__title">Tell us about your goal</h2>
      <p class="section__sub">Share a few details and we will get back to you.</p>
    </header>

    <form class="enquiry" method="post" action="<?php echo esc_url( wp_make_link_relative( rest_url( 'cdrwa/v1/enquiry' ) ) ); ?>" data-endpoint="<?php echo esc_url( wp_make_link_relative( rest_url( 'cdrwa/v1/enquiry' ) ) ); ?>">
      <div class="enquiry__fields">
        <div class="enquiry__row">
          <div class="field">
            <label for="enquiry-name">Full name <span class="req" aria-hidden="true">*</span></label>
            <input id="enquiry-name" name="name" type="text" autocomplete="name" required>
          </div>
          <div class="field">
            <label for="enquiry-email">Email <span class="req" aria-hidden="true">*</span></label>
            <input id="enquiry-email" name="email" type="email" autocomplete="email" required>
          </div>
        </div>
        <div class="enquiry__row">
          <div class="field">
            <label for="enquiry-phone">Phone or WhatsApp</label>
            <input id="enquiry-phone" name="phone" type="tel" autocomplete="tel">
          </div>
          <div class="field">
            <label for="enquiry-country">Country</label>
            <input id="enquiry-country" name="country" type="text" autocomplete="country-name">
          </div>
        </div>
        <div class="field">
          <label for="enquiry-topic">I need help with <span class="req" aria-hidden="true">*</span></label>
          <select id="enquiry-topic" name="topic" required>
            <option value="">Choose one</option>
            <option>CDR writing</option>
            <option>CDR review</option>
            <option>CDR optimization</option>
            <option>Visa consultation</option>
            <option>Career advice</option>
            <option>Something else</option>
          </select>
        </div>
        <div class="field">
          <label for="enquiry-message">Your message <span class="req" aria-hidden="true">*</span></label>
          <textarea id="enquiry-message" name="message" rows="5" required></textarea>
        </div>
        <div class="field field--hp" aria-hidden="true">
          <label for="enquiry-website">Leave this field empty</label>
          <input id="enquiry-website" name="website" type="text" tabindex="-1" autocomplete="off">
        </div>
        <div class="check">
          <input id="enquiry-consent" name="consent" type="checkbox" required>
          <label for="enquiry-consent">I agree to be contacted about my enquiry. See our <a href="/privacy-policy/">Privacy Policy</a>.</label>
        </div>
        <button class="btn btn--accent" type="submit">Send enquiry</button>
      </div>

      <p class="enquiry__status" role="status" aria-live="polite"></p>
      <p class="enquiry__fallback" hidden>You can also reach us on <a href="https://wa.me/61291917405" rel="noopener">WhatsApp</a> or at <a href="mailto:Contact@CDRAustralia.Org">Contact@CDRAustralia.Org</a>.</p>

      <div class="enquiry__done" tabindex="-1" hidden>
        <p class="enquiry__done-icon" aria-hidden="true">✅</p>
        <h3>Thank you</h3>
        <p>Your enquiry has been received. We will get back to you soon.</p>
      </div>
    </form>
  </div>
  <script src="<?php echo esc_url( wp_make_link_relative( get_theme_file_uri( 'assets/js/enquiry-form.js' ) ) ); ?>" defer></script>
</section>
<!-- /wp:html -->