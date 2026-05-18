<?php
/**
 * Purpose: User registration view/modal to create an account.
 * Website Section: Authentication.
 * Developer Notes: Collect name, email, password, phone, address, drivers_id; include validation messaging and CSRF token.
 */

$old = $old ?? [];
$errors = $errors ?? [];

$districtOptions = [
	'Koshi',
	'Madhesh',
	'Bagmati',
	'Lumbini',
	'Karnali',
	'Sudurpashchim',
];

?>
<section class="auth-register" aria-labelledby="register-title">
	<h1 id="register-title">Create Account</h1>
	<form method="post" action="/register" novalidate>
		<?php if (function_exists('csrf_field')): ?>
			<?= csrf_field() ?>
		<?php endif; ?>

		<div class="form-row">
			<label for="first_name">First Name</label>
			<input id="first_name" name="first_name" type="text" value="<?= htmlspecialchars((string) ($old['first_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required />
		</div>

		<div class="form-row">
			<label for="last_name">Last Name</label>
			<input id="last_name" name="last_name" type="text" value="<?= htmlspecialchars((string) ($old['last_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required />
		</div>

		<div class="form-row">
			<label for="email">Email</label>
			<input id="email" name="email" type="email" value="<?= htmlspecialchars((string) ($old['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required />
		</div>

		<div class="form-row">
			<label for="phone">Phone Number</label>
			<input id="phone" name="phone" type="text" value="<?= htmlspecialchars((string) ($old['phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" />
		</div>

		<div class="form-row">
			<label for="date_of_birth">Date of Birth</label>
			<input id="date_of_birth" name="date_of_birth" type="date" value="<?= htmlspecialchars((string) ($old['date_of_birth'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" />
		</div>

		<div class="form-row">
			<label for="drivers_id">Driver's ID</label>
			<input id="drivers_id" name="drivers_id" type="text" value="<?= htmlspecialchars((string) ($old['drivers_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" />
		</div>

		<div class="form-row">
			<label for="street">Street</label>
			<input id="street" name="street" type="text" value="<?= htmlspecialchars((string) ($old['street'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" />
		</div>

		<div class="form-row">
			<label for="post_code">Post Code</label>
			<input id="post_code" name="post_code" type="text" value="<?= htmlspecialchars((string) ($old['post_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" />
		</div>

		<div class="form-row">
			<label for="city">City</label>
			<input id="city" name="city" type="text" value="<?= htmlspecialchars((string) ($old['city'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" />
		</div>

		<div class="form-row">
			<label for="district">District</label>
			<select id="district" name="district">
				<option value="">Select district</option>
				<?php foreach ($districtOptions as $district): ?>
					<option value="<?= htmlspecialchars($district, ENT_QUOTES, 'UTF-8') ?>" <?= (($old['district'] ?? '') === $district) ? 'selected' : '' ?>>
						<?= htmlspecialchars($district, ENT_QUOTES, 'UTF-8') ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="form-row">
			<label for="password">Password</label>
			<input id="password" name="password" type="password" required />
		</div>

		<div class="form-row">
			<label for="password_confirmation">Confirm Password</label>
			<input id="password_confirmation" name="password_confirmation" type="password" required />
		</div>

		<?php if (!empty($errors)): ?>
			<div class="form-errors" role="alert">
				<?php foreach ($errors as $error): ?>
					<p><?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') ?></p>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<button type="submit">Create Account</button>
	</form>
</section>
