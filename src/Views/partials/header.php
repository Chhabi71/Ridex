<?php
/**
 * Purpose: Shared HTML <head> and top navigation bar for public pages.
*/

$headerSessionUser = isset($_SESSION['auth_user']) && is_array($_SESSION['auth_user'])
	? $_SESSION['auth_user']
	: [];
$headerIsAdmin = (($headerSessionUser['role'] ?? '') === 'admin');
$headerIsUser = (($headerSessionUser['role'] ?? '') === 'user');
$headerProfileModalTarget = $headerIsAdmin
	? 'admin-profile-modal'
	: ($headerIsUser ? 'user-profile-modal' : 'user-login-modal');
$headerCurrentPage = strtolower(trim((string) ($page ?? '')));
$headerIsAdminPage = str_starts_with($headerCurrentPage, 'admin');
?>

<header class="site-header" role="banner">
	<div class="site-header__inner">
		<div class="site-header__brand" aria-label="Ridex brand">
			<img
				src="images/ridex-header.png"
				alt="Ridex logo"
				class="site-header__logo"
				onerror="this.onerror=null;this.src='images/logo.svg';"
			/>
		</div>
		<nav class="site-header__actions" aria-label="User navigation">
			<button
				class="icon-button icon-button--profile"
				type="button"
				aria-label="User profile"
				data-modal-target="<?= htmlspecialchars($headerProfileModalTarget, ENT_QUOTES, 'UTF-8') ?>"
			>
				<span class="material-symbols-rounded" aria-hidden="true">account_circle</span>
			</button>
			<?php if (!$headerIsAdminPage): ?>
				<?php //menu: opens global navigation modal ?>
				<button class="icon-button" type="button" aria-label="Open menu" data-menu-open>
					<span class="material-symbols-rounded" aria-hidden="true">menu</span>
				</button>
			<?php endif; ?>
		</nav>
	</div>
</header>
