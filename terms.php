<?php require_once __DIR__ . '/includes/functions.php'; include __DIR__ . '/header.php'; ?>
<div style="max-width:800px;margin:0 auto;">
    <h1 class="h2 fw-bold mb-1">Terms &amp; Conditions</h1>
    <p class="text-muted small mb-4">Last updated: April 2026</p>

    <div class="detail-box p-4">
        <h5 class="fw-bold">1. Acceptance of Terms</h5>
        <p>By using <?= e(APP_NAME) ?>, you agree to these terms. If you disagree, do not use our service.</p>

        <h5 class="fw-bold mt-4">2. Account Responsibilities</h5>
        <ul>
            <li>You are responsible for maintaining the confidentiality of your credentials.</li>
            <li>You must not share your account with others.</li>
            <li>Notify us immediately of unauthorized access.</li>
            <li>Accounts may be locked after <?= e(get_setting('max_login_attempts','3')) ?> consecutive failed login attempts.</li>
        </ul>

        <h5 class="fw-bold mt-4">3. Orders &amp; Payments</h5>
        <ul>
            <li>All prices are in Philippine Peso (₱) unless stated otherwise.</li>
            <li>Orders are subject to product availability.</li>
            <li>We reserve the right to cancel orders suspected of fraud.</li>
            <li>Payment processing is handled by third-party gateways (PayPal, Stripe, GCash, Maya).</li>
        </ul>

        <h5 class="fw-bold mt-4">4. Returns &amp; Refunds</h5>
        <p>Items may be returned within 7 days of delivery in original condition. Contact support to initiate a return.</p>

        <h5 class="fw-bold mt-4">5. Prohibited Activities</h5>
        <p>You may not: attempt unauthorized access, scrape or harvest data, use the service for illegal purposes, or circumvent security measures.</p>

        <h5 class="fw-bold mt-4">6. Limitation of Liability</h5>
        <p><?= e(APP_NAME) ?> is not liable for indirect, incidental, or consequential damages arising from use of the service.</p>

        <h5 class="fw-bold mt-4">7. Governing Law</h5>
        <p>These terms are governed by the laws of the Republic of the Philippines.</p>

        <h5 class="fw-bold mt-4">8. Contact</h5>
        <p>Legal inquiries: <a href="mailto:legal@threapglailz.com">legal@threapglailz.com</a></p>
    </div>
</div>
<?php include __DIR__ . '/footer.php'; ?>
