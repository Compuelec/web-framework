<?php
/**
 * Root index file - redirects to /cms
 * This file ensures that accessing the root directory redirects to the CMS
 */

// Get the base path
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$basePath = dirname($scriptName);
$basePath = str_replace('\\', '/', $basePath);
$basePath = rtrim($basePath, '/');

// Calculate CMS path
$cmsPath = $basePath . '/cms';

// If base path is root, use /cms
if ($basePath === '' || $basePath === '/') {
    $cmsPath = '/cms';
}

// Redirect to CMS
header('Location: ' . $cmsPath . '/', true, 301);
exit;

