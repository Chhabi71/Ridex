<?php
/**
 * Purpose: SMTP email helper for RIDEX account verification and password reset emails.
 * Website Section: Authentication email delivery.
 * Developer Notes: Keeps SMTP credentials in .env and never exposes them to frontend JavaScript.
 */

if (!function_exists('ridex_mail_env')) {
	function ridex_mail_env(string $key, string $default = ''): string
	{
		$value = env($key, $default);
		return trim((string) $value);
	}
}

if (!function_exists('ridex_app_url')) {
	function ridex_app_url(): string
	{
		$appUrl = ridex_mail_env('APP_URL', '');
		if ($appUrl !== '') {
			return rtrim($appUrl, '/');
		}

		$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
		$host = trim((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
		$scriptDir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php')));
		$scriptDir = $scriptDir === '/' ? '' : rtrim($scriptDir, '/');

		return $scheme . '://' . $host . $scriptDir;
	}
}

if (!function_exists('ridex_build_absolute_url')) {
	function ridex_build_absolute_url(array $query): string
	{
		return ridex_app_url() . '/index.php?' . http_build_query($query);
	}
}

if (!function_exists('ridex_smtp_read_response')) {
	function ridex_smtp_read_response($socket): string
	{
		$response = '';
		while (is_resource($socket) && !feof($socket)) {
			$line = fgets($socket, 515);
			if ($line === false) {
				break;
			}

			$response .= $line;
			if (strlen($line) >= 4 && substr($line, 3, 1) === ' ') {
				break;
			}
		}

		return $response;
	}
}

if (!function_exists('ridex_smtp_expect')) {
	function ridex_smtp_expect($socket, array $acceptedCodes, string $context): string
	{
		$response = ridex_smtp_read_response($socket);
		$code = (int) substr($response, 0, 3);
		if (!in_array($code, $acceptedCodes, true)) {
			throw new RuntimeException($context . ' failed. SMTP response: ' . trim($response));
		}

		return $response;
	}
}

if (!function_exists('ridex_smtp_send_line')) {
	function ridex_smtp_send_line($socket, string $line): void
	{
		if (!is_resource($socket)) {
			throw new RuntimeException('SMTP socket is not available.');
		}

		fwrite($socket, $line . "\r\n");
	}
}

if (!function_exists('ridex_mailer_is_configured')) {
	function ridex_mailer_is_configured(): bool
	{
		return ridex_mail_env('MAIL_HOST') !== ''
			&& ridex_mail_env('MAIL_USERNAME') !== ''
			&& ridex_mail_env('MAIL_PASSWORD') !== ''
			&& ridex_mail_env('MAIL_FROM_ADDRESS') !== '';
	}
}

if (!function_exists('ridex_mail_log_fallback')) {
	function ridex_mail_log_fallback(string $to, string $subject, string $htmlBody, string $plainBody): void
	{
		$logDir = APP_ROOT . '/var/logs';
		if (!is_dir($logDir)) {
			@mkdir($logDir, 0775, true);
		}

		$logMessage = "\n==== RIDEX MAIL FALLBACK " . date('c') . " ====\n"
			. "To: {$to}\nSubject: {$subject}\n\n{$plainBody}\n\nHTML:\n{$htmlBody}\n";
		@file_put_contents($logDir . '/mail.log', $logMessage, FILE_APPEND);
	}
}

if (!function_exists('ridex_send_email')) {
	function ridex_send_email(string $to, string $subject, string $htmlBody, string $plainBody = ''): bool
	{
		$to = trim($to);
		$subject = trim($subject);
		if ($to === '' || $subject === '') {
			return false;
		}

		if ($plainBody === '') {
			$plainBody = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody)));
		}

		if (!ridex_mailer_is_configured()) {
			ridex_mail_log_fallback($to, $subject, $htmlBody, $plainBody);
			return false;
		}

		$host = ridex_mail_env('MAIL_HOST', 'smtp.gmail.com');
		$port = (int) ridex_mail_env('MAIL_PORT', '587');
		$username = ridex_mail_env('MAIL_USERNAME');
		$password = ridex_mail_env('MAIL_PASSWORD');
		$fromAddress = ridex_mail_env('MAIL_FROM_ADDRESS', $username);
		$fromName = ridex_mail_env('MAIL_FROM_NAME', 'RIDEX');
		$encryption = strtolower(ridex_mail_env('MAIL_ENCRYPTION', 'tls'));
		$timeout = 20;

		$remote = ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
		$errno = 0;
		$errstr = '';
		$socket = @stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);
		if (!is_resource($socket)) {
			throw new RuntimeException('Unable to connect to SMTP server: ' . $errstr);
		}

		stream_set_timeout($socket, $timeout);

		try {
			ridex_smtp_expect($socket, [220], 'SMTP greeting');
			ridex_smtp_send_line($socket, 'EHLO localhost');
			ridex_smtp_expect($socket, [250], 'SMTP EHLO');

			if ($encryption === 'tls') {
				ridex_smtp_send_line($socket, 'STARTTLS');
				ridex_smtp_expect($socket, [220], 'SMTP STARTTLS');
				if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
					throw new RuntimeException('Unable to enable SMTP TLS encryption.');
				}

				ridex_smtp_send_line($socket, 'EHLO localhost');
				ridex_smtp_expect($socket, [250], 'SMTP EHLO after TLS');
			}

			ridex_smtp_send_line($socket, 'AUTH LOGIN');
			ridex_smtp_expect($socket, [334], 'SMTP AUTH LOGIN');
			ridex_smtp_send_line($socket, base64_encode($username));
			ridex_smtp_expect($socket, [334], 'SMTP username');
			ridex_smtp_send_line($socket, base64_encode($password));
			ridex_smtp_expect($socket, [235], 'SMTP password');

			ridex_smtp_send_line($socket, 'MAIL FROM:<' . $fromAddress . '>');
			ridex_smtp_expect($socket, [250], 'SMTP MAIL FROM');
			ridex_smtp_send_line($socket, 'RCPT TO:<' . $to . '>');
			ridex_smtp_expect($socket, [250, 251], 'SMTP RCPT TO');
			ridex_smtp_send_line($socket, 'DATA');
			ridex_smtp_expect($socket, [354], 'SMTP DATA');

			$boundary = 'ridex_' . bin2hex(random_bytes(12));
			$headers = [
				'From: ' . (function_exists('mb_encode_mimeheader') ? mb_encode_mimeheader($fromName) : $fromName) . ' <' . $fromAddress . '>',
				'To: <' . $to . '>',
				'Subject: ' . (function_exists('mb_encode_mimeheader') ? mb_encode_mimeheader($subject) : $subject),
				'MIME-Version: 1.0',
				'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
			];

			$message = implode("\r\n", $headers) . "\r\n\r\n"
				. '--' . $boundary . "\r\n"
				. "Content-Type: text/plain; charset=UTF-8\r\n"
				. "Content-Transfer-Encoding: 8bit\r\n\r\n"
				. $plainBody . "\r\n\r\n"
				. '--' . $boundary . "\r\n"
				. "Content-Type: text/html; charset=UTF-8\r\n"
				. "Content-Transfer-Encoding: 8bit\r\n\r\n"
				. $htmlBody . "\r\n\r\n"
				. '--' . $boundary . "--\r\n";

			// SMTP DATA terminates with a dot on a line by itself. Escape leading dots.
			$message = preg_replace('/^\./m', '..', $message);
			fwrite($socket, $message . "\r\n.\r\n");
			ridex_smtp_expect($socket, [250], 'SMTP message send');
			ridex_smtp_send_line($socket, 'QUIT');
			fclose($socket);

			return true;
		} catch (Throwable $exception) {
			if (is_resource($socket)) {
				@fclose($socket);
			}

			ridex_mail_log_fallback($to, $subject, $htmlBody, $plainBody);
			throw $exception;
		}
	}
}

if (!function_exists('ridex_send_verification_email')) {
	function ridex_send_verification_email(string $to, string $name, string $token): bool
	{
		$link = ridex_build_absolute_url([
			'page' => 'verify-email',
			'token' => $token,
		]);
		$safeName = htmlspecialchars($name !== '' ? $name : 'Ridex user', ENT_QUOTES, 'UTF-8');
		$safeLink = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');
		$html = '<p>Hello ' . $safeName . ',</p>'
			. '<p>Thank you for creating your RIDEX account. Please verify your email address by clicking the button below.</p>'
			. '<p><a href="' . $safeLink . '" style="display:inline-block;padding:12px 18px;background:#111827;color:#ffffff;text-decoration:none;border-radius:8px;">Verify Email</a></p>'
			. '<p>This link will expire in 24 hours.</p>'
			. '<p>If the button does not work, copy this link: ' . $safeLink . '</p>';
		$plain = "Hello {$name},\n\nVerify your RIDEX email using this link:\n{$link}\n\nThis link will expire in 24 hours.";

		return ridex_send_email($to, 'Verify your RIDEX account email', $html, $plain);
	}
}

if (!function_exists('ridex_send_account_verification_decision_email')) {
	function ridex_send_account_verification_decision_email(string $to, string $name, string $decision): bool
	{
		$decision = strtolower(trim($decision));
		$safeName = htmlspecialchars($name !== '' ? $name : 'Ridex user', ENT_QUOTES, 'UTF-8');

		if ($decision === 'verified') {
			$subject = 'RIDEX Account Verification Approved';
			$html = '<p>Hello ' . $safeName . ',</p>'
				. '<p>Your RIDEX account verification has been approved successfully.</p>'
				. '<p>You can now book and rent vehicles from RIDEX.</p>'
				. '<p>Thank you,<br>RIDEX Team</p>';
			$plain = "Hello {$name},\n\nYour RIDEX account verification has been approved successfully.\nYou can now book and rent vehicles from RIDEX.\n\nThank you,\nRIDEX Team";
		} elseif ($decision === 'rejected') {
			$subject = 'RIDEX Account Verification Rejected';
			$reuploadLink = ridex_build_absolute_url(['page' => 'reupload-verification']);
			$safeReuploadLink = htmlspecialchars($reuploadLink, ENT_QUOTES, 'UTF-8');
			$html = '<p>Hello ' . $safeName . ',</p>'
				. '<p>Your RIDEX account verification request was rejected.</p>'
				. '<p>Please upload a clearer and valid verification document, then try again.</p>'
				. '<p><a href="' . $safeReuploadLink . '" style="display:inline-block;padding:12px 18px;background:#111827;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:600;">Re-upload Verification Photo</a></p>'
				. '<p>If the button does not work, copy this link: ' . $safeReuploadLink . '</p>'
				. '<p>Thank you,<br>RIDEX Team</p>';
			$plain = "Hello {$name},\n\nYour RIDEX account verification request was rejected.\nPlease upload a clearer and valid verification document, then try again.\n\nRe-upload Verification Photo:\n{$reuploadLink}\n\nThank you,\nRIDEX Team";
		} else {
			return false;
		}

		return ridex_send_email($to, $subject, $html, $plain);
	}
}

if (!function_exists('ridex_send_password_reset_email')) {
	function ridex_send_password_reset_email(string $to, string $name, string $token): bool
	{
		$link = ridex_build_absolute_url([
			'page' => 'reset-password',
			'token' => $token,
		]);
		$safeName = htmlspecialchars($name !== '' ? $name : 'Ridex user', ENT_QUOTES, 'UTF-8');
		$safeLink = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');
		$html = '<p>Hello ' . $safeName . ',</p>'
			. '<p>We received a request to reset your RIDEX password. Click the button below to create a new password.</p>'
			. '<p><a href="' . $safeLink . '" style="display:inline-block;padding:12px 18px;background:#111827;color:#ffffff;text-decoration:none;border-radius:8px;">Reset Password</a></p>'
			. '<p>This password reset link will expire in 1 hour.</p>'
			. '<p>If you did not request this, you can safely ignore this email.</p>'
			. '<p>If the button does not work, copy this link: ' . $safeLink . '</p>';
		$plain = "Hello {$name},\n\nReset your RIDEX password using this link:\n{$link}\n\nThis link will expire in 1 hour. If you did not request this, ignore this email.";

		return ridex_send_email($to, 'Reset your RIDEX password', $html, $plain);
	}
}
