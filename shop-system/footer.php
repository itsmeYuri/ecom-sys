</div>
</main>

<?php if (empty($isEmbeddedView)): ?>
<footer class="footer mt-4 pt-5 pb-4">
    <div class="container">
        <div class="newsletter-box mb-4">
            <h4>STAY UP TO DATE ABOUT OUR<br>LATEST OFFERS</h4>
            <form class="newsletter-form" onsubmit="event.preventDefault()">
                <input type="email" placeholder="Enter your email address" aria-label="Email address">
                <button type="submit">Subscribe</button>
            </form>
        </div>
        <div class="row g-4 footer-links">
            <div class="col-md-4">
                <h5 class="fw-bold">Threap Glailz</h5>
                <p class="text-muted small">We handpick stylish thrift finds that look premium without the premium price.</p>
                <div class="socials mt-3" aria-label="Social media links">
                    <span>in</span>
                    <span>ig</span>
                    <span>fb</span>
                    <span>x</span>
                </div>
            </div>
            <div class="col-6 col-md-2"><h6>Company</h6><p class="small text-muted">About<br>Features<br>Works<br>Career</p></div>
            <div class="col-6 col-md-2"><h6>Help</h6><p class="small text-muted">Customer Support<br>Delivery Details<br>Terms &amp; Conditions<br>Privacy Policy</p></div>
            <div class="col-6 col-md-2"><h6>FAQ</h6><p class="small text-muted">Account<br>Manage Deliveries<br>Orders<br>Payments</p></div>
            <div class="col-6 col-md-2"><h6>Resources</h6><p class="small text-muted">Free eBooks<br>Development Tutorial<br>How to Blog<br>YouTube Playlist</p></div>
        </div>
    </div>
</footer>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
</body>
</html>
