<?php
/**
 * Purpose: Shared modal markup (login/register/forgot password, generic alerts).
*/

$adminLoginError = isset($adminLoginError) ? trim((string) $adminLoginError) : '';
$adminLoginEmail = isset($adminLoginEmail) ? trim((string) $adminLoginEmail) : '';
$adminLoginEmailInvalid = isset($adminLoginEmailInvalid) ? (bool) $adminLoginEmailInvalid : false;
$adminLoginPasswordInvalid = isset($adminLoginPasswordInvalid) ? (bool) $adminLoginPasswordInvalid : false;
$userLoginError = isset($userLoginError) ? trim((string) $userLoginError) : '';
$userLoginIdentifier = isset($userLoginIdentifier) ? trim((string) $userLoginIdentifier) : '';
$userLoginIdentifierInvalid = isset($userLoginIdentifierInvalid) ? (bool) $userLoginIdentifierInvalid : false;
$userLoginPasswordInvalid = isset($userLoginPasswordInvalid) ? (bool) $userLoginPasswordInvalid : false;
$userLoginSuccess = isset($userLoginSuccess) ? trim((string) $userLoginSuccess) : '';
$userLoginCanResendVerification = isset($userLoginCanResendVerification) ? (bool) $userLoginCanResendVerification : false;
$userPostAuthRedirect = isset($userPostAuthRedirect) ? trim((string) $userPostAuthRedirect) : 'index.php';
if ($userPostAuthRedirect === '') {
	$userPostAuthRedirect = 'index.php';
}
$userRegisterErrors = isset($userRegisterErrors) && is_array($userRegisterErrors) ? $userRegisterErrors : [];
$userRegisterOld = isset($userRegisterOld) && is_array($userRegisterOld) ? $userRegisterOld : [];
$userRegisterSuccessEmail = isset($userRegisterSuccessEmail) ? trim((string) $userRegisterSuccessEmail) : '';
$registerPostAuthRedirect = trim((string) ($userRegisterOld['post_auth_redirect'] ?? $userPostAuthRedirect));
if ($registerPostAuthRedirect === '') {
	$registerPostAuthRedirect = 'index.php';
}
$adminSessionUser = isset($_SESSION['auth_user']) && is_array($_SESSION['auth_user'])
	? $_SESSION['auth_user']
	: [];
$isAdminSession = (($adminSessionUser['role'] ?? '') === 'admin');
$isUserSession = (($adminSessionUser['role'] ?? '') === 'user');
$userLoginShouldAutoOpen = $userLoginError !== '' || $userLoginSuccess !== '';
$userRegisterShouldAutoOpen = !empty($userRegisterErrors);
$userRegisterShouldOpenSuccess = $userRegisterSuccessEmail !== '';
$userRegisterProvinceOptions = ['Koshi', 'Madhesh', 'Bagmati', 'Gandaki', 'Lumbini', 'Karnali', 'Sudurpashchim'];
$userRegisterProvinceSelected = trim((string) ($userRegisterOld['province'] ?? 'Bagmati'));
if (!in_array($userRegisterProvinceSelected, $userRegisterProvinceOptions, true)) {
	$userRegisterProvinceSelected = 'Bagmati';
}

$userRegisterOldBool = static function (array $source, string $key): bool {
	$rawValue = $source[$key] ?? false;
	if (is_bool($rawValue)) {
		return $rawValue;
	}

	$normalized = strtolower(trim((string) $rawValue));
	return in_array($normalized, ['1', 'true', 'on', 'yes'], true);
};
$adminProfileNameRaw = trim((string) ($adminSessionUser['name'] ?? ''));
$adminProfileName = trim((string) preg_replace('/\s*admin\s*$/i', '', $adminProfileNameRaw));
if ($adminProfileName === '') {
	$adminProfileName = 'Ridex';
}
$adminProfileEmail = trim((string) ($adminSessionUser['email'] ?? 'info@ridex.com'));
$adminProfilePhone = trim((string) ($adminSessionUser['phone'] ?? ''));
if ($adminProfilePhone === '') {
	$adminProfilePhone = '+977 9841222200';
}
$userProfileUserId = (int) ($adminSessionUser['id'] ?? 0);
$userProfileName = trim((string) ($adminSessionUser['name'] ?? ''));
if ($userProfileName === '') {
	$userProfileName = 'Ridex User';
}
$userProfileEmail = trim((string) ($adminSessionUser['email'] ?? 'user@ridex.com'));
$userProfilePhone = trim((string) ($adminSessionUser['phone'] ?? ''));
if ($userProfilePhone === '') {
	$userProfilePhone = '+977 98XXXXXXXX';
}
$userProfileDobRaw = trim((string) ($adminSessionUser['date_of_birth'] ?? ''));
$userProfileDriversId = trim((string) ($adminSessionUser['drivers_id'] ?? ''));

if ($isUserSession && $userProfileUserId > 0 && function_exists('db')) {
	try {
		$userProfileLookupStmt = db()->prepare(
			'SELECT
				name,
				email,
				phone,
				drivers_id,
				date_of_birth
			 FROM users
			 WHERE id = :id AND role = :role
			 LIMIT 1'
		);
		$userProfileLookupStmt->execute([
			'id' => $userProfileUserId,
			'role' => 'user',
		]);
		$userProfileLookup = $userProfileLookupStmt->fetch();

		if (is_array($userProfileLookup)) {
			$userProfileName = trim((string) ($userProfileLookup['name'] ?? $userProfileName));
			$userProfileEmail = trim((string) ($userProfileLookup['email'] ?? $userProfileEmail));
			$userProfilePhoneDb = trim((string) ($userProfileLookup['phone'] ?? ''));
			if ($userProfilePhoneDb !== '') {
				$userProfilePhone = $userProfilePhoneDb;
			}
			$userProfileDriversIdDb = trim((string) ($userProfileLookup['drivers_id'] ?? ''));
			if ($userProfileDriversIdDb !== '') {
				$userProfileDriversId = $userProfileDriversIdDb;
			}
			$userProfileDobDb = trim((string) ($userProfileLookup['date_of_birth'] ?? ''));
			if ($userProfileDobDb !== '') {
				$userProfileDobRaw = $userProfileDobDb;
			}

			$_SESSION['auth_user']['name'] = $userProfileName;
			$_SESSION['auth_user']['email'] = $userProfileEmail;
			$_SESSION['auth_user']['phone'] = $userProfilePhone;
			$_SESSION['auth_user']['drivers_id'] = $userProfileDriversId;
			$_SESSION['auth_user']['date_of_birth'] = $userProfileDobRaw;
		}
	} catch (Throwable $exception) {
		error_log('User profile hydration failed: ' . $exception->getMessage());
	}
}

$userProfileDob = 'Not provided';
if ($userProfileDobRaw !== '') {
	try {
		$userProfileDob = (new DateTimeImmutable($userProfileDobRaw))->format('d M Y');
	} catch (Throwable $exception) {
		$userProfileDob = $userProfileDobRaw;
	}
}

if ($userProfileDriversId === '') {
	$userProfileDriversId = 'Not provided';
}

$bookingReceiptModalData = isset($bookingReceiptModalData) && is_array($bookingReceiptModalData)
	? $bookingReceiptModalData
	: [];
$hasBookingReceiptModalData = trim((string) ($bookingReceiptModalData['booking_number'] ?? '')) !== '';
$bookingReceiptStatusVariant = strtolower(trim((string) ($bookingReceiptModalData['status_variant'] ?? 'due')));
if (!in_array($bookingReceiptStatusVariant, ['paid', 'due'], true)) {
	$bookingReceiptStatusVariant = 'due';
}

//menu: global menu modal (entry point to admin and primary navigation)
?>

<div class="menu-modal" id="menu-modal" hidden aria-hidden="true" data-modal-id="menu-modal">
	<div class="menu-modal__overlay" data-modal-close></div>

	<section class="menu-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="menu-modal-title">
		<header class="menu-modal__header">
			<div class="menu-modal__brand" id="menu-modal-title" aria-label="Ridex menu">
				<img
					src="images/ridex-header.png"
					alt="Ridex logo"
					class="menu-modal__logo"
					onerror="this.onerror=null;this.src='images/logo.svg';"
				/>
			</div>

			<button class="menu-modal__close" type="button" aria-label="Close menu" data-modal-close>
				<span class="material-symbols-rounded" aria-hidden="true">close</span>
			</button>
		</header>

		<nav class="menu-modal__nav" aria-label="Primary menu links">
			<a class="menu-modal__link" href="index.php">Home</a>
			<a class="menu-modal__link" href="index.php?page=vehicles&amp;vehicle_type=cars">Cars</a>
			<a class="menu-modal__link" href="index.php?page=vehicles&amp;vehicle_type=bikes">Bikes</a>
			<a class="menu-modal__link" href="index.php?page=vehicles&amp;vehicle_type=luxury">Luxury</a>
			<a class="menu-modal__link menu-modal__link--admin" href="index.php?page=user-booking-history">Your Bookings</a>
			<button class="menu-modal__link menu-modal__link--admin" type="button" data-modal-target="admin-login-modal">Log in as admin</button>
		</nav>

		<button class="menu-modal__back" type="button" aria-label="Back to previous view" data-modal-back>
			<span class="material-symbols-rounded" aria-hidden="true">arrow_back</span>
		</button>
	</section>
</div>

<div class="menu-modal user-booking-confirm-modal" id="user-booking-confirm-modal" hidden aria-hidden="true" data-modal-id="user-booking-confirm-modal">
	<div class="menu-modal__overlay" data-modal-close></div>

	<section class="menu-modal__dialog admin-modal__dialog user-booking-confirm-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="user-booking-confirm-title">
		<header class="menu-modal__header user-booking-confirm-modal__header">
			<div class="menu-modal__brand" aria-label="Ridex booking confirmation">
				<img
					src="images/ridex-header.png"
					alt="Ridex logo"
					class="menu-modal__logo"
					onerror="this.onerror=null;this.src='images/logo.svg';"
				/>
			</div>

			<button class="menu-modal__close" type="button" aria-label="Close booking confirmation" data-modal-close>
				<span class="material-symbols-rounded" aria-hidden="true">close</span>
			</button>
		</header>

		<div class="user-booking-confirm-modal__content">
			<span class="material-symbols-rounded user-booking-confirm-modal__icon" aria-hidden="true">directions_car</span>
			<p class="user-booking-confirm-modal__text" id="user-booking-confirm-title">Do you want to confirm this booking?</p>

			<div class="user-booking-confirm-modal__actions">
				<button class="user-booking-confirm-modal__cancel" type="button" data-modal-back>Cancel</button>
				<form method="get" action="index.php" class="user-booking-confirm-modal__form">
					<input type="hidden" name="page" value="booking-engine" />
					<input type="hidden" name="flow_start" value="1" />
					<input type="hidden" name="vehicle_id" value="0" data-booking-confirm-vehicle-id />
					<input type="hidden" name="vehicle_type" value="cars" data-booking-confirm-vehicle-type />
					<input type="hidden" name="pickup-location" value="" data-booking-confirm-pickup-location />
					<input type="hidden" name="return-location" value="" data-booking-confirm-return-location />
					<input type="hidden" name="pickup-date" value="" data-booking-confirm-pickup-date />
					<input type="hidden" name="return-date" value="" data-booking-confirm-return-date />
					<input type="hidden" name="pickup-time" value="" data-booking-confirm-pickup-time />
					<input type="hidden" name="return-time" value="" data-booking-confirm-return-time />
					<button class="user-booking-confirm-modal__confirm" type="submit">Confirm</button>
				</form>
			</div>
		</div>

		<button class="menu-modal__back admin-modal__back" type="button" aria-label="Back to previous view" data-modal-back>
			<span class="material-symbols-rounded" aria-hidden="true">arrow_back</span>
		</button>
	</section>
</div>

<?php if ($hasBookingReceiptModalData): ?>
	<div class="menu-modal user-booking-bill-modal" id="user-booking-bill-modal" hidden aria-hidden="true" data-modal-id="user-booking-bill-modal">
		<div class="menu-modal__overlay" data-modal-close></div>

		<section class="menu-modal__dialog admin-modal__dialog user-booking-bill-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="user-booking-bill-title">
			<header class="menu-modal__header user-booking-bill-modal__header">
				<div class="menu-modal__brand" aria-label="Ridex booking bill">
					<img
						src="images/ridex-header.png"
						alt="Ridex logo"
						class="menu-modal__logo"
						onerror="this.onerror=null;this.src='images/logo.svg';"
					/>
				</div>

				<button class="menu-modal__close" type="button" aria-label="Close booking bill" data-modal-close>
					<span class="material-symbols-rounded" aria-hidden="true">close</span>
				</button>
			</header>

			<div class="user-booking-bill-modal__content" id="user-booking-bill-title">
				<p class="user-booking-bill-modal__booking-number">
					Booking Number:
					<span><?= htmlspecialchars((string) ($bookingReceiptModalData['booking_number'] ?? '#RX-0000'), ENT_QUOTES, 'UTF-8') ?></span>
				</p>
				<p class="user-booking-bill-modal__status user-booking-bill-modal__status--<?= htmlspecialchars($bookingReceiptStatusVariant, ENT_QUOTES, 'UTF-8') ?>">
					<?= htmlspecialchars((string) ($bookingReceiptModalData['status_line'] ?? 'Due N/A'), ENT_QUOTES, 'UTF-8') ?>
				</p>

				<h3 class="user-booking-bill-modal__heading">Price details</h3>

				<div class="user-booking-bill-modal__line"><span>Price per day</span><span>NRs <?= htmlspecialchars(number_format((float) ((int) ($bookingReceiptModalData['price_per_day'] ?? 0)), 2), ENT_QUOTES, 'UTF-8') ?></span></div>
				<div class="user-booking-bill-modal__line"><span>Price for period</span><span>NRs <?= htmlspecialchars(number_format((float) ((int) ($bookingReceiptModalData['price_for_days'] ?? 0)), 2), ENT_QUOTES, 'UTF-8') ?></span></div>
				<div class="user-booking-bill-modal__line"><span>Drop charge</span><span>NRs <?= htmlspecialchars(number_format((float) ((int) ($bookingReceiptModalData['drop_charge'] ?? 0)), 2), ENT_QUOTES, 'UTF-8') ?></span></div>
				<div class="user-booking-bill-modal__line"><span>Taxes &amp; Fees</span><span>NRs <?= htmlspecialchars(number_format((float) ((int) ($bookingReceiptModalData['taxes_and_fees'] ?? 0)), 2), ENT_QUOTES, 'UTF-8') ?></span></div>
				<div class="user-booking-bill-modal__total"><span>NRs <?= htmlspecialchars(number_format((float) ((int) ($bookingReceiptModalData['total_amount'] ?? 0)), 2), ENT_QUOTES, 'UTF-8') ?></span></div>

				<div class="user-booking-bill-modal__actions">
					<a
						class="user-booking-bill-modal__download"
						href="<?= htmlspecialchars((string) ($bookingReceiptModalData['download_pdf_url'] ?? '#'), ENT_QUOTES, 'UTF-8') ?>"
					>
						Download
					</a>
				</div>
			</div>

			<button class="menu-modal__back admin-modal__back" type="button" aria-label="Back to previous view" data-modal-back>
				<span class="material-symbols-rounded" aria-hidden="true">arrow_back</span>
			</button>
		</section>
	</div>
<?php endif; ?>

<?php if ($userLoginShouldAutoOpen): ?>
	<button type="button" hidden data-user-auth-auto-open="true" data-modal-target="user-login-modal"></button>
<?php endif; ?>

<button type="button" hidden data-booking-login-modal-trigger="true" data-modal-target="user-login-modal"></button>

<div class="menu-modal user-login-modal" id="user-login-modal" hidden aria-hidden="true" data-modal-id="user-login-modal">
	<div class="menu-modal__overlay" data-modal-close></div>

	<section class="menu-modal__dialog admin-modal__dialog user-auth-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="user-login-title">
		<header class="menu-modal__header user-auth-modal__header">
			<div class="menu-modal__brand" aria-label="Ridex user login">
				<img
					src="images/ridex-header.png"
					alt="Ridex logo"
					class="menu-modal__logo"
					onerror="this.onerror=null;this.src='images/logo.svg';"
				/>
			</div>

			<button class="menu-modal__close" type="button" aria-label="Close user login" data-modal-close>
				<span class="material-symbols-rounded" aria-hidden="true">close</span>
			</button>
		</header>

		<div class="user-login-modal__content">
			<h2 class="user-login-modal__title" id="user-login-title">Log In</h2>

			<form class="user-login-form" method="post" action="index.php" autocomplete="off" novalidate>
				<input type="hidden" name="action" value="user-login" />
				<input
					type="hidden"
					name="post_auth_redirect"
					value="<?= htmlspecialchars($userPostAuthRedirect, ENT_QUOTES, 'UTF-8') ?>"
					data-user-post-auth-redirect
				/>

				<label class="user-login-form__label" for="user-login-identifier-input">Your Email / Driver ID</label>
				<input
					class="user-login-form__input<?= $userLoginIdentifierInvalid ? ' user-login-form__input--error' : '' ?>"
					type="text"
					id="user-login-identifier-input"
					name="user_identifier"
					placeholder="Enter your email or driver's ID"
					autocomplete="username"
					spellcheck="false"
					required
					value="<?= htmlspecialchars($userLoginIdentifier, ENT_QUOTES, 'UTF-8') ?>"
				/>

				<label class="user-login-form__label" for="user-login-password-input">Password</label>
				<div class="user-login-form__password-wrap">
					<input
						class="user-login-form__input<?= $userLoginPasswordInvalid ? ' user-login-form__input--error' : '' ?>"
						type="password"
						id="user-login-password-input"
						name="user_password"
						placeholder="Enter your password"
						autocomplete="current-password"
						required
						minlength="8"
					/>
					<button
						class="user-login-form__password-toggle"
						type="button"
						aria-label="Show password"
						data-password-toggle
						data-password-target="user-login-password-input"
					>
						<span class="material-symbols-rounded" aria-hidden="true">visibility</span>
					</button>
				</div>

				<button class="user-login-form__forgot" type="button" data-modal-target="user-forgot-email-modal">Forgot your password?</button>

				<?php if ($userLoginError !== ''): ?>
					<p class="user-login-form__error" role="alert"><?= htmlspecialchars($userLoginError, ENT_QUOTES, 'UTF-8') ?></p>
				<?php endif; ?>
				<?php if ($userLoginSuccess !== ''): ?>
					<p class="user-login-form__success" role="status"><?= htmlspecialchars($userLoginSuccess, ENT_QUOTES, 'UTF-8') ?></p>
				<?php endif; ?>

				<button class="user-login-form__submit" type="submit">Log In</button>
			</form>

			<?php if ($userLoginCanResendVerification): ?>
				<form class="user-login-form user-login-form--resend" method="post" action="index.php" autocomplete="off">
					<input type="hidden" name="action" value="user-resend-verification-email" />
					<input type="hidden" name="user_identifier" value="<?= htmlspecialchars($userLoginIdentifier, ENT_QUOTES, 'UTF-8') ?>" />
					<input type="hidden" name="post_auth_redirect" value="<?= htmlspecialchars($userPostAuthRedirect, ENT_QUOTES, 'UTF-8') ?>" />
					<button class="user-login-form__resend" type="submit">Resend Verification Email</button>
				</form>
			<?php endif; ?>

			<button class="user-login-form__create-account" type="button" data-modal-target="user-register-personal-modal">Create Account</button>
		</div>

		<button class="menu-modal__back admin-modal__back" type="button" aria-label="Back to previous view" data-modal-back>
			<span class="material-symbols-rounded" aria-hidden="true">arrow_back</span>
		</button>
	</section>
</div>

<div class="menu-modal user-login-modal" id="user-forgot-email-modal" hidden aria-hidden="true" data-modal-id="user-forgot-email-modal">
	<div class="menu-modal__overlay" data-modal-close></div>

	<section class="menu-modal__dialog admin-modal__dialog user-auth-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="user-forgot-email-title">
		<header class="menu-modal__header user-auth-modal__header">
			<div class="menu-modal__brand" aria-label="Ridex forgot password email">
				<img
					src="images/ridex-header.png"
					alt="Ridex logo"
					class="menu-modal__logo"
					onerror="this.onerror=null;this.src='images/logo.svg';"
				/>
			</div>

			<button class="menu-modal__close" type="button" aria-label="Close forgot password" data-modal-close>
				<span class="material-symbols-rounded" aria-hidden="true">close</span>
			</button>
		</header>

		<div class="user-reset-modal__content">
			<h2 class="user-reset-modal__title" id="user-forgot-email-title">Forgot Password?</h2>
			<p class="user-reset-modal__text">Enter your registered email to start the password reset process.</p>

			<label class="user-login-form__label" for="user-forgot-email-input">Email</label>
			<input
				class="user-login-form__input"
				type="email"
				id="user-forgot-email-input"
				placeholder="example@ridex.com"
				autocomplete="email"
				required
			/>

			<button
				class="user-login-form__submit"
				type="button"
				data-user-forgot-email-continue
				data-modal-target="user-forgot-driver-modal"
			>
				Continue
			</button>
		</div>

		<button class="menu-modal__back admin-modal__back" type="button" aria-label="Back to previous view" data-modal-back>
			<span class="material-symbols-rounded" aria-hidden="true">arrow_back</span>
		</button>
	</section>
</div>

<div class="menu-modal user-login-modal" id="user-forgot-driver-modal" hidden aria-hidden="true" data-modal-id="user-forgot-driver-modal">
	<div class="menu-modal__overlay" data-modal-close></div>

	<section class="menu-modal__dialog admin-modal__dialog user-auth-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="user-forgot-driver-title">
		<header class="menu-modal__header user-auth-modal__header">
			<div class="menu-modal__brand" aria-label="Ridex forgot password driver ID">
				<img
					src="images/ridex-header.png"
					alt="Ridex logo"
					class="menu-modal__logo"
					onerror="this.onerror=null;this.src='images/logo.svg';"
				/>
			</div>

			<button class="menu-modal__close" type="button" aria-label="Close driver ID verification" data-modal-close>
				<span class="material-symbols-rounded" aria-hidden="true">close</span>
			</button>
		</header>

		<div class="user-reset-modal__content">
			<h2 class="user-reset-modal__title" id="user-forgot-driver-title">Driver ID Verification</h2>
			<p class="user-reset-modal__text">Enter your Driver ID to verify your account and send a reset link.</p>

			<label class="user-login-form__label" for="user-forgot-driver-id-input">Driver ID</label>
			<input
				class="user-login-form__input"
				type="text"
				id="user-forgot-driver-id-input"
				placeholder="Enter your driver ID"
				autocomplete="off"
				required
			/>

			<button
				class="user-login-form__submit"
				type="button"
				data-user-forgot-driver-continue
				data-modal-target="user-forgot-sent-modal"
			>
				Continue
			</button>
		</div>

		<button class="menu-modal__back admin-modal__back" type="button" aria-label="Back to previous view" data-modal-back>
			<span class="material-symbols-rounded" aria-hidden="true">arrow_back</span>
		</button>
	</section>
</div>

<div class="menu-modal user-login-modal" id="user-forgot-sent-modal" hidden aria-hidden="true" data-modal-id="user-forgot-sent-modal">
	<div class="menu-modal__overlay" data-modal-close></div>

	<section class="menu-modal__dialog admin-modal__dialog user-auth-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="user-forgot-sent-title">
		<header class="menu-modal__header user-auth-modal__header">
			<div class="menu-modal__brand" aria-label="Ridex reset email sent">
				<img
					src="images/ridex-header.png"
					alt="Ridex logo"
					class="menu-modal__logo"
					onerror="this.onerror=null;this.src='images/logo.svg';"
				/>
			</div>

			<button class="menu-modal__close" type="button" aria-label="Close reset email confirmation" data-modal-close>
				<span class="material-symbols-rounded" aria-hidden="true">close</span>
			</button>
		</header>

		<div class="user-reset-modal__content user-reset-modal__content--sent">
			<span class="material-symbols-rounded user-reset-modal__icon" aria-hidden="true">mail</span>
			<h2 class="user-reset-modal__title" id="user-forgot-sent-title">Email Sent!</h2>
			<p class="user-reset-modal__text user-reset-modal__text--center">
				We sent a password reset link to
				<span class="user-reset-modal__email" data-user-forgot-email-preview>example@ridex.com</span>.
				Please check your inbox and spam folder.
			</p>

			<button class="user-login-form__submit" type="button" data-modal-target="user-forgot-email-modal">Send Another Reset Link</button>
		</div>

		<button class="menu-modal__back admin-modal__back" type="button" aria-label="Back to previous view" data-modal-back>
			<span class="material-symbols-rounded" aria-hidden="true">arrow_back</span>
		</button>
	</section>
</div>

<form id="user-register-form" method="post" action="index.php" enctype="multipart/form-data" novalidate>
	<input type="hidden" name="action" value="user-register" />
	<input
		type="hidden"
		name="post_auth_redirect"
		value="<?= htmlspecialchars($registerPostAuthRedirect, ENT_QUOTES, 'UTF-8') ?>"
		data-user-post-auth-redirect
	/>
	<input
		type="hidden"
		name="subscribe_newsletter"
		value="<?= $userRegisterOldBool($userRegisterOld, 'subscribe_newsletter') ? '1' : '0' ?>"
		data-user-register-newsletter-hidden
	/>
</form>

<?php if ($userRegisterShouldOpenSuccess): ?>
	<button type="button" hidden data-user-register-auto-open="true" data-modal-target="user-register-created-modal"></button>
<?php elseif ($userRegisterShouldAutoOpen): ?>
	<button type="button" hidden data-user-register-auto-open="true" data-modal-target="user-register-personal-modal"></button>
<?php endif; ?>

<div class="menu-modal user-register-modal" id="user-register-personal-modal" hidden aria-hidden="true" data-modal-id="user-register-personal-modal">
	<div class="menu-modal__overlay" data-modal-close></div>

	<section class="menu-modal__dialog admin-modal__dialog user-register-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="user-register-personal-title">
		<header class="menu-modal__header user-register-modal__header">
			<div class="menu-modal__brand" aria-label="Ridex create account personal information">
				<img
					src="images/ridex-header.png"
					alt="Ridex logo"
					class="menu-modal__logo"
					onerror="this.onerror=null;this.src='images/logo.svg';"
				/>
			</div>

			<button class="menu-modal__close" type="button" aria-label="Close create account" data-modal-close>
				<span class="material-symbols-rounded" aria-hidden="true">close</span>
			</button>
		</header>

		<div class="user-register-modal__content">
			<h2 class="user-register-modal__title" id="user-register-personal-title">Personal Information</h2>

			<?php if (!empty($userRegisterErrors)): ?>
				<div class="user-register-modal__errors" role="alert">
					<?php foreach ($userRegisterErrors as $userRegisterError): ?>
						<p><?= htmlspecialchars((string) $userRegisterError, ENT_QUOTES, 'UTF-8') ?></p>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<label class="user-register-modal__label" for="user-register-first-name">First Name</label>
			<input
				class="admin-create-vehicle-modal__input user-register-modal__input"
				type="text"
				id="user-register-first-name"
				name="first_name"
				form="user-register-form"
				maxlength="100"
				required
				value="<?= htmlspecialchars((string) ($userRegisterOld['first_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
			/>

			<label class="user-register-modal__label" for="user-register-last-name">Last Name</label>
			<input
				class="admin-create-vehicle-modal__input user-register-modal__input"
				type="text"
				id="user-register-last-name"
				name="last_name"
				form="user-register-form"
				maxlength="100"
				required
				value="<?= htmlspecialchars((string) ($userRegisterOld['last_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
			/>

			<label class="user-register-modal__label" for="user-register-date-of-birth">Date of Birth</label>
			<div class="admin-create-vehicle-modal__date-wrap user-register-modal__date-wrap">
				<input
					class="admin-create-vehicle-modal__input user-register-modal__input"
					type="text"
					id="user-register-date-of-birth"
					name="date_of_birth"
					form="user-register-form"
					placeholder="dd/mm/yyyy"
					autocomplete="off"
					required
					value="<?= htmlspecialchars((string) ($userRegisterOld['date_of_birth'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
				/>
				<button class="booking-input__icon-button admin-create-vehicle-modal__date-button" type="button" aria-label="Open date picker" data-open-picker-for="user-register-date-of-birth">
					<span class="material-symbols-rounded admin-create-vehicle-modal__date-icon" aria-hidden="true">calendar_month</span>
				</button>
			</div>

			<label class="user-register-modal__label" for="user-register-drivers-id">Driver's ID</label>
			<div class="user-register-modal__driver-id-row">
				<div class="user-register-modal__driver-id-side">
					<input
						class="admin-create-vehicle-modal__input user-register-modal__input"
						type="text"
						id="user-register-drivers-id"
						name="drivers_id"
						form="user-register-form"
						maxlength="50"
						required
						value="<?= htmlspecialchars((string) ($userRegisterOld['drivers_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
					/>
				</div>

				<span class="user-register-modal__driver-id-separator" aria-hidden="true"></span>

				<div class="user-register-modal__driver-id-upload-side">
					<div class="user-register-modal__driver-id-preview" aria-live="polite" data-user-register-driver-image-preview>
						<img class="user-register-modal__driver-id-preview-image" alt="" hidden data-user-register-driver-image-preview-image />
						<span class="user-register-modal__driver-id-preview-placeholder" data-user-register-driver-image-preview-placeholder>No image selected</span>
						<span class="user-register-modal__driver-id-preview-name" hidden data-user-register-driver-image-preview-name></span>
						<div class="user-register-modal__driver-id-preview-actions">
							<button class="user-register-modal__driver-id-action user-register-modal__driver-id-action--clear" type="button" aria-label="Remove uploaded Driver's ID image" data-user-register-driver-image-clear hidden>
								<span class="material-symbols-rounded" aria-hidden="true">close</span>
							</button>

							<button class="user-register-modal__driver-id-action user-register-modal__driver-id-action--camera" type="button" aria-label="Upload Driver's ID image" data-user-register-driver-image-trigger>
								<span class="material-symbols-rounded" aria-hidden="true">photo_camera</span>
							</button>
						</div>
					</div>

					<input
						class="user-register-modal__driver-id-file-input"
						type="file"
						id="user-register-drivers-id-image"
						name="drivers_id_image"
						form="user-register-form"
						accept="image/png,image/jpeg,image/jpg,image/webp"
						data-user-register-driver-image-input
					/>
					<p class="user-register-modal__driver-id-message" role="alert" data-user-register-driver-image-message hidden></p>
				</div>
			</div>

			<button class="user-register-modal__continue" type="button" data-user-register-next data-user-register-step="personal" data-modal-target="user-register-contact-modal">Continue</button>
		</div>

		<button class="menu-modal__back admin-modal__back" type="button" aria-label="Back to previous view" data-modal-back>
			<span class="material-symbols-rounded" aria-hidden="true">arrow_back</span>
		</button>
	</section>
</div>

<div class="menu-modal user-register-modal" id="user-register-contact-modal" hidden aria-hidden="true" data-modal-id="user-register-contact-modal">
	<div class="menu-modal__overlay" data-modal-close></div>

	<section class="menu-modal__dialog admin-modal__dialog user-register-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="user-register-contact-title">
		<header class="menu-modal__header user-register-modal__header">
			<div class="menu-modal__brand" aria-label="Ridex create account contact details">
				<img
					src="images/ridex-header.png"
					alt="Ridex logo"
					class="menu-modal__logo"
					onerror="this.onerror=null;this.src='images/logo.svg';"
				/>
			</div>

			<button class="menu-modal__close" type="button" aria-label="Close create account" data-modal-close>
				<span class="material-symbols-rounded" aria-hidden="true">close</span>
			</button>
		</header>

		<div class="user-register-modal__content">
			<h2 class="user-register-modal__title" id="user-register-contact-title">Contact Details</h2>

			<label class="user-register-modal__label" for="user-register-phone">Phone Number</label>
			<input
				class="admin-create-vehicle-modal__input user-register-modal__input"
				type="tel"
				id="user-register-phone"
				name="phone"
				form="user-register-form"
				maxlength="15"
				minlength="15"
				inputmode="numeric"
				autocomplete="tel"
				pattern="[+]977 9[0-9]{9}"
				title="Phone must be in +977 9XXXXXXXXX format."
				placeholder="+977 9XXXXXXXXX"
				required
				value="<?= htmlspecialchars((string) ($userRegisterOld['phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
			/>

			<label class="user-register-modal__label" for="user-register-email">Email</label>
			<input
				class="admin-create-vehicle-modal__input user-register-modal__input"
				type="email"
				id="user-register-email"
				name="email"
				form="user-register-form"
				maxlength="150"
				required
				value="<?= htmlspecialchars((string) ($userRegisterOld['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
			/>

			<label class="user-register-modal__checkbox" for="user-register-newsletter">
				<input
					type="checkbox"
					id="user-register-newsletter"
					data-user-register-newsletter
					<?= $userRegisterOldBool($userRegisterOld, 'subscribe_newsletter') ? 'checked' : '' ?>
				/>
				<span>I confirm to receive news and special offers from Ridex.</span>
			</label>

			<button class="user-register-modal__continue" type="button" data-user-register-next data-user-register-step="contact" data-modal-target="user-register-address-modal">Continue</button>
		</div>

		<button class="menu-modal__back admin-modal__back" type="button" aria-label="Back to previous view" data-modal-back>
			<span class="material-symbols-rounded" aria-hidden="true">arrow_back</span>
		</button>
	</section>
</div>

<div class="menu-modal user-register-modal" id="user-register-address-modal" hidden aria-hidden="true" data-modal-id="user-register-address-modal">
	<div class="menu-modal__overlay" data-modal-close></div>

	<section class="menu-modal__dialog admin-modal__dialog user-register-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="user-register-address-title">
		<header class="menu-modal__header user-register-modal__header">
			<div class="menu-modal__brand" aria-label="Ridex create account address details">
				<img
					src="images/ridex-header.png"
					alt="Ridex logo"
					class="menu-modal__logo"
					onerror="this.onerror=null;this.src='images/logo.svg';"
				/>
			</div>

			<button class="menu-modal__close" type="button" aria-label="Close create account" data-modal-close>
				<span class="material-symbols-rounded" aria-hidden="true">close</span>
			</button>
		</header>

		<div class="user-register-modal__content">
			<h2 class="user-register-modal__title" id="user-register-address-title">Address</h2>

			<label class="user-register-modal__label" for="user-register-street">Street</label>
			<input
				class="admin-create-vehicle-modal__input user-register-modal__input"
				type="text"
				id="user-register-street"
				name="street"
				form="user-register-form"
				maxlength="255"
				required
				value="<?= htmlspecialchars((string) ($userRegisterOld['street'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
			/>

			<label class="user-register-modal__label" for="user-register-province">Province</label>
			<select class="admin-create-vehicle-modal__input admin-create-vehicle-modal__select user-register-modal__input" id="user-register-province" name="province" form="user-register-form" required>
				<?php foreach ($userRegisterProvinceOptions as $userRegisterProvinceOption): ?>
					<option value="<?= htmlspecialchars($userRegisterProvinceOption, ENT_QUOTES, 'UTF-8') ?>" <?= $userRegisterProvinceSelected === $userRegisterProvinceOption ? 'selected' : '' ?>><?= htmlspecialchars($userRegisterProvinceOption, ENT_QUOTES, 'UTF-8') ?></option>
				<?php endforeach; ?>
			</select>

			<label class="user-register-modal__label" for="user-register-post-code">Post Code</label>
			<input
				class="admin-create-vehicle-modal__input user-register-modal__input"
				type="text"
				id="user-register-post-code"
				name="post_code"
				form="user-register-form"
				maxlength="20"
				required
				value="<?= htmlspecialchars((string) ($userRegisterOld['post_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
			/>

			<label class="user-register-modal__label" for="user-register-city">City</label>
			<input
				class="admin-create-vehicle-modal__input user-register-modal__input"
				type="text"
				id="user-register-city"
				name="city"
				form="user-register-form"
				maxlength="100"
				required
				value="<?= htmlspecialchars((string) ($userRegisterOld['city'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
			/>

			<button class="user-register-modal__continue" type="button" data-user-register-next data-user-register-step="address" data-modal-target="user-register-password-modal">Continue</button>
		</div>

		<button class="menu-modal__back admin-modal__back" type="button" aria-label="Back to previous view" data-modal-back>
			<span class="material-symbols-rounded" aria-hidden="true">arrow_back</span>
		</button>
	</section>
</div>

<div class="menu-modal user-register-modal" id="user-register-password-modal" hidden aria-hidden="true" data-modal-id="user-register-password-modal">
	<div class="menu-modal__overlay" data-modal-close></div>

	<section class="menu-modal__dialog admin-modal__dialog user-register-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="user-register-password-title">
		<header class="menu-modal__header user-register-modal__header">
			<div class="menu-modal__brand" aria-label="Ridex create account password details">
				<img
					src="images/ridex-header.png"
					alt="Ridex logo"
					class="menu-modal__logo"
					onerror="this.onerror=null;this.src='images/logo.svg';"
				/>
			</div>

			<button class="menu-modal__close" type="button" aria-label="Close create account" data-modal-close>
				<span class="material-symbols-rounded" aria-hidden="true">close</span>
			</button>
		</header>

		<div class="user-register-modal__content">
			<h2 class="user-register-modal__title" id="user-register-password-title">Create Password</h2>

			<label class="user-register-modal__label" for="user-register-password-input">Password</label>
			<div class="admin-login-form__password-wrap user-register-modal__password-wrap">
				<input
					class="admin-create-vehicle-modal__input user-register-modal__input"
					type="password"
					id="user-register-password-input"
					name="password"
					form="user-register-form"
					required
					minlength="8"
					autocomplete="new-password"
					data-user-register-password
				/>
				<button
					class="admin-login-form__password-toggle"
					type="button"
					aria-label="Show password"
					data-password-toggle
					data-password-target="user-register-password-input"
				>
					<span class="material-symbols-rounded" aria-hidden="true">visibility</span>
				</button>
			</div>

			<ul class="user-register-modal__password-rules" aria-label="Password requirements">
				<li data-password-rule="lowercase"><span class="material-symbols-rounded" aria-hidden="true">check</span><span>One lowercase letter</span></li>
				<li data-password-rule="uppercase"><span class="material-symbols-rounded" aria-hidden="true">check</span><span>One uppercase letter</span></li>
				<li data-password-rule="digit"><span class="material-symbols-rounded" aria-hidden="true">check</span><span>One digit</span></li>
				<li data-password-rule="symbol"><span class="material-symbols-rounded" aria-hidden="true">check</span><span>One symbol</span></li>
				<li data-password-rule="length"><span class="material-symbols-rounded" aria-hidden="true">check</span><span>8 characters minimum</span></li>
			</ul>

			<button class="user-register-modal__continue" type="button" data-user-register-next data-user-register-step="password" data-modal-target="user-register-terms-modal">Continue</button>
		</div>

		<button class="menu-modal__back admin-modal__back" type="button" aria-label="Back to previous view" data-modal-back>
			<span class="material-symbols-rounded" aria-hidden="true">arrow_back</span>
		</button>
	</section>
</div>

<div class="menu-modal user-register-modal" id="user-register-terms-modal" hidden aria-hidden="true" data-modal-id="user-register-terms-modal">
	<div class="menu-modal__overlay" data-modal-close></div>

	<section class="menu-modal__dialog admin-modal__dialog user-register-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="user-register-terms-title">
		<header class="menu-modal__header user-register-modal__header">
			<div class="menu-modal__brand" aria-label="Ridex create account terms and conditions">
				<img
					src="images/ridex-header.png"
					alt="Ridex logo"
					class="menu-modal__logo"
					onerror="this.onerror=null;this.src='images/logo.svg';"
				/>
			</div>

			<button class="menu-modal__close" type="button" aria-label="Close create account" data-modal-close>
				<span class="material-symbols-rounded" aria-hidden="true">close</span>
			</button>
		</header>

		<div class="user-register-modal__content user-register-modal__content--terms">
			<h2 class="user-register-modal__title" id="user-register-terms-title">Terms &amp; Conditions</h2>

			<label class="user-register-modal__checkbox user-register-modal__checkbox--terms" for="user-register-terms-privacy">
				<input type="checkbox" id="user-register-terms-privacy" name="terms_privacy" value="1" form="user-register-form" required <?= $userRegisterOldBool($userRegisterOld, 'terms_privacy') ? 'checked' : '' ?> />
				<span><strong>Terms &amp; Privacy:</strong> "I hereby acknowledge that I have thoroughly read, understood, and agree to be legally bound by the <a href="index.php?page=terms-conditions" target="_blank" rel="noopener noreferrer">Terms &amp; Conditions</a>, which also encompasses Ridex's comprehensive <a href="index.php?page=privacy-policy" target="_blank" rel="noopener noreferrer">Privacy Policy</a> regarding data usage."</span>
			</label>

			<label class="user-register-modal__checkbox user-register-modal__checkbox--terms" for="user-register-terms-deposit">
				<input type="checkbox" id="user-register-terms-deposit" name="terms_deposit" value="1" form="user-register-form" required <?= $userRegisterOldBool($userRegisterOld, 'terms_deposit') ? 'checked' : '' ?> />
				<span><strong>Deposit Policy:</strong> "I confirm that I have reviewed the <a href="index.php?page=deposit-policy" target="_blank" rel="noopener noreferrer">Deposit Policy</a> and consent to the financial hold requirements necessary to proceed with my booking."</span>
			</label>

			<label class="user-register-modal__checkbox user-register-modal__checkbox--terms" for="user-register-terms-damage">
				<input type="checkbox" id="user-register-terms-damage" name="terms_damage" value="1" form="user-register-form" required <?= $userRegisterOldBool($userRegisterOld, 'terms_damage') ? 'checked' : '' ?> />
				<span><strong>Damage Policy:</strong> "I have read and fully accept the terms outlined in Ridex's <a href="index.php?page=damage-management-policy" target="_blank" rel="noopener noreferrer">Damage Management Policy</a>, including my responsibilities regarding vehicle condition and potential repair fees."</span>
			</label>

			<button class="user-register-modal__continue" type="submit" form="user-register-form" data-user-register-submit>Continue</button>
			<p class="user-register-modal__terms-note">By continuing, you agree to our Terms of Service and Policies. Your data is protected and never shared with third parties.</p>
		</div>

		<button class="menu-modal__back admin-modal__back" type="button" aria-label="Back to previous view" data-modal-back>
			<span class="material-symbols-rounded" aria-hidden="true">arrow_back</span>
		</button>
	</section>
</div>

<div class="menu-modal user-register-modal" id="user-register-created-modal" hidden aria-hidden="true" data-modal-id="user-register-created-modal">
	<div class="menu-modal__overlay" data-modal-close></div>

	<section class="menu-modal__dialog admin-modal__dialog user-register-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="user-register-created-title">
		<header class="menu-modal__header user-register-modal__header">
			<div class="menu-modal__brand" aria-label="Ridex account created confirmation">
				<img
					src="images/ridex-header.png"
					alt="Ridex logo"
					class="menu-modal__logo"
					onerror="this.onerror=null;this.src='images/logo.svg';"
				/>
			</div>

			<button class="menu-modal__close" type="button" aria-label="Close account created modal" data-modal-close>
				<span class="material-symbols-rounded" aria-hidden="true">close</span>
			</button>
		</header>

		<div class="user-register-modal__content user-register-modal__content--created">
			<h2 class="user-register-modal__title user-register-modal__title--success" id="user-register-created-title">Account Created!</h2>

			<p class="user-register-modal__created-heading">Check your inbox to confirm your email</p>
			<p class="user-register-modal__created-text">
				Look for the email we sent to
				<span class="user-register-modal__created-email"><?= htmlspecialchars($userRegisterSuccessEmail !== '' ? $userRegisterSuccessEmail : 'example@gmail.com', ENT_QUOTES, 'UTF-8') ?></span>
				and use the button to confirm within the next 24 hours.
			</p>
			<p class="user-register-modal__created-text">Can't see it? Check in spam or try again later</p>

			<a class="user-register-modal__continue user-register-modal__continue--link" href="index.php">Go to Home</a>
		</div>

		<button class="menu-modal__back admin-modal__back" type="button" aria-label="Back to previous view" data-modal-back>
			<span class="material-symbols-rounded" aria-hidden="true">arrow_back</span>
		</button>
	</section>
</div>

<?php if ($isUserSession): ?>
	<div class="menu-modal user-profile-modal" id="user-profile-modal" hidden aria-hidden="true" data-modal-id="user-profile-modal">
		<div class="menu-modal__overlay" data-modal-close></div>

		<section class="menu-modal__dialog admin-modal__dialog user-auth-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="user-profile-title">
			<header class="menu-modal__header user-auth-modal__header">
				<div class="menu-modal__brand" aria-label="Ridex user profile">
					<img
						src="images/ridex-header.png"
						alt="Ridex logo"
						class="menu-modal__logo"
						onerror="this.onerror=null;this.src='images/logo.svg';"
					/>
				</div>

				<button class="menu-modal__close" type="button" aria-label="Close user profile" data-modal-close>
					<span class="material-symbols-rounded" aria-hidden="true">close</span>
				</button>
			</header>

			<div class="user-profile-modal__content">
				<h2 class="user-profile-modal__title" id="user-profile-title">Profile</h2>

				<div class="user-profile-modal__identity">
					<span class="material-symbols-rounded user-profile-modal__avatar" aria-hidden="true">account_circle</span>
					<p class="user-profile-modal__name"><?= htmlspecialchars($userProfileName, ENT_QUOTES, 'UTF-8') ?></p>
				</div>

				<dl class="user-profile-modal__details">
					<div class="user-profile-modal__detail-row">
						<dt>Email</dt>
						<dd><?= htmlspecialchars($userProfileEmail, ENT_QUOTES, 'UTF-8') ?></dd>
					</div>
					<div class="user-profile-modal__detail-row">
						<dt>Phone Number</dt>
						<dd><?= htmlspecialchars($userProfilePhone, ENT_QUOTES, 'UTF-8') ?></dd>
					</div>
					<div class="user-profile-modal__detail-row">
						<dt>Driver ID</dt>
						<dd><?= htmlspecialchars($userProfileDriversId, ENT_QUOTES, 'UTF-8') ?></dd>
					</div>
					<div class="user-profile-modal__detail-row">
						<dt>Date of Birth</dt>
						<dd><?= htmlspecialchars($userProfileDob, ENT_QUOTES, 'UTF-8') ?></dd>
					</div>
				</dl>

				<div class="user-profile-modal__actions">
					<button class="user-profile-modal__logout" type="button" data-modal-target="user-logout-modal">Logout</button>
					<a class="user-profile-modal__history" href="index.php?page=user-booking-history">View Booking History</a>
				</div>
			</div>

			<button class="menu-modal__back admin-modal__back" type="button" aria-label="Back to previous view" data-modal-back>
				<span class="material-symbols-rounded" aria-hidden="true">arrow_back</span>
			</button>
		</section>
	</div>

	<div class="menu-modal user-logout-modal" id="user-logout-modal" hidden aria-hidden="true" data-modal-id="user-logout-modal">
		<div class="menu-modal__overlay" data-modal-close></div>

		<section class="menu-modal__dialog admin-modal__dialog user-auth-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="user-logout-title">
			<header class="menu-modal__header user-auth-modal__header">
				<div class="menu-modal__brand" aria-label="Ridex user logout confirmation">
					<img
						src="images/ridex-header.png"
						alt="Ridex logo"
						class="menu-modal__logo"
						onerror="this.onerror=null;this.src='images/logo.svg';"
					/>
				</div>

				<button class="menu-modal__close" type="button" aria-label="Close user logout prompt" data-modal-close>
					<span class="material-symbols-rounded" aria-hidden="true">close</span>
				</button>
			</header>

			<div class="user-logout-modal__content">
				<span class="material-symbols-rounded user-logout-modal__icon" aria-hidden="true">delete</span>
				<p class="user-logout-modal__text" id="user-logout-title">Are you sure you want to logout? This action can’t be undone.</p>

				<div class="user-logout-modal__actions">
					<button class="user-logout-modal__cancel" type="button" data-modal-back>Cancel</button>
					<form class="user-logout-modal__form" method="post" action="index.php">
						<input type="hidden" name="action" value="user-logout" />
						<button class="user-logout-modal__confirm" type="submit">Logout</button>
					</form>
				</div>
			</div>

			<button class="menu-modal__back admin-modal__back" type="button" aria-label="Back to previous view" data-modal-back>
				<span class="material-symbols-rounded" aria-hidden="true">arrow_back</span>
			</button>
		</section>
	</div>
<?php endif; ?>

<?php if ($isAdminSession): ?>
	<?php // admin login: admin profile modal for authenticated admins ?>
	<div class="menu-modal admin-profile-modal" id="admin-profile-modal" hidden aria-hidden="true" data-modal-id="admin-profile-modal">
		<div class="menu-modal__overlay" data-modal-close></div>

		<section class="menu-modal__dialog admin-modal__dialog admin-profile-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="admin-profile-title">
			<header class="menu-modal__header">
				<div class="menu-modal__brand" aria-label="Ridex admin profile">
					<img
						src="images/ridex-header.png"
						alt="Ridex logo"
						class="menu-modal__logo"
						onerror="this.onerror=null;this.src='images/logo.svg';"
					/>
				</div>

				<button class="menu-modal__close" type="button" aria-label="Close profile" data-modal-close>
					<span class="material-symbols-rounded" aria-hidden="true">close</span>
				</button>
			</header>

			<div class="admin-profile-modal__content">
				<h2 class="admin-profile-modal__title" id="admin-profile-title">Profile</h2>

				<div class="admin-profile-modal__identity">
					<span class="material-symbols-rounded admin-profile-modal__avatar" aria-hidden="true">account_circle</span>
					<p class="admin-profile-modal__name"><?= htmlspecialchars($adminProfileName, ENT_QUOTES, 'UTF-8') ?></p>
				</div>

				<dl class="admin-profile-modal__details">
					<div class="admin-profile-modal__detail-row">
						<dt>Email</dt>
						<dd><?= htmlspecialchars($adminProfileEmail, ENT_QUOTES, 'UTF-8') ?></dd>
					</div>
					<div class="admin-profile-modal__detail-row">
						<dt>Phone Number</dt>
						<dd><?= htmlspecialchars($adminProfilePhone, ENT_QUOTES, 'UTF-8') ?></dd>
					</div>
				</dl>

				<a class="admin-profile-modal__dashboard" href="index.php?page=admin-dashboard">Admin Dashboard</a>
				<button class="admin-profile-modal__logout" type="button" data-modal-target="admin-logout-modal">Logout</button>
			</div>

			<button class="menu-modal__back admin-modal__back" type="button" aria-label="Back to previous view" data-modal-back>
				<span class="material-symbols-rounded" aria-hidden="true">arrow_back</span>
			</button>
		</section>
	</div>

	<?php // admin login: logout confirmation modal ?>
	<div class="menu-modal admin-logout-modal" id="admin-logout-modal" hidden aria-hidden="true" data-modal-id="admin-logout-modal">
		<div class="menu-modal__overlay" data-modal-close></div>

		<section class="menu-modal__dialog admin-modal__dialog admin-logout-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="admin-logout-title">
			<header class="menu-modal__header">
				<div class="menu-modal__brand" aria-label="Ridex logout confirmation">
					<img
						src="images/ridex-header.png"
						alt="Ridex logo"
						class="menu-modal__logo"
						onerror="this.onerror=null;this.src='images/logo.svg';"
					/>
				</div>

				<button class="menu-modal__close" type="button" aria-label="Close logout prompt" data-modal-close>
					<span class="material-symbols-rounded" aria-hidden="true">close</span>
				</button>
			</header>

			<div class="admin-logout-modal__content">
				<span class="material-symbols-rounded admin-logout-modal__icon" aria-hidden="true">delete</span>
				<p class="admin-logout-modal__text" id="admin-logout-title">Are you sure you want to logout? This action can’t be undone.</p>

				<div class="admin-logout-modal__actions">
					<button class="admin-logout-modal__cancel" type="button" data-modal-back>Cancel</button>
					<form class="admin-logout-modal__form" method="post" action="index.php">
						<input type="hidden" name="action" value="admin-logout" />
						<button class="admin-logout-modal__confirm" type="submit">Logout</button>
					</form>
				</div>
			</div>

			<button class="menu-modal__back admin-modal__back" type="button" aria-label="Back to previous view" data-modal-back>
				<span class="material-symbols-rounded" aria-hidden="true">arrow_back</span>
			</button>
		</section>
	</div>

	<?php // read modal reference: C:\Users\User\Downloads\Ridex includes the 5-status read examples, and available status has an extra maintenance icon. ?>
	<div class="menu-modal admin-vehicle-read-modal" id="admin-vehicle-read-modal" hidden aria-hidden="true" data-modal-id="admin-vehicle-read-modal">
		<div class="menu-modal__overlay" data-modal-close></div>

		<section class="menu-modal__dialog admin-modal__dialog admin-vehicle-read-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="admin-vehicle-read-title">
			<header class="menu-modal__header admin-vehicle-read-modal__header">
				<div class="menu-modal__brand" aria-label="Ridex vehicle details">
					<img
						src="images/ridex-header.png"
						alt="Ridex logo"
						class="menu-modal__logo"
						onerror="this.onerror=null;this.src='images/logo.svg';"
					/>
				</div>

				<?php // maintenance edit/fillup form: available-vehicle maintenance trigger icon opens maintenance fill modal. ?>
				<button
					class="admin-vehicle-read-modal__maintenance-indicator"
					type="button"
					aria-label="Open maintenance form"
					data-read-maintenance-indicator
					data-modal-target="admin-maintenance-fill-modal"
					hidden
				>
					<span class="material-symbols-rounded" aria-hidden="true">build</span>
				</button>
				<button class="menu-modal__close" type="button" aria-label="Close vehicle read prompt" data-modal-close>
					<span class="material-symbols-rounded" aria-hidden="true">close</span>
				</button>
			</header>

			<div class="admin-vehicle-read-modal__content">
				<div class="admin-vehicle-read-modal__headline">
					<div class="admin-vehicle-read-modal__booking-block">
						<p class="admin-vehicle-read-modal__booking-number">Booking Number: <span data-read-booking-number>N/A</span></p>
						<p class="admin-vehicle-read-modal__current-user" data-read-current-user-line>Current User: <span data-read-current-user>N/A</span></p>
					</div>
					<span class="admin-vehicle-read-modal__status-pill admin-vehicle-read-modal__status-pill--available" data-read-status-pill>Available</span>
				</div>

				<div class="admin-vehicle-read-modal__hero">
					<div class="admin-vehicle-read-modal__image-wrap">
						<img src="images/vehicle-feature.png" alt="Vehicle" class="admin-vehicle-read-modal__image" data-read-vehicle-image onerror="this.onerror=null;this.src='images/vehicle-feature.png';" />
					</div>

					<div class="admin-vehicle-read-modal__meta">
						<h2 class="admin-vehicle-read-modal__type" id="admin-vehicle-read-title" data-read-vehicle-type>Car</h2>
						<p class="admin-vehicle-read-modal__full-name" data-read-vehicle-full-name>Vehicle</p>

						<ul class="admin-vehicle-read-modal__specs" aria-label="Vehicle specifications">
							<li><span class="material-symbols-rounded" aria-hidden="true">person</span><span data-read-vehicle-seats>0 Seats</span></li>
							<li><span class="material-symbols-rounded" aria-hidden="true">directions_car</span><span data-read-vehicle-transmission>N/A</span></li>
							<li><span class="material-symbols-rounded" aria-hidden="true">badge</span><span data-read-vehicle-age>0+ Years</span></li>
							<li><span class="material-symbols-rounded" aria-hidden="true">local_gas_station</span><span data-read-vehicle-fuel>Fuel</span></li>
							<li><span class="material-symbols-rounded" aria-hidden="true">speed</span><span data-read-vehicle-mileage>Mileage N/A</span></li>
							<li><span class="material-symbols-rounded" aria-hidden="true">id_card</span><span data-read-vehicle-plate>N/A</span></li>
						</ul>
					</div>
				</div>

				<table class="admin-vehicle-read-modal__table" aria-label="Vehicle status details">
					<tbody data-read-status-rows>
						<tr>
							<th scope="row">Last Service Date</th>
							<td>unavailable</td>
						</tr>
					</tbody>
				</table>

				<div class="admin-vehicle-read-modal__actions">
					<?php // maintenance edit/fillup form: read modal edit action routes to maintenance edit modal when vehicle is in maintenance. ?>
					<button class="admin-vehicle-read-modal__edit" type="button" data-read-edit-action>Edit</button>
					<button
						class="admin-vehicle-read-modal__delete"
						type="button"
						data-modal-target="admin-delete-vehicle-modal"
						data-read-delete-action
						hidden
					>
						Delete
					</button>
				</div>
			</div>

			<button class="menu-modal__back admin-modal__back" type="button" aria-label="Back to previous view" data-modal-back>
				<span class="material-symbols-rounded" aria-hidden="true">arrow_back</span>
			</button>
		</section>
	</div>

	<?php // maintenance edit/fillup form: maintenance creation modal for transitioning an available vehicle to maintenance status. ?>
	<div class="menu-modal admin-maintenance-modal" id="admin-maintenance-fill-modal" hidden aria-hidden="true" data-modal-id="admin-maintenance-fill-modal">
		<div class="menu-modal__overlay" data-modal-close></div>

		<section class="menu-modal__dialog admin-modal__dialog admin-maintenance-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="admin-maintenance-fill-title">
			<header class="menu-modal__header admin-maintenance-modal__header">
				<div class="menu-modal__brand" aria-label="Ridex maintenance form">
					<img
						src="images/ridex-header.png"
						alt="Ridex logo"
						class="menu-modal__logo"
						onerror="this.onerror=null;this.src='images/logo.svg';"
					/>
				</div>

				<button class="menu-modal__close" type="button" aria-label="Close maintenance form" data-modal-close>
					<span class="material-symbols-rounded" aria-hidden="true">close</span>
				</button>
			</header>

			<div class="admin-maintenance-modal__content">
				<form class="admin-maintenance-modal__form" method="post" action="index.php" data-maintenance-fill-form>
					<input type="hidden" name="action" value="admin-start-maintenance" />
					<input type="hidden" name="vehicle_id" value="" data-maintenance-vehicle-id-input />
					<input type="hidden" name="fleet_mode" value="type" data-maintenance-fleet-mode-input />
					<input type="hidden" name="fleet_type" value="cars" data-maintenance-fleet-type-input />
					<input type="hidden" name="fleet_status" value="maintenance" data-maintenance-fleet-status-input />

					<label class="admin-maintenance-modal__label" for="maintenance-fill-issue">Issue Description</label>
					<input class="admin-maintenance-modal__input" type="text" id="maintenance-fill-issue" name="issue_description" maxlength="255" required data-maintenance-issue-input />

					<label class="admin-maintenance-modal__label" for="maintenance-fill-workshop">Workshop Name</label>
					<input class="admin-maintenance-modal__input" type="text" id="maintenance-fill-workshop" name="workshop_name" maxlength="150" required data-maintenance-workshop-input />

					<label class="admin-maintenance-modal__label" for="maintenance-fill-estimate">Est. Completion</label>
					<div class="admin-maintenance-modal__date-wrap">
						<input class="admin-maintenance-modal__input" type="text" id="maintenance-fill-estimate" name="estimate_completion_date" required inputmode="none" data-maintenance-estimate-input />
						<button class="booking-input__icon-button admin-maintenance-modal__date-button" type="button" aria-label="Open calendar" data-open-picker-for="maintenance-fill-estimate">
							<span class="material-symbols-rounded admin-maintenance-modal__date-icon" aria-hidden="true">calendar_month</span>
						</button>
					</div>

					<label class="admin-maintenance-modal__label" for="maintenance-fill-cost">Service Cost</label>
					<input class="admin-maintenance-modal__input" type="number" id="maintenance-fill-cost" name="service_cost" min="0" step="0.01" required data-maintenance-cost-input />

					<div class="admin-maintenance-modal__actions">
						<button class="admin-maintenance-modal__submit" type="submit">Update</button>
					</div>
				</form>
			</div>

			<button class="menu-modal__back admin-modal__back" type="button" aria-label="Back to previous view" data-modal-back>
				<span class="material-symbols-rounded" aria-hidden="true">arrow_back</span>
			</button>
		</section>
	</div>

	<?php // maintenance edit/fillup form: maintenance edit modal with update and complete service actions. ?>
	<div class="menu-modal admin-maintenance-modal" id="admin-maintenance-edit-modal" hidden aria-hidden="true" data-modal-id="admin-maintenance-edit-modal">
		<div class="menu-modal__overlay" data-modal-close></div>

		<section class="menu-modal__dialog admin-modal__dialog admin-maintenance-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="admin-maintenance-edit-title">
			<header class="menu-modal__header admin-maintenance-modal__header">
				<div class="menu-modal__brand" aria-label="Ridex maintenance edit form">
					<img
						src="images/ridex-header.png"
						alt="Ridex logo"
						class="menu-modal__logo"
						onerror="this.onerror=null;this.src='images/logo.svg';"
					/>
				</div>

				<button class="menu-modal__close" type="button" aria-label="Close maintenance edit form" data-modal-close>
					<span class="material-symbols-rounded" aria-hidden="true">close</span>
				</button>
			</header>

			<div class="admin-maintenance-modal__content">
				<form class="admin-maintenance-modal__form" method="post" action="index.php" id="admin-maintenance-edit-form" data-maintenance-edit-form>
					<input type="hidden" name="action" value="admin-update-maintenance" />
					<input type="hidden" name="vehicle_id" value="" data-maintenance-edit-vehicle-id-input />
					<input type="hidden" name="fleet_mode" value="status" data-maintenance-edit-fleet-mode-input />
					<input type="hidden" name="fleet_type" value="cars" data-maintenance-edit-fleet-type-input />
					<input type="hidden" name="fleet_status" value="maintenance" data-maintenance-edit-fleet-status-input />

					<label class="admin-maintenance-modal__label" for="maintenance-edit-issue">Issue Description</label>
					<input class="admin-maintenance-modal__input" type="text" id="maintenance-edit-issue" name="issue_description" maxlength="255" required data-maintenance-edit-issue-input />

					<label class="admin-maintenance-modal__label" for="maintenance-edit-workshop">Workshop Name</label>
					<input class="admin-maintenance-modal__input" type="text" id="maintenance-edit-workshop" name="workshop_name" maxlength="150" required data-maintenance-edit-workshop-input />

					<label class="admin-maintenance-modal__label" for="maintenance-edit-estimate">Est. Completion</label>
					<div class="admin-maintenance-modal__date-wrap">
						<input class="admin-maintenance-modal__input" type="text" id="maintenance-edit-estimate" name="estimate_completion_date" required inputmode="none" data-maintenance-edit-estimate-input />
						<button class="booking-input__icon-button admin-maintenance-modal__date-button" type="button" aria-label="Open calendar" data-open-picker-for="maintenance-edit-estimate">
							<span class="material-symbols-rounded admin-maintenance-modal__date-icon" aria-hidden="true">calendar_month</span>
						</button>
					</div>

					<label class="admin-maintenance-modal__label" for="maintenance-edit-cost">Service Cost</label>
					<input class="admin-maintenance-modal__input" type="number" id="maintenance-edit-cost" name="service_cost" min="0" step="0.01" required data-maintenance-edit-cost-input />

				</form>

				<div class="admin-maintenance-modal__actions admin-maintenance-modal__actions--dual">
					<form id="admin-maintenance-complete-form" method="post" action="index.php" class="admin-maintenance-modal__complete-form">
						<input type="hidden" name="action" value="admin-complete-maintenance" />
						<input type="hidden" name="vehicle_id" value="" data-maintenance-complete-vehicle-id-input />
						<input type="hidden" name="fleet_mode" value="status" data-maintenance-complete-fleet-mode-input />
						<input type="hidden" name="fleet_type" value="cars" data-maintenance-complete-fleet-type-input />
						<input type="hidden" name="fleet_status" value="maintenance" data-maintenance-complete-fleet-status-input />
						<button class="admin-maintenance-modal__complete" type="submit">Complete Service</button>
					</form>
					<button class="admin-maintenance-modal__submit" type="submit" form="admin-maintenance-edit-form">Update</button>
				</div>
			</div>

			<button class="menu-modal__back admin-modal__back" type="button" aria-label="Back to previous view" data-modal-back>
				<span class="material-symbols-rounded" aria-hidden="true">arrow_back</span>
			</button>
		</section>
	</div>

	<?php // create vehicle modal: three-part admin create flow (part 1/2/3) using existing modal system and required fields before submission. ?>
	<div class="menu-modal admin-create-vehicle-modal" id="admin-create-vehicle-modal" hidden aria-hidden="true" data-modal-id="admin-create-vehicle-modal">
		<div class="menu-modal__overlay" data-modal-close></div>

		<section class="menu-modal__dialog admin-modal__dialog admin-create-vehicle-modal__dialog" role="dialog" aria-modal="true" aria-label="Create vehicle form">
			<header class="menu-modal__header admin-create-vehicle-modal__header">
				<div class="menu-modal__brand" aria-label="Ridex create vehicle form">
					<img
						src="images/ridex-header.png"
						alt="Ridex logo"
						class="menu-modal__logo"
						onerror="this.onerror=null;this.src='images/logo.svg';"
					/>
				</div>

				<button class="menu-modal__close" type="button" aria-label="Close create vehicle form" data-modal-close>
					<span class="material-symbols-rounded" aria-hidden="true">close</span>
				</button>
			</header>

			<div class="admin-create-vehicle-modal__content">
				<form class="admin-create-vehicle-modal__form" method="post" action="index.php" enctype="multipart/form-data" data-admin-create-vehicle-form>
					<input type="hidden" name="action" value="admin-create-vehicle" />
					<input type="hidden" name="fleet_mode" value="type" data-create-fleet-mode-input />
					<input type="hidden" name="fleet_type" value="cars" data-create-fleet-type-input />

					<section class="admin-create-vehicle-modal__step is-active" data-create-step-panel="1" aria-label="Create vehicle part 1">
						<label class="admin-create-vehicle-modal__label" for="create-vehicle-type">Vehicle Type</label>
						<select class="admin-create-vehicle-modal__input admin-create-vehicle-modal__select" id="create-vehicle-type" name="vehicle_type" required data-create-vehicle-type-input>
							<option value="cars">Car</option>
							<option value="bikes">Bike</option>
							<option value="luxury">Luxury</option>
						</select>

						<label class="admin-create-vehicle-modal__label" for="create-vehicle-full-name">Vehicle Full Name</label>
						<input class="admin-create-vehicle-modal__input" type="text" id="create-vehicle-full-name" name="full_name" maxlength="150" required />

						<label class="admin-create-vehicle-modal__label" for="create-vehicle-name">Vehicle Name</label>
						<input class="admin-create-vehicle-modal__input" type="text" id="create-vehicle-name" name="short_name" maxlength="100" required />

						<label class="admin-create-vehicle-modal__label" for="create-vehicle-price">Price Per Day</label>
						<input class="admin-create-vehicle-modal__input" type="number" id="create-vehicle-price" name="price_per_day" min="1" step="0.01" required />

						<label class="admin-create-vehicle-modal__label" for="create-vehicle-driver-age">Driver's ID</label>
						<select class="admin-create-vehicle-modal__input admin-create-vehicle-modal__select" id="create-vehicle-driver-age" name="driver_age_requirement" required>
							<option value="18">18+</option>
							<option value="21">21+</option>
						</select>

						<div class="admin-create-vehicle-modal__footer">
							<button class="menu-modal__back admin-create-vehicle-modal__nav" type="button" aria-label="Close create vehicle form" data-create-step-close>
								<span class="material-symbols-rounded" aria-hidden="true">arrow_back</span>
							</button>
							<button class="menu-modal__back admin-create-vehicle-modal__nav" type="button" aria-label="Go to create vehicle part 2" data-create-step-next>
								<span class="material-symbols-rounded" aria-hidden="true">arrow_forward</span>
							</button>
						</div>
					</section>

					<section class="admin-create-vehicle-modal__step" data-create-step-panel="2" aria-label="Create vehicle part 2" hidden>
						<label class="admin-create-vehicle-modal__label" for="create-vehicle-image">Upload Images (max 5)</label>
						<div class="admin-create-vehicle-modal__image-section" data-create-image-section>
							<div class="admin-create-vehicle-modal__file-fields" data-create-image-fields>
								<div class="admin-create-vehicle-modal__file-wrap" data-image-field-row>
									<input class="admin-create-vehicle-modal__input admin-create-vehicle-modal__input--file-name" type="text" id="create-vehicle-image-name" readonly aria-hidden="true" tabindex="-1" data-image-name-input />
									<button class="admin-create-vehicle-modal__file-clear" type="button" aria-label="Clear selected image" data-image-clear-button hidden>
										<span class="material-symbols-rounded" aria-hidden="true">close</span>
									</button>
									<button class="admin-create-vehicle-modal__file-add" type="button" aria-label="Add another image field" data-image-add-button>
										<span class="material-symbols-rounded" aria-hidden="true">add</span>
									</button>
									<input class="admin-create-vehicle-modal__file-input" type="file" id="create-vehicle-image" name="image_files[]" accept="image/png,image/jpeg,image/jpg,image/webp" required data-image-file-input />
								</div>
							</div>
						</div>

						<div class="admin-create-vehicle-modal__field-zone" data-create-field-zone="2"></div>

						<div class="admin-create-vehicle-modal__footer">
							<button class="menu-modal__back admin-create-vehicle-modal__nav" type="button" aria-label="Go back to create vehicle part 1" data-create-step-prev>
								<span class="material-symbols-rounded" aria-hidden="true">arrow_back</span>
							</button>
							<button class="menu-modal__back admin-create-vehicle-modal__nav" type="button" aria-label="Go to create vehicle part 3" data-create-step-next>
								<span class="material-symbols-rounded" aria-hidden="true">arrow_forward</span>
							</button>
						</div>
					</section>

					<section class="admin-create-vehicle-modal__step" data-create-step-panel="3" aria-label="Create vehicle part 3" hidden>
						<div class="admin-create-vehicle-modal__field-zone" data-create-field-zone="3"></div>

						<div class="admin-create-vehicle-modal__footer">
							<button class="menu-modal__back admin-create-vehicle-modal__nav" type="button" aria-label="Go back to create vehicle part 2" data-create-step-prev>
								<span class="material-symbols-rounded" aria-hidden="true">arrow_back</span>
							</button>
							<button class="menu-modal__back admin-create-vehicle-modal__nav" type="button" aria-label="Go to create vehicle part 4" data-create-step-next data-create-step3-next>
								<span class="material-symbols-rounded" aria-hidden="true">arrow_forward</span>
							</button>
							<button class="admin-create-vehicle-modal__submit" type="submit" data-create-step3-submit hidden>Create</button>
						</div>
					</section>

					<section class="admin-create-vehicle-modal__step" data-create-step-panel="4" aria-label="Create vehicle part 4" hidden>
						<div class="admin-create-vehicle-modal__field-zone" data-create-field-zone="4"></div>

						<div class="admin-create-vehicle-modal__footer admin-create-vehicle-modal__footer--submit">
							<button class="menu-modal__back admin-create-vehicle-modal__nav" type="button" aria-label="Go back to create vehicle part 3" data-create-step-prev>
								<span class="material-symbols-rounded" aria-hidden="true">arrow_back</span>
							</button>
							<button class="admin-create-vehicle-modal__submit" type="submit" data-create-step4-submit>Create</button>
						</div>
					</section>

					<div class="admin-create-vehicle-modal__field-pool" data-create-field-pool hidden>
						<div class="admin-create-vehicle-modal__field-block" data-field-block data-field-key="seats">
							<label class="admin-create-vehicle-modal__label" for="create-vehicle-seats">Seats</label>
							<input class="admin-create-vehicle-modal__input" type="number" id="create-vehicle-seats" name="number_of_seats" min="1" max="12" required />
						</div>
						<div class="admin-create-vehicle-modal__field-block" data-field-block data-field-key="transmission">
							<label class="admin-create-vehicle-modal__label" for="create-vehicle-transmission">Transmission</label>
							<select class="admin-create-vehicle-modal__input admin-create-vehicle-modal__select" id="create-vehicle-transmission" name="transmission_type" required>
								<option value="manual">Manual</option>
								<option value="automatic">Automatic</option>
								<option value="hybrid">Hybrid</option>
								<option value="N/A">N/A</option>
							</select>
						</div>
						<div class="admin-create-vehicle-modal__field-block" data-field-block data-field-key="fuel">
							<label class="admin-create-vehicle-modal__label" for="create-vehicle-fuel">Fuel Type</label>
							<select class="admin-create-vehicle-modal__input admin-create-vehicle-modal__select" id="create-vehicle-fuel" name="fuel_type" required>
								<option value="petrol">Petrol</option>
								<option value="diesel">Diesel</option>
								<option value="electric">Electric</option>
							</select>
						</div>
						<div class="admin-create-vehicle-modal__field-block" data-field-block data-field-key="mileage">
							<label class="admin-create-vehicle-modal__label" for="create-vehicle-mileage">Mileage (km/L)</label>
							<input class="admin-create-vehicle-modal__input" type="number" id="create-vehicle-mileage" name="mileage_km_per_l" min="0.01" step="0.01" required />
						</div>
						<div class="admin-create-vehicle-modal__field-block" data-field-block data-field-key="license">
							<label class="admin-create-vehicle-modal__label" for="create-vehicle-license">License Plate No.</label>
							<input class="admin-create-vehicle-modal__input" type="text" id="create-vehicle-license" name="license_plate" maxlength="50" required />
						</div>
						<div class="admin-create-vehicle-modal__field-block" data-field-block data-field-key="gps">
							<label class="admin-create-vehicle-modal__label" for="create-vehicle-gps-id">GPS ID</label>
							<input class="admin-create-vehicle-modal__input" type="text" id="create-vehicle-gps-id" name="gps_id" maxlength="50" required />
						</div>
						<div class="admin-create-vehicle-modal__field-block" data-field-block data-field-key="status">
							<label class="admin-create-vehicle-modal__label" for="create-vehicle-status">Initial Vehicle Status</label>
							<select class="admin-create-vehicle-modal__input admin-create-vehicle-modal__select" id="create-vehicle-status" name="status" required>
								<option value="available">Available</option>
								<option value="reserved">Reserved</option>
								<option value="on_trip">On Trip</option>
								<option value="overdue">Overdue</option>
								<option value="maintenance">Maintenance</option>
							</select>
						</div>
						<div class="admin-create-vehicle-modal__field-block" data-field-block data-field-key="last-service">
							<label class="admin-create-vehicle-modal__label" for="create-vehicle-last-service-date">Last Service Date</label>
							<div class="admin-create-vehicle-modal__date-wrap">
								<input class="admin-create-vehicle-modal__input" type="text" id="create-vehicle-last-service-date" name="last_service_date" required inputmode="none" data-create-last-service-input />
								<button class="booking-input__icon-button admin-create-vehicle-modal__date-button" type="button" aria-label="Open calendar" data-open-picker-for="create-vehicle-last-service-date">
									<span class="material-symbols-rounded admin-create-vehicle-modal__date-icon" aria-hidden="true">calendar_month</span>
								</button>
							</div>
						</div>
						<div class="admin-create-vehicle-modal__field-block" data-field-block data-field-key="description">
							<label class="admin-create-vehicle-modal__label" for="create-vehicle-description">Vehicle Description (Detailed)</label>
							<textarea class="admin-create-vehicle-modal__textarea" id="create-vehicle-description" name="description" maxlength="2000" required></textarea>
						</div>
					</div>
				</form>
			</div>
		</section>
	</div>

	<?php // edit vehicle modal: three-part admin edit flow matching create modal layout with prefilled vehicle data and update action. ?>
	<div class="menu-modal admin-edit-vehicle-modal" id="admin-edit-vehicle-modal" hidden aria-hidden="true" data-modal-id="admin-edit-vehicle-modal">
		<div class="menu-modal__overlay" data-modal-close></div>

		<section class="menu-modal__dialog admin-modal__dialog admin-create-vehicle-modal__dialog" role="dialog" aria-modal="true" aria-label="Edit vehicle form">
			<header class="menu-modal__header admin-create-vehicle-modal__header">
				<div class="menu-modal__brand" aria-label="Ridex edit vehicle form">
					<img
						src="images/ridex-header.png"
						alt="Ridex logo"
						class="menu-modal__logo"
						onerror="this.onerror=null;this.src='images/logo.svg';"
					/>
				</div>

				<button class="menu-modal__close" type="button" aria-label="Close edit vehicle form" data-modal-close>
					<span class="material-symbols-rounded" aria-hidden="true">close</span>
				</button>
			</header>

			<div class="admin-create-vehicle-modal__content">
				<form class="admin-create-vehicle-modal__form" method="post" action="index.php" enctype="multipart/form-data" data-admin-edit-vehicle-form>
					<input type="hidden" name="action" value="admin-update-vehicle" />
					<input type="hidden" name="vehicle_id" value="" data-edit-vehicle-id-input />
					<input type="hidden" name="current_image_path" value="" data-edit-current-image-path-input />
						<input type="hidden" name="remove_current_image" value="0" data-edit-remove-current-image-input />
						<input type="hidden" name="replace_primary_image" value="0" data-edit-replace-primary-image-input />
					<input type="hidden" name="fleet_mode" value="type" data-edit-fleet-mode-input />
					<input type="hidden" name="fleet_type" value="cars" data-edit-fleet-type-input />
					<input type="hidden" name="fleet_status" value="reserved" data-edit-fleet-status-input />

					<section class="admin-create-vehicle-modal__step is-active" data-edit-step-panel="1" aria-label="Edit vehicle part 1">
						<label class="admin-create-vehicle-modal__label" for="edit-vehicle-type">Vehicle Type</label>
						<select class="admin-create-vehicle-modal__input admin-create-vehicle-modal__select" id="edit-vehicle-type" name="vehicle_type" required data-edit-vehicle-type-input>
							<option value="cars">Car</option>
							<option value="bikes">Bike</option>
							<option value="luxury">Luxury</option>
						</select>

						<label class="admin-create-vehicle-modal__label" for="edit-vehicle-full-name">Vehicle Full Name</label>
						<input class="admin-create-vehicle-modal__input" type="text" id="edit-vehicle-full-name" name="full_name" maxlength="150" required data-edit-vehicle-full-name-input />

						<label class="admin-create-vehicle-modal__label" for="edit-vehicle-name">Vehicle Name</label>
						<input class="admin-create-vehicle-modal__input" type="text" id="edit-vehicle-name" name="short_name" maxlength="100" required data-edit-vehicle-short-name-input />

						<label class="admin-create-vehicle-modal__label" for="edit-vehicle-price">Price Per Day</label>
						<input class="admin-create-vehicle-modal__input" type="number" id="edit-vehicle-price" name="price_per_day" min="1" step="0.01" required data-edit-vehicle-price-input />

						<label class="admin-create-vehicle-modal__label" for="edit-vehicle-driver-age">Driver's ID</label>
						<select class="admin-create-vehicle-modal__input admin-create-vehicle-modal__select" id="edit-vehicle-driver-age" name="driver_age_requirement" required data-edit-vehicle-driver-age-input>
							<option value="18">18+</option>
							<option value="21">21+</option>
						</select>

						<div class="admin-create-vehicle-modal__footer">
							<button class="menu-modal__back admin-create-vehicle-modal__nav" type="button" aria-label="Close edit vehicle form" data-edit-step-close>
								<span class="material-symbols-rounded" aria-hidden="true">arrow_back</span>
							</button>
							<button class="menu-modal__back admin-create-vehicle-modal__nav" type="button" aria-label="Go to edit vehicle part 2" data-edit-step-next>
								<span class="material-symbols-rounded" aria-hidden="true">arrow_forward</span>
							</button>
						</div>
					</section>

					<section class="admin-create-vehicle-modal__step" data-edit-step-panel="2" aria-label="Edit vehicle part 2" hidden>
						<label class="admin-create-vehicle-modal__label" for="edit-vehicle-image">Upload New Images (max 5 total)</label>
						<div class="admin-create-vehicle-modal__image-section" data-edit-image-section>
							<div class="admin-create-vehicle-modal__file-fields" data-edit-image-fields>
								<div class="admin-create-vehicle-modal__file-wrap" data-image-field-row>
									<input class="admin-create-vehicle-modal__input admin-create-vehicle-modal__input--file-name" type="text" id="edit-vehicle-image-name" readonly aria-hidden="true" tabindex="-1" data-edit-image-name-input data-image-name-input />
									<button class="admin-create-vehicle-modal__file-clear" type="button" aria-label="Clear selected image" data-image-clear-button hidden>
										<span class="material-symbols-rounded" aria-hidden="true">close</span>
									</button>
									<button class="admin-create-vehicle-modal__file-add" type="button" aria-label="Add another image field" data-image-add-button>
										<span class="material-symbols-rounded" aria-hidden="true">add</span>
									</button>
									<input class="admin-create-vehicle-modal__file-input" type="file" id="edit-vehicle-image" name="image_files[]" accept="image/png,image/jpeg,image/jpg,image/webp" data-image-file-input />
								</div>
							</div>
						</div>

						<div class="admin-create-vehicle-modal__field-zone" data-edit-field-zone="2"></div>

						<div class="admin-create-vehicle-modal__footer">
							<button class="menu-modal__back admin-create-vehicle-modal__nav" type="button" aria-label="Go back to edit vehicle part 1" data-edit-step-prev>
								<span class="material-symbols-rounded" aria-hidden="true">arrow_back</span>
							</button>
							<button class="menu-modal__back admin-create-vehicle-modal__nav" type="button" aria-label="Go to edit vehicle part 3" data-edit-step-next>
								<span class="material-symbols-rounded" aria-hidden="true">arrow_forward</span>
							</button>
						</div>
					</section>

					<section class="admin-create-vehicle-modal__step" data-edit-step-panel="3" aria-label="Edit vehicle part 3" hidden>
						<div class="admin-create-vehicle-modal__field-zone" data-edit-field-zone="3"></div>

						<div class="admin-create-vehicle-modal__footer">
							<button class="menu-modal__back admin-create-vehicle-modal__nav" type="button" aria-label="Go back to edit vehicle part 2" data-edit-step-prev>
								<span class="material-symbols-rounded" aria-hidden="true">arrow_back</span>
							</button>
							<button class="menu-modal__back admin-create-vehicle-modal__nav" type="button" aria-label="Go to edit vehicle part 4" data-edit-step-next data-edit-step3-next>
								<span class="material-symbols-rounded" aria-hidden="true">arrow_forward</span>
							</button>
							<button class="admin-create-vehicle-modal__submit" type="submit" data-edit-step3-submit hidden>Update</button>
						</div>
					</section>

					<section class="admin-create-vehicle-modal__step" data-edit-step-panel="4" aria-label="Edit vehicle part 4" hidden>
						<div class="admin-create-vehicle-modal__field-zone" data-edit-field-zone="4"></div>

						<div class="admin-create-vehicle-modal__footer admin-create-vehicle-modal__footer--submit">
							<button class="menu-modal__back admin-create-vehicle-modal__nav" type="button" aria-label="Go back to edit vehicle part 3" data-edit-step-prev>
								<span class="material-symbols-rounded" aria-hidden="true">arrow_back</span>
							</button>
							<button class="admin-create-vehicle-modal__submit" type="submit" data-edit-step4-submit>Update</button>
						</div>
					</section>

					<div class="admin-create-vehicle-modal__field-pool" data-edit-field-pool hidden>
						<div class="admin-create-vehicle-modal__field-block" data-field-block data-field-key="seats">
							<label class="admin-create-vehicle-modal__label" for="edit-vehicle-seats">Seats</label>
							<input class="admin-create-vehicle-modal__input" type="number" id="edit-vehicle-seats" name="number_of_seats" min="1" max="12" required data-edit-vehicle-seats-input />
						</div>
						<div class="admin-create-vehicle-modal__field-block" data-field-block data-field-key="transmission">
							<label class="admin-create-vehicle-modal__label" for="edit-vehicle-transmission">Transmission</label>
							<select class="admin-create-vehicle-modal__input admin-create-vehicle-modal__select" id="edit-vehicle-transmission" name="transmission_type" required data-edit-vehicle-transmission-input>
								<option value="manual">Manual</option>
								<option value="automatic">Automatic</option>
								<option value="hybrid">Hybrid</option>
								<option value="N/A">N/A</option>
							</select>
						</div>
						<div class="admin-create-vehicle-modal__field-block" data-field-block data-field-key="fuel">
							<label class="admin-create-vehicle-modal__label" for="edit-vehicle-fuel">Fuel Type</label>
							<select class="admin-create-vehicle-modal__input admin-create-vehicle-modal__select" id="edit-vehicle-fuel" name="fuel_type" required data-edit-vehicle-fuel-input>
								<option value="petrol">Petrol</option>
								<option value="diesel">Diesel</option>
								<option value="electric">Electric</option>
							</select>
						</div>
						<div class="admin-create-vehicle-modal__field-block" data-field-block data-field-key="mileage">
							<label class="admin-create-vehicle-modal__label" for="edit-vehicle-mileage">Mileage (km/L)</label>
							<input class="admin-create-vehicle-modal__input" type="number" id="edit-vehicle-mileage" name="mileage_km_per_l" min="0.01" step="0.01" required data-edit-vehicle-mileage-input />
						</div>
						<div class="admin-create-vehicle-modal__field-block" data-field-block data-field-key="license">
							<label class="admin-create-vehicle-modal__label" for="edit-vehicle-license">License Plate No.</label>
							<input class="admin-create-vehicle-modal__input" type="text" id="edit-vehicle-license" name="license_plate" maxlength="50" required data-edit-vehicle-license-input />
						</div>
						<div class="admin-create-vehicle-modal__field-block" data-field-block data-field-key="gps">
							<label class="admin-create-vehicle-modal__label" for="edit-vehicle-gps-id">GPS ID</label>
							<input class="admin-create-vehicle-modal__input" type="text" id="edit-vehicle-gps-id" name="gps_id" maxlength="50" required data-edit-vehicle-gps-id-input />
						</div>
						<div class="admin-create-vehicle-modal__field-block" data-field-block data-field-key="status">
							<label class="admin-create-vehicle-modal__label" for="edit-vehicle-status">Vehicle Status</label>
							<select class="admin-create-vehicle-modal__input admin-create-vehicle-modal__select" id="edit-vehicle-status" name="status" required data-edit-vehicle-status-input>
								<option value="available">Available</option>
								<option value="reserved">Reserved</option>
								<option value="on_trip">On Trip</option>
								<option value="overdue">Overdue</option>
								<option value="maintenance">Maintenance</option>
							</select>
						</div>
						<div class="admin-create-vehicle-modal__field-block" data-field-block data-field-key="last-service">
							<label class="admin-create-vehicle-modal__label" for="edit-vehicle-last-service-date">Last Service Date</label>
							<div class="admin-create-vehicle-modal__date-wrap">
								<input class="admin-create-vehicle-modal__input" type="text" id="edit-vehicle-last-service-date" name="last_service_date" required inputmode="none" data-edit-last-service-input />
								<button class="booking-input__icon-button admin-create-vehicle-modal__date-button" type="button" aria-label="Open calendar" data-open-picker-for="edit-vehicle-last-service-date">
									<span class="material-symbols-rounded admin-create-vehicle-modal__date-icon" aria-hidden="true">calendar_month</span>
								</button>
							</div>
						</div>
						<div class="admin-create-vehicle-modal__field-block" data-field-block data-field-key="description">
							<label class="admin-create-vehicle-modal__label" for="edit-vehicle-description">Vehicle Description (Detailed)</label>
							<textarea class="admin-create-vehicle-modal__textarea" id="edit-vehicle-description" name="description" maxlength="2000" required data-edit-vehicle-description-input></textarea>
						</div>
					</div>

				</form>
			</div>
		</section>
	</div>

	<?php // admin fleet delete: vehicle delete confirmation modal (same sizing/layout as logout modal). ?>
	<div class="menu-modal admin-logout-modal" id="admin-delete-vehicle-modal" hidden aria-hidden="true" data-modal-id="admin-delete-vehicle-modal">
		<div class="menu-modal__overlay" data-modal-close></div>

		<section class="menu-modal__dialog admin-modal__dialog admin-logout-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="admin-delete-vehicle-title">
			<header class="menu-modal__header">
				<div class="menu-modal__brand" aria-label="Ridex delete vehicle confirmation">
					<img
						src="images/ridex-header.png"
						alt="Ridex logo"
						class="menu-modal__logo"
						onerror="this.onerror=null;this.src='images/logo.svg';"
					/>
				</div>

				<button class="menu-modal__close" type="button" aria-label="Close delete vehicle prompt" data-modal-close>
					<span class="material-symbols-rounded" aria-hidden="true">close</span>
				</button>
			</header>

			<div class="admin-logout-modal__content">
				<span class="material-symbols-rounded admin-logout-modal__icon" aria-hidden="true">delete</span>
				<p class="admin-logout-modal__text" id="admin-delete-vehicle-title">
					Are you sure you want to delete <span data-delete-vehicle-name>this vehicle</span>? This action can’t be undone.
				</p>

				<div class="admin-logout-modal__actions">
					<button class="admin-logout-modal__cancel" type="button" data-modal-back>Cancel</button>
					<form class="admin-logout-modal__form" method="post" action="index.php">
						<input type="hidden" name="action" value="admin-delete-vehicle" />
						<input type="hidden" name="vehicle_id" value="" data-delete-vehicle-id-input />
						<input type="hidden" name="fleet_mode" value="type" data-delete-fleet-mode-input />
						<input type="hidden" name="fleet_type" value="cars" data-delete-fleet-type-input />
						<input type="hidden" name="fleet_status" value="reserved" data-delete-fleet-status-input />
						<button class="admin-logout-modal__confirm" type="submit">Delete</button>
					</form>
				</div>
			</div>

			<button class="menu-modal__back admin-modal__back" type="button" aria-label="Back to previous view" data-modal-back>
				<span class="material-symbols-rounded" aria-hidden="true">arrow_back</span>
			</button>
		</section>
	</div>
<?php endif; ?>

<?php // admin login: admin authentication modal ?>
<div class="menu-modal admin-login-modal" id="admin-login-modal" hidden aria-hidden="true" data-modal-id="admin-login-modal" data-open-on-load="<?= $adminLoginError !== '' ? 'true' : 'false' ?>">
	<div class="menu-modal__overlay" data-modal-close></div>

	<section class="menu-modal__dialog admin-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="admin-login-title">
		<header class="menu-modal__header">
			<div class="menu-modal__brand" aria-label="Ridex admin login">
				<img
					src="images/ridex-header.png"
					alt="Ridex logo"
					class="menu-modal__logo"
					onerror="this.onerror=null;this.src='images/logo.svg';"
				/>
			</div>

			<button class="menu-modal__close" type="button" aria-label="Close admin login" data-modal-close>
				<span class="material-symbols-rounded" aria-hidden="true">close</span>
			</button>
		</header>

		<div class="admin-login-modal__content">
			<h2 class="admin-login-modal__title" id="admin-login-title">Log In</h2>

			<form class="admin-login-form" method="post" action="index.php" autocomplete="off" data-admin-login-form>
				<input type="hidden" name="action" value="admin-login" />

				<label class="admin-login-form__label" for="admin-email-input">Administrative Email</label>
				<input
					class="admin-login-form__input<?= $adminLoginEmailInvalid ? ' admin-login-form__input--error' : '' ?>"
					type="email"
					id="admin-email-input"
					name="admin_email"
					placeholder="info@ridex.com"
					autocomplete="off"
					spellcheck="false"
					data-admin-login-input
					required
					value="<?= htmlspecialchars($adminLoginEmail, ENT_QUOTES, 'UTF-8') ?>"
				/>

				<label class="admin-login-form__label" for="admin-password-input">Password</label>
				<div class="admin-login-form__password-wrap">
					<input
						class="admin-login-form__input<?= $adminLoginPasswordInvalid ? ' admin-login-form__input--error' : '' ?>"
						type="password"
						id="admin-password-input"
						name="admin_password"
						placeholder="Enter your password"
						autocomplete="off"
						data-admin-login-input
						required
						minlength="8"
					/>
					<button
						class="admin-login-form__password-toggle"
						type="button"
						aria-label="Show password"
						data-password-toggle
						data-password-target="admin-password-input"
					>
						<span class="material-symbols-rounded" aria-hidden="true">visibility</span>
					</button>
				</div>

				<button class="admin-login-form__forgot" type="button" data-modal-target="admin-reset-modal">Forgot your password?</button>

				<?php if ($adminLoginError !== ''): ?>
					<p class="admin-login-form__error" role="alert" data-admin-login-error><?= htmlspecialchars($adminLoginError, ENT_QUOTES, 'UTF-8') ?></p>
				<?php endif; ?>

				<button class="admin-login-form__submit" type="submit">Log In</button>
			</form>
		</div>

		<button class="menu-modal__back admin-modal__back" type="button" aria-label="Back to previous view" data-modal-back>
			<span class="material-symbols-rounded" aria-hidden="true">arrow_back</span>
		</button>
	</section>
</div>

<?php // admin login: forgot-password confirmation modal ?>
<div class="menu-modal admin-reset-modal" id="admin-reset-modal" hidden aria-hidden="true" data-modal-id="admin-reset-modal">
	<div class="menu-modal__overlay" data-modal-close></div>

	<section class="menu-modal__dialog admin-modal__dialog admin-reset-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="admin-reset-title">
		<header class="menu-modal__header">
			<div class="menu-modal__brand" aria-label="Ridex password reset">
				<img
					src="images/ridex-header.png"
					alt="Ridex logo"
					class="menu-modal__logo"
					onerror="this.onerror=null;this.src='images/logo.svg';"
				/>
			</div>

			<button class="menu-modal__close" type="button" aria-label="Close reset prompt" data-modal-close>
				<span class="material-symbols-rounded" aria-hidden="true">close</span>
			</button>
		</header>

		<div class="admin-reset-modal__content">
			<span class="material-symbols-rounded admin-reset-modal__icon" aria-hidden="true">mail</span>
			<h2 class="admin-reset-modal__title" id="admin-reset-title">Check your inbox</h2>
			<p class="admin-reset-modal__text">
				You should receive an email in the next few minutes with a link to reset your password.
			</p>
			<a class="admin-reset-modal__submit" href="index.php">Reset Password</a>
		</div>

		<button class="menu-modal__back admin-modal__back" type="button" aria-label="Back to previous view" data-modal-back>
			<span class="material-symbols-rounded" aria-hidden="true">arrow_back</span>
		</button>
	</section>
</div>