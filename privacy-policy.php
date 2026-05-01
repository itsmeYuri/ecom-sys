<?php require_once __DIR__ . '/includes/functions.php'; include __DIR__ . '/header.php'; ?>
<div style="max-width:800px;margin:0 auto;">
    <h1 class="h2 fw-bold mb-1">Privacy Policy</h1>
    <p class="text-muted small mb-4">Last updated: April 2026</p>

    <div class="detail-box p-4">
        <h5 class="fw-bold">1. Information We Collect</h5>
        <p>We collect information you provide when creating an account, placing orders, or contacting support. This includes:</p>
        <ul><li>Full name, email address, phone number</li><li>Shipping address (stored encrypted)</li><li>Payment method selection (payment card details are handled by third-party processors)</li><li>Order history and product reviews</li></ul>

        <h5 class="fw-bold mt-4">2. How We Use Your Information</h5>
        <p>Your data is used to: process orders, send order confirmations, provide customer support, send security notifications (OTP codes), and improve our service.</p>

        <h5 class="fw-bold mt-4">3. Data Security</h5>
        <p>We employ industry-standard security measures:</p>
        <ul>
            <li>Passwords are hashed with bcrypt (cost factor 12)</li>
            <li>Sensitive data (shipping addresses) is AES-256-CBC encrypted at rest</li>
            <li>All connections use HTTPS/TLS in production</li>
            <li>CSRF tokens protect all form submissions</li>
            <li>Multi-factor authentication (OTP + TOTP) is available</li>
        </ul>

        <h5 class="fw-bold mt-4">4. Cookies</h5>
        <p>We use the following cookies:</p>
        <ul>
            <li><strong>tg_secure_session</strong> — Required. Maintains your login session. Expires when you close your browser.</li>
            <li><strong>cookie_consent</strong> — Stores your cookie consent preference for 1 year.</li>
        </ul>

        <h5 class="fw-bold mt-4">5. Third-Party Processors</h5>
        <p>Payment gateways (PayPal, Stripe, GCash, Maya) have their own privacy policies. We do not store full card numbers. Shipping is fulfilled by our logistics partners.</p>

        <h5 class="fw-bold mt-4">6. Your Rights</h5>
        <p>You may request to: access, correct, or delete your personal data. Contact us at <a href="mailto:privacy@threapglailz.com">privacy@threapglailz.com</a>.</p>

        <h5 class="fw-bold mt-4">7. Data Retention</h5>
        <p>Account data is retained until you request deletion or your account is inactive for 3 years. Order records are retained for 7 years for legal compliance.</p>

        <h5 class="fw-bold mt-4">8. Contact</h5>
        <p>For privacy inquiries: <a href="mailto:privacy@threapglailz.com">privacy@threapglailz.com</a></p>
    </div>
</div>
<?php include __DIR__ . '/footer.php'; ?>
