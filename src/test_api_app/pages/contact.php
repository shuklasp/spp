<?php
/**
 * Contact Page — Demonstrates YAML-driven forms in native PHP
 * The form is defined in etc/apps/test_api_app/forms/contact.yml
 */
if (class_exists('\SPPMod\Drishyam\SPPUX')) {
    \SPPMod\Drishyam\SPPUX::boot();
}
?>
<div style="max-width: 700px; margin: 2rem auto; padding: 0 1rem;">
    <div style="background: #fff; border-radius: 16px; padding: 2.5rem; box-shadow: 0 4px 20px rgba(0,0,0,0.04); border: 1px solid #e2e8f0;">
        <span style="display:inline-block; background:#e0e7ff; color:#4f46e5; padding:4px 12px; border-radius:20px; font-size:0.75rem; font-weight:700; margin-bottom:1rem;">YAML FORM</span>
        <h1 style="margin: 0 0 0.5rem;">Contact Us</h1>
        <p style="color: #64748b;">This form is powered by the SPP YAML form engine. Definition: <code>etc/apps/test_api_app/forms/contact.yml</code></p>

        <form method="POST" style="margin-top: 2rem;">
            <input type="hidden" name="spp_form_id" value="contact">
            <div style="margin-bottom: 1.2rem;">
                <label style="display:block; font-weight:600; font-size:0.85rem; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:0.4rem; color:#64748b;">Name</label>
                <input type="text" name="guest_name" required placeholder="Your name" style="width:100%; padding:0.8rem; border:1px solid #e2e8f0; border-radius:10px; font-family:inherit; font-size:1rem;">
            </div>
            <div style="margin-bottom: 1.2rem;">
                <label style="display:block; font-weight:600; font-size:0.85rem; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:0.4rem; color:#64748b;">Email</label>
                <input type="email" name="email" placeholder="you@example.com" style="width:100%; padding:0.8rem; border:1px solid #e2e8f0; border-radius:10px; font-family:inherit; font-size:1rem;">
            </div>
            <div style="margin-bottom: 1.2rem;">
                <label style="display:block; font-weight:600; font-size:0.85rem; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:0.4rem; color:#64748b;">Message</label>
                <textarea name="message" rows="4" placeholder="Your message..." style="width:100%; padding:0.8rem; border:1px solid #e2e8f0; border-radius:10px; font-family:inherit; font-size:1rem;"></textarea>
            </div>
            <button type="submit" style="padding:0.8rem 2rem; background:#6366f1; color:#fff; border:none; border-radius:10px; font-weight:600; cursor:pointer;">Send Message</button>
        </form>
    </div>
</div>