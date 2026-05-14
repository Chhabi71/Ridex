<?php
/**
 * Purpose: Session model for persisting server-side session data if stored in DB.
 * Website Section: Cross-cutting Authentication/State.
 * Developer Notes: Implement CRUD for session rows, cleanup expired sessions, and integrate with middleware/auth helpers.
 */

if (!function_exists('ridex_session_set_flash')) {
	function ridex_session_set_flash(string $key, array $payload): void
	{
		$_SESSION[$key] = $payload;
	}
}

if (!function_exists('ridex_session_pull_flash')) {
	function ridex_session_pull_flash(string $key): array
	{
		$payload = $_SESSION[$key] ?? [];
		unset($_SESSION[$key]);

		return is_array($payload) ? $payload : [];
	}
}

if (!function_exists('ridex_session_clear_auth_context')) {
	function ridex_session_clear_auth_context(): void
	{
		unset(
			$_SESSION['auth_user'],
			$_SESSION['admin_login_flash'],
			$_SESSION['user_login_flash'],
			$_SESSION['booking_flow_lock']
		);
	}
}

if (!function_exists('ridex_session_set_admin_login_flash')) {
	function ridex_session_set_admin_login_flash(
		string $error,
		string $email,
		bool $emailInvalid,
		bool $passwordInvalid
	): void {
		ridex_session_set_flash('admin_login_flash', [
			'error' => $error,
			'email' => $email,
			'email_invalid' => $emailInvalid,
			'password_invalid' => $passwordInvalid,
		]);
	}
}

if (!function_exists('ridex_session_set_user_login_flash')) {
	function ridex_session_set_user_login_flash(
		string $error,
		string $identifier,
		bool $identifierInvalid,
		bool $passwordInvalid,
		string $postAuthRedirect
	): void {
		ridex_session_set_flash('user_login_flash', [
			'error' => $error,
			'identifier' => $identifier,
			'identifier_invalid' => $identifierInvalid,
			'password_invalid' => $passwordInvalid,
			'post_auth_redirect' => $postAuthRedirect,
		]);
	}
}

if (!function_exists('ridex_session_set_user_register_flash')) {
	function ridex_session_set_user_register_flash(array $errors, array $old): void
	{
		ridex_session_set_flash('user_register_flash', [
			'errors' => $errors,
			'old' => $old,
		]);
		unset($_SESSION['user_register_success']);
	}
}

if (!function_exists('ridex_session_set_user_register_success')) {
	function ridex_session_set_user_register_success(string $email, bool $subscribeNewsletter): void
	{
		ridex_session_set_flash('user_register_success', [
			'email' => $email,
			'subscribe_newsletter' => $subscribeNewsletter,
		]);
	}
}
