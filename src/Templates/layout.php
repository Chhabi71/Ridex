<?php
/**
 * Purpose: Main layout wrapper for customer-facing pages.
*/

$title = $title ?? 'Ridex';
$view = $view ?? null;
$viewData = $viewData ?? [];
$currentPage = strtolower(trim((string) ($page ?? '')));
$isAdminPage = str_starts_with($currentPage, 'admin');
$bodyClass = $isAdminPage ? 'admin-page' : '';
$layoutSessionUser = isset($_SESSION['auth_user']) && is_array($_SESSION['auth_user'])
	? $_SESSION['auth_user']
	: [];
$isUserAuthenticated = (($layoutSessionUser['role'] ?? '') === 'user');
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
	<link
		rel="stylesheet"
		href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0"
	/>
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" />
	<link rel="stylesheet" href="css/styles.css?v=20260408-1" />
	<link rel="stylesheet" href="css/booking.css?v=20260408-1" />
	<link rel="stylesheet" href="css/admin.css?v=20260408-2" />
	<link rel="stylesheet" href="css/user.css?v=20260407-8" />
</head>
<body
	class="<?= htmlspecialchars($bodyClass, ENT_QUOTES, 'UTF-8') ?>"
	data-user-authenticated="<?= $isUserAuthenticated ? '1' : '0' ?>"
>
	<?php include __DIR__ . '/../Views/partials/header.php'; ?>

	<main class="page-content" role="main">
		<?php
		// Expose view data to the included view while avoiding variable collisions.
		extract($viewData, EXTR_SKIP);

		if ($view) {
			$viewPath = __DIR__ . '/../Views/' . ltrim($view, '/');
			if (substr($viewPath, -4) !== '.php') {
				$viewPath .= '.php';
			}

			if (is_file($viewPath)) {
				include $viewPath;
			} else {
				echo "<!-- View not found: {$view} -->";
			}
		} else {
			echo '<!-- No view provided -->';
		}
		?>
	</main>

	<?php include __DIR__ . '/../Views/partials/modals.php'; ?>

	<?php $renderFooterMarkup = empty($hideFooter); ?>
	<?php include __DIR__ . '/../Views/partials/footer.php'; ?>
</body>
</html>
