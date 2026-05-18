<?php
/**
 * Purpose: Shared footer partial closing layout and loading JS assets.
*/

$footerCurrentPage = strtolower(trim((string) ($page ?? '')));
$footerIsAdminPage = str_starts_with($footerCurrentPage, 'admin');
$renderFooterMarkup = isset($renderFooterMarkup) ? (bool) $renderFooterMarkup : true;
?>

<?php if ($renderFooterMarkup): ?>
	<footer class="site-footer" role="contentinfo">
		<div class="site-footer__inner">
			<div class="site-footer__top">
				<div class="site-footer__brand" aria-label="Ridex logo">
					<img
						src="images/large.png"
						alt="Ridex"
						class="site-footer__logo"
						onerror="this.onerror=null;this.src='images/logo.svg';"
					/>
				</div>
				<div class="site-footer__contact" aria-label="Ridex contact information">
					<a href="tel:+9779841222200">+977 9841222200</a>
					<a href="https://mail.google.com/mail/u/0/#inbox?compose=CllgCJNsMWchLNbVXHqGMGfGJZmjvCMTZljXMBCWcwBbvghKXcCPbZRCGdJHHbKxjttdjXbWmkg">info@ridex.com</a>
				</div>
			</div>

			<hr class="site-footer__rule" aria-hidden="true" />

			<div class="site-footer__grid">
				<section class="site-footer__section" aria-labelledby="footer-about">
					<h2 id="footer-about">About Us</h2>
					<p>
						Experience seamless travel across Nepal with RIDEX. From city commutes to rugged adventures, we provide reliable, premium vehicles perfectly tailored for your comfort and peace of mind.
					</p>
				</section>
				<section class="site-footer__section" aria-labelledby="footer-hours">
					<h2 id="footer-hours">Open Hours</h2>
					<p>
						Monday: 9:00 AM – 5:00 PM<br />
						Tuesday: 9:00 AM – 5:00 PM<br />
						Wednesday: 9:00 AM – 5:00 PM<br />
						Thursday: 9:00 AM – 5:00 PM<br />
						Friday: 9:00 AM – 5:00 PM
					</p>
				</section>
				<section class="site-footer__section" aria-labelledby="footer-address">
					<h2 id="footer-address">Address</h2>
					<address>
						Ridex<br />
						House No. 214, Narayan Chaur Marg<br />
						Naxal, Kathmandu 44600<br />
						Bagmati Province<br />
						Nepal
					</address>
				</section>
				<section class="site-footer__section" aria-labelledby="footer-social">
					<h2 id="footer-social">Follow Us</h2>
					<nav class="site-footer__social" aria-label="Social media links">
						<a href="https://www.facebook.com/chhabi.maharjan.5" target="_blank" rel="noopener noreferrer">Facebook</a>
						<a href="https://www.instagram.com/khadka_rashik/" target="_blank" rel="noopener noreferrer">Instagram</a>
						<a href="https://www.tiktok.com/@ha.na0425?is_from_webapp=1&sender_device=pc" target="_blank" rel="noopener noreferrer">Tiktok</a>
					</nav>
				</section>
			</div>

			<div class="site-footer__bottom">
				<p>© 2026 Ridex. All rights reserved.</p>
				<nav class="site-footer__policies" aria-label="Policy links">
					<a href="index.php?page=terms-conditions">Terms &amp; Conditions</a>
					<a href="index.php?page=privacy-policy">Privacy Policy</a>
					<a href="index.php?page=deposit-policy">Deposit Policy</a>
					<a href="index.php?page=damage-management-policy">Damage Management Policy</a>
				</nav>
			</div>
		</div>
	</footer>
<?php endif; ?>

<script src="js/vendor/flatpickr.min.js?v=20260407-1" defer></script>
<script src="js/admin-booking-modals.js?v=20260406-2" defer></script>
<script src="js/app.js?v=20260408-1" defer></script>
<script src="js/user-register-modals.js?v=20260408-1" defer></script>
<script src="js/booking.js?v=20260408-1" defer></script>
<?php if ($footerIsAdminPage): ?>
	<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js" defer></script>
	<script src="../charts/vehicles_chart.js?v=20260406-1" defer></script>
	<script src="../charts/fleet_chart.js?v=20260406-1" defer></script>
	<script src="js/admin-dashboard.js?v=20260406-4" defer></script>
	<script src="js/admin-bookings-search.js?v=20260406-3" defer></script>
<?php if ($footerCurrentPage === 'admin-live-tracking'): ?>
	<script src="js/admin-live-tracking-map.js?v=20260408-1" defer></script>
<?php endif; ?>
<?php endif; ?>
