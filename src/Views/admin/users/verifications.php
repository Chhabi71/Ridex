<?php
/**
 * Purpose: Admin user verification management page.
 * Website Section: Admin User Verifications.
 */

$verificationRows = isset($verificationRows) && is_array($verificationRows) ? $verificationRows : [];
$verificationStatusFilter = isset($verificationStatusFilter) ? strtolower(trim((string) $verificationStatusFilter)) : 'all';

$statusMeta = [
	'pending' => ['label' => 'Pending', 'class' => 'admin-verifications__status--pending', 'icon' => 'hourglass_top'],
	'verified' => ['label' => 'Verified', 'class' => 'admin-verifications__status--verified', 'icon' => 'verified'],
	'rejected' => ['label' => 'Rejected', 'class' => 'admin-verifications__status--rejected', 'icon' => 'cancel'],
];

$emailStatusMeta = [
	'verified' => ['label' => 'Email Verified', 'class' => 'admin-verifications__status--verified', 'icon' => 'mark_email_read'],
	'pending' => ['label' => 'Email Pending', 'class' => 'admin-verifications__status--pending', 'icon' => 'mail'],
];

$formatPhone = static function ($phone): string {
	$phone = trim((string) $phone);
	return $phone !== '' ? $phone : 'N/A';
};

$formatDate = static function ($rawDate): string {
	$rawDate = trim((string) $rawDate);
	if ($rawDate === '') {
		return 'N/A';
	}
	try {
		return (new DateTimeImmutable($rawDate))->format('M d, Y');
	} catch (Throwable $exception) {
		return 'N/A';
	}
};

$documentUrl = static function ($path): string {
	$path = trim((string) $path);
	if ($path === '') {
		return '';
	}

	// Absolute external URLs can be used directly.
	if (preg_match('/^https?:\/\//i', $path) === 1) {
		return $path;
	}

	// Normalize Windows slashes and remove leading ./ or /.
	$normalized = str_replace('\\', '/', $path);
	$normalized = preg_replace('#^\./+#', '', $normalized) ?: $normalized;
	$normalized = ltrim($normalized, '/');

	// Database may store public/uploads/..., uploads/..., or only the filename.
	if (str_starts_with($normalized, 'public/uploads/')) {
		$normalized = substr($normalized, strlen('public/'));
	} elseif (str_starts_with($normalized, 'uploads/uploads/')) {
		$normalized = substr($normalized, strlen('uploads/'));
	} elseif (!str_starts_with($normalized, 'uploads/')) {
		$fileOnly = basename($normalized);
		$candidates = [
			'uploads/profiles/driver-ids/' . $fileOnly,
			'uploads/profiles/' . $fileOnly,
			'uploads/' . $fileOnly,
		];
		foreach ($candidates as $candidate) {
			if (is_file(APP_ROOT . '/public/' . $candidate)) {
				$normalized = $candidate;
				break;
			}
		}
		if (!str_starts_with($normalized, 'uploads/')) {
			$normalized = 'uploads/profiles/driver-ids/' . $fileOnly;
		}
	}

	// Encode spaces/special chars without encoding folder separators.
	$parts = array_map('rawurlencode', explode('/', $normalized));
	return implode('/', $parts);
};

$filterItems = [
	'all' => 'All',
	'pending' => 'Pending',
	'verified' => 'Verified',
	'rejected' => 'Rejected',
];
?>

<section class="admin-verifications" aria-labelledby="admin-verifications-title">
	<div class="admin-dashboard__shell">
		<aside class="admin-sidebar" aria-label="Admin panel navigation">
			<nav class="admin-sidebar__nav" aria-label="Admin sections">
				<a class="admin-sidebar__link" href="index.php?page=admin-dashboard">Dashboard</a>
				<a class="admin-sidebar__link" href="index.php?page=admin-manage-fleet">Manage Fleet</a>
				<a class="admin-sidebar__link" href="index.php?page=admin-all-bookings">All Bookings</a>
				<a class="admin-sidebar__link" href="index.php?page=admin-live-tracking">Live Tracking</a>
				<a class="admin-sidebar__link is-active" href="index.php?page=admin-user-verifications" aria-current="page">User Verification</a>
			</nav>
		</aside>

		<div class="admin-dashboard__content admin-verifications__content">
			<div class="admin-verifications__toolbar">
				<h1 class="admin-dashboard__title admin-verifications__title" id="admin-verifications-title">User Verification</h1>
				<div class="admin-verifications__filters" aria-label="Verification status filters">
					<?php foreach ($filterItems as $filterKey => $filterLabel): ?>
						<a class="admin-verifications__filter <?= $verificationStatusFilter === $filterKey ? 'is-active' : '' ?>" href="index.php?page=admin-user-verifications&status=<?= htmlspecialchars($filterKey, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($filterLabel, ENT_QUOTES, 'UTF-8') ?></a>
					<?php endforeach; ?>
				</div>
			</div>

			<section class="admin-kpi-grid admin-verifications__kpis" aria-label="Verification summary">
				<?php
				$counts = ['pending' => 0, 'verified' => 0, 'rejected' => 0];
				foreach ($verificationRows as $row) {
					$key = strtolower(trim((string) ($row['verification_status'] ?? 'pending')));
					if (isset($counts[$key])) {
						$counts[$key]++;
					}
				}
				?>
				<article class="admin-kpi-card"><header class="admin-kpi-card__header"><h2>Pending Review</h2><span class="material-symbols-rounded admin-kpi-card__icon" aria-hidden="true">hourglass_top</span></header><strong class="admin-kpi-card__value"><?= number_format($counts['pending']) ?></strong></article>
				<article class="admin-kpi-card"><header class="admin-kpi-card__header"><h2>Verified Users</h2><span class="material-symbols-rounded admin-kpi-card__icon" aria-hidden="true">verified</span></header><strong class="admin-kpi-card__value"><?= number_format($counts['verified']) ?></strong></article>
				<article class="admin-kpi-card"><header class="admin-kpi-card__header"><h2>Rejected Users</h2><span class="material-symbols-rounded admin-kpi-card__icon admin-kpi-card__icon--down" aria-hidden="true">cancel</span></header><strong class="admin-kpi-card__value"><?= number_format($counts['rejected']) ?></strong></article>
				<article class="admin-kpi-card"><header class="admin-kpi-card__header"><h2>Total Users</h2><span class="material-symbols-rounded admin-kpi-card__icon" aria-hidden="true">groups</span></header><strong class="admin-kpi-card__value"><?= number_format(count($verificationRows)) ?></strong></article>
			</section>

			<div class="admin-bookings__table-wrap admin-verifications__table-card">
				<div class="admin-bookings__table-scroll">
				<table class="admin-bookings__table admin-verifications__table" aria-label="User verification table">
					<thead>
						<tr>
							<th scope="col">User</th>
							<th scope="col">Email</th>
							<th scope="col">Email Status</th>
							<th scope="col">Verification Document</th>
							<th scope="col">ID Status</th>
							<th scope="col">Joined</th>
							<th scope="col">Action</th>
						</tr>
					</thead>
					<tbody>
						<?php if (!empty($verificationRows)): ?>
							<?php foreach ($verificationRows as $row): ?>
								<?php
								$userId = (int) ($row['id'] ?? 0);
								$statusKey = strtolower(trim((string) ($row['verification_status'] ?? 'pending')));
								if (!isset($statusMeta[$statusKey])) {
									$statusKey = 'pending';
								}
								$meta = $statusMeta[$statusKey];
								$docUrl = $documentUrl($row['drivers_id_image_path'] ?? '');
								$driversId = trim((string) ($row['drivers_id'] ?? ''));
								$emailVerified = (int) ($row['email_verified'] ?? 0) === 1 || trim((string) ($row['email_verified_at'] ?? '')) !== '';
								$emailMeta = $emailStatusMeta[$emailVerified ? 'verified' : 'pending'];
								?>
								<tr>
									<td class="admin-verifications__cell-user">
										<div class="admin-verifications__user">
											<span class="admin-verifications__avatar material-symbols-rounded" aria-hidden="true">person</span>
											<div class="admin-verifications__user-info"><strong class="admin-verifications__user-name"><?= htmlspecialchars(trim((string) ($row['name'] ?? 'Ridex User')) ?: 'Ridex User', ENT_QUOTES, 'UTF-8') ?></strong><span class="admin-verifications__user-phone"><?= htmlspecialchars($formatPhone($row['phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span></div>
										</div>
									</td>
									<td class="admin-verifications__cell-email"><span class="admin-verifications__email" title="<?= htmlspecialchars(trim((string) ($row['email'] ?? 'N/A')) ?: 'N/A', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(trim((string) ($row['email'] ?? 'N/A')) ?: 'N/A', ENT_QUOTES, 'UTF-8') ?></span></td>
									<td class="admin-verifications__cell-status"><span class="admin-verifications__status <?= htmlspecialchars($emailMeta['class'], ENT_QUOTES, 'UTF-8') ?>"><span class="material-symbols-rounded" aria-hidden="true"><?= htmlspecialchars($emailMeta['icon'], ENT_QUOTES, 'UTF-8') ?></span><?= htmlspecialchars($emailMeta['label'], ENT_QUOTES, 'UTF-8') ?></span></td>
									<td class="admin-verifications__cell-doc">
										<?php if ($docUrl !== ''): ?>
											<button class="admin-verifications__view" type="button" data-verification-doc-open data-doc-url="<?= htmlspecialchars($docUrl, ENT_QUOTES, 'UTF-8') ?>" data-doc-title="<?= htmlspecialchars(($driversId !== '' ? $driversId : 'Verification Document'), ENT_QUOTES, 'UTF-8') ?>">View Photo</button>
										<?php else: ?>
											<span class="admin-verifications__missing">No photo</span>
										<?php endif; ?>
									</td>
									<td class="admin-verifications__cell-status"><span class="admin-verifications__status <?= htmlspecialchars($meta['class'], ENT_QUOTES, 'UTF-8') ?>"><span class="material-symbols-rounded" aria-hidden="true"><?= htmlspecialchars($meta['icon'], ENT_QUOTES, 'UTF-8') ?></span><?= htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8') ?></span></td>
									<td class="admin-verifications__cell-date"><?= htmlspecialchars($formatDate($row['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
									<td class="admin-verifications__cell-actions">
										<div class="admin-verifications__actions">
											<form method="post" action="index.php"><input type="hidden" name="action" value="admin-user-verification-update"><input type="hidden" name="user_id" value="<?= $userId ?>"><input type="hidden" name="verification_decision" value="verified"><button class="admin-verifications__action-btn admin-verifications__approve" type="submit">Approve</button></form>
											<form method="post" action="index.php"><input type="hidden" name="action" value="admin-user-verification-update"><input type="hidden" name="user_id" value="<?= $userId ?>"><input type="hidden" name="verification_decision" value="rejected"><button class="admin-verifications__action-btn admin-verifications__reject" type="submit">Reject</button></form>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php else: ?>
							<tr><td colspan="7" class="admin-verifications__empty">No users found for this verification filter.</td></tr>
						<?php endif; ?>
					</tbody>
				</table>
				</div>
			</div>
		</div>
	</div>
</section>

<div class="admin-verification-doc-modal" data-verification-doc-modal hidden aria-hidden="true">
	<div class="admin-verification-doc-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="admin-verification-doc-title">
		<button class="admin-verification-doc-modal__close" type="button" data-verification-doc-close aria-label="Close verification document"><span class="material-symbols-rounded" aria-hidden="true">close</span></button>
		<h2 id="admin-verification-doc-title">User Verification Document</h2>
		<p data-verification-doc-label>License / ID Photo</p>
		<div class="admin-verification-doc-modal__image-wrap">
			<img src="" alt="User verification document" data-verification-doc-image hidden>
			<div class="admin-verification-doc-modal__message" data-verification-doc-message>Document image not found</div>
		</div>
	</div>
</div>

<script>
document.addEventListener('click', function (event) {
	var openButton = event.target.closest('[data-verification-doc-open]');
	var modal = document.querySelector('[data-verification-doc-modal]');
	if (openButton && modal) {
		var image = modal.querySelector('[data-verification-doc-image]');
		var message = modal.querySelector('[data-verification-doc-message]');
		var label = modal.querySelector('[data-verification-doc-label]');
		var rawUrl = (openButton.getAttribute('data-doc-url') || '').trim();

		if (label) {
			label.textContent = openButton.getAttribute('data-doc-title') || 'License / ID Photo';
		}

		if (message) {
			message.textContent = rawUrl ? 'Loading document...' : 'Document image not found';
			message.hidden = false;
		}

		if (image) {
			image.hidden = true;
			image.removeAttribute('src');
			image.onload = function () {
				image.hidden = false;
				if (message) message.hidden = true;
			};
			image.onerror = function () {
				image.hidden = true;
				image.removeAttribute('src');
				if (message) {
					message.textContent = 'Document image not found';
					message.hidden = false;
				}
			};

			if (rawUrl) {
				image.src = rawUrl;
			}
		}

		modal.hidden = false;
		modal.setAttribute('aria-hidden', 'false');
		return;
	}
	if (event.target.closest('[data-verification-doc-close]') || event.target === modal) {
		if (modal) {
			modal.hidden = true;
			modal.setAttribute('aria-hidden', 'true');
		}
	}
});
</script>
