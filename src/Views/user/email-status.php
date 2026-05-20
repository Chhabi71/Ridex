<?php
$statusTitle = trim((string) ($statusTitle ?? 'Email Verification'));
$statusMessage = trim((string) ($statusMessage ?? ''));
$statusType = trim((string) ($statusType ?? 'info'));
$statusIcon = $statusType === 'success' ? 'mark_email_read' : ($statusType === 'error' ? 'error' : 'info');
?>

<section class="user-auth-status-page">
	<div class="user-auth-status-card user-auth-status-card--<?= htmlspecialchars($statusType, ENT_QUOTES, 'UTF-8') ?>">
		<span class="material-symbols-rounded user-auth-status-card__icon" aria-hidden="true"><?= htmlspecialchars($statusIcon, ENT_QUOTES, 'UTF-8') ?></span>
		<h1 class="user-auth-status-card__title"><?= htmlspecialchars($statusTitle, ENT_QUOTES, 'UTF-8') ?></h1>
		<?php if ($statusMessage !== ''): ?>
			<p class="user-auth-status-card__text"><?= htmlspecialchars($statusMessage, ENT_QUOTES, 'UTF-8') ?></p>
		<?php endif; ?>
		<a class="user-auth-status-card__button" href="index.php">Go to Home</a>
	</div>
</section>
