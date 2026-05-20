<?php
$resetToken = trim((string) ($resetToken ?? ''));
$resetErrors = isset($resetErrors) && is_array($resetErrors) ? $resetErrors : [];
$isResetValid = (bool) ($isResetValid ?? false);
$isResetSuccess = (bool) ($isResetSuccess ?? false);
$resetMessage = trim((string) ($resetMessage ?? ''));
$resetRole = strtolower(trim((string) ($resetRole ?? 'user')));
$resetRole = in_array($resetRole, ['user', 'admin'], true) ? $resetRole : 'user';
?>

<section class="user-auth-status-page">
	<div class="user-auth-status-card">
		<span class="material-symbols-rounded user-auth-status-card__icon" aria-hidden="true"><?= $isResetSuccess ? 'check_circle' : 'lock_reset' ?></span>
		<h1 class="user-auth-status-card__title"><?= $isResetSuccess ? 'Password Updated' : ($resetRole === 'admin' ? 'Reset Admin Password' : 'Reset Password') ?></h1>

		<?php if ($resetMessage !== ''): ?>
			<p class="user-auth-status-card__text"><?= htmlspecialchars($resetMessage, ENT_QUOTES, 'UTF-8') ?></p>
		<?php endif; ?>

		<?php if (!empty($resetErrors)): ?>
			<div class="user-auth-status-card__errors" role="alert">
				<?php foreach ($resetErrors as $resetError): ?>
					<p><?= htmlspecialchars((string) $resetError, ENT_QUOTES, 'UTF-8') ?></p>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ($isResetValid && !$isResetSuccess): ?>
			<form class="user-auth-status-card__form" method="post" action="index.php" novalidate>
				<input type="hidden" name="action" value="user-password-reset" />
				<input type="hidden" name="reset_token" value="<?= htmlspecialchars($resetToken, ENT_QUOTES, 'UTF-8') ?>" />

				<label class="user-login-form__label" for="reset-new-password">New Password</label>
				<input class="user-login-form__input" type="password" id="reset-new-password" name="new_password" required autocomplete="new-password" />

				<label class="user-login-form__label" for="reset-confirm-password">Confirm Password</label>
				<input class="user-login-form__input" type="password" id="reset-confirm-password" name="confirm_password" required autocomplete="new-password" />

				<p class="user-auth-status-card__hint">Use at least 8 characters with uppercase, lowercase, number, and symbol.</p>
				<button class="user-login-form__submit" type="submit">Update Password</button>
			</form>
		<?php else: ?>
			<?php if ($resetRole === 'admin'): ?>
			<button class="user-auth-status-card__button" type="button" data-modal-target="admin-login-modal">Back to Admin Login</button>
		<?php else: ?>
			<button class="user-auth-status-card__button" type="button" data-modal-target="user-login-modal">Back to Login</button>
		<?php endif; ?>
		<?php endif; ?>
	</div>
</section>
