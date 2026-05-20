<?php
/** Purpose: User verification document re-upload page. */
$userName = trim((string) ($userName ?? 'Ridex User')) ?: 'Ridex User';
$successMessage = trim((string) ($successMessage ?? ''));
$errorMessage = trim((string) ($errorMessage ?? ''));
?>
<section class="verification-reupload" aria-labelledby="verification-reupload-title">
	<div class="verification-reupload__card">
		<div class="verification-reupload__brand">RIDEX</div>
		<h1 id="verification-reupload-title">Re-upload Verification Photo</h1>
		<p>Hello <?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?>, upload a clearer license or ID photo for admin review.</p>
		<?php if ($successMessage !== ''): ?><div class="verification-reupload__notice verification-reupload__notice--success"><?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
		<?php if ($errorMessage !== ''): ?><div class="verification-reupload__notice verification-reupload__notice--error"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
		<form class="verification-reupload__form" action="index.php" method="post" enctype="multipart/form-data">
			<input type="hidden" name="action" value="user-verification-reupload">
			<label class="verification-reupload__upload">
				<span class="material-symbols-rounded" aria-hidden="true">upload_file</span>
				<span>Choose verification photo</span>
				<input type="file" name="verification_document" accept="image/jpeg,image/png,image/webp" required>
			</label>
			<button type="submit">Submit for Review</button>
		</form>
		<a class="verification-reupload__back" href="index.php">Back to Home</a>
	</div>
</section>
