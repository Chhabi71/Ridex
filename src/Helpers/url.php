<?php
/**
 * Purpose: URL builder utilities for routing, asset paths, and signed links if needed.
 * Website Section: Shared Routing/Links.
 * Developer Notes: Generate base URLs, route helpers, and convenience functions for redirects and asset versioning.
 */

if (!function_exists('ridex_url_with_query')) {
	function ridex_url_with_query(array $query, string $basePath = 'index.php'): string
	{
		$basePath = trim($basePath);
		if ($basePath === '') {
			$basePath = 'index.php';
		}

		if (empty($query)) {
			return $basePath;
		}

		return $basePath . '?' . http_build_query($query);
	}
}

if (!function_exists('ridex_redirect')) {
	function ridex_redirect(string $location, int $statusCode = 303): void
	{
		header('Location: ' . $location, true, $statusCode);
		exit;
	}
}

if (!function_exists('ridex_redirect_with_query')) {
	function ridex_redirect_with_query(array $query, int $statusCode = 303, string $basePath = 'index.php'): void
	{
		ridex_redirect(ridex_url_with_query($query, $basePath), $statusCode);
	}
}
