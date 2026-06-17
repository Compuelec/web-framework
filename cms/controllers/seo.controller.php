<?php

class SeoController {

    // Generate a URL-friendly slug from a title string
    public static function generateSlug($title) {
        $slug = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title);
        $slug = strtolower($slug);
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/[\s-]+/', '-', $slug);
        $slug = trim($slug, '-');
        return $slug;
    }

    // Check whether a slug is already used by another page_seo record
    public static function checkSlugExists($slug, $excludeId = null) {
        $url    = 'page_seo?linkTo=slug_seo&equalTo=' . urlencode($slug);
        $result = CurlController::request($url, 'GET', []);

        if ($result->status !== 200 || empty($result->results)) {
            return false;
        }

        // Allow the same record to keep its own slug on update
        if ($excludeId !== null) {
            foreach ($result->results as $row) {
                if ((int)$row->id_seo !== (int)$excludeId) {
                    return true;
                }
            }
            return false;
        }

        return true;
    }

    // Save (create or update) SEO data for a page
    public static function saveSeo($pageId, $fields) {
        $pageId = (int)$pageId;

        // Sanitize all text inputs
        $slug        = isset($fields['slug_seo'])       ? trim(strip_tags($fields['slug_seo']))       : '';
        $metaTitle   = isset($fields['meta_title_seo']) ? trim(strip_tags($fields['meta_title_seo'])) : '';
        $metaDesc    = isset($fields['meta_desc_seo'])  ? trim(strip_tags($fields['meta_desc_seo']))  : '';
        $ogTitle     = isset($fields['og_title_seo'])   ? trim(strip_tags($fields['og_title_seo']))   : '';
        $ogDesc      = isset($fields['og_desc_seo'])    ? trim(strip_tags($fields['og_desc_seo']))    : '';
        $ogImage     = isset($fields['og_image_seo'])   ? trim(strip_tags($fields['og_image_seo']))   : '';
        $ogType      = isset($fields['og_type_seo'])    ? trim(strip_tags($fields['og_type_seo']))    : 'website';

        // Auto-generate slug if blank
        if ($slug === '' && isset($fields['title_page']) && $fields['title_page'] !== '') {
            $slug = self::generateSlug($fields['title_page']);
        }

        // Validate slug format
        if ($slug !== '' && !preg_match('/^[a-z0-9-]+$/', $slug)) {
            return ['error' => 'Invalid slug: only lowercase letters, digits, and hyphens are allowed.'];
        }

        if ($slug === '') {
            return ['error' => 'Slug is required.'];
        }

        // Check for existing SEO record on this page
        $existing   = self::getSeo($pageId);
        $excludeId  = $existing ? (int)$existing->id_seo : null;

        // Enforce slug uniqueness
        if (self::checkSlugExists($slug, $excludeId)) {
            return ['error' => 'This slug is already in use by another page.'];
        }

        $token = $_SESSION['admin']->token_admin ?? '';

        if ($existing) {
            // Update existing record
            $url    = 'page_seo?id=' . $excludeId . '&nameId=id_seo&token=' . $token . '&table=admins&suffix=admin';
            $payload = http_build_query([
                'slug_seo'        => $slug,
                'meta_title_seo'  => $metaTitle,
                'meta_desc_seo'   => $metaDesc,
                'og_title_seo'    => $ogTitle,
                'og_desc_seo'     => $ogDesc,
                'og_image_seo'    => $ogImage,
                'og_type_seo'     => $ogType,
            ]);
            $result = CurlController::request($url, 'PUT', $payload);
        } else {
            // Create new record
            $url    = 'page_seo?token=' . $token . '&table=admins&suffix=admin';
            $payload = [
                'id_page_seo'     => $pageId,
                'slug_seo'        => $slug,
                'meta_title_seo'  => $metaTitle,
                'meta_desc_seo'   => $metaDesc,
                'og_title_seo'    => $ogTitle,
                'og_desc_seo'     => $ogDesc,
                'og_image_seo'    => $ogImage,
                'og_type_seo'     => $ogType,
            ];
            $result = CurlController::request($url, 'POST', $payload);
        }

        if ($result->status === 200) {
            require_once __DIR__ . '/../../core/activity_log.php';
            logActivity('update', 'page_seo', $pageId, 'SEO saved for page ' . $pageId);
            self::regenerateSitemap();
            return ['success' => true];
        }

        return ['error' => 'Failed to save SEO data.'];
    }

    // Fetch the SEO record for a given page ID
    public static function getSeo($pageId) {
        $url    = 'page_seo?linkTo=id_page_seo&equalTo=' . (int)$pageId;
        $result = CurlController::request($url, 'GET', []);

        if ($result->status === 200 && !empty($result->results)) {
            return $result->results[0];
        }
        return null;
    }

    // Fetch a SEO record by slug (used by the web frontend router)
    public static function getSeoBySlug($slug) {
        $url    = 'page_seo?linkTo=slug_seo&equalTo=' . urlencode($slug);
        $result = CurlController::request($url, 'GET', []);

        if ($result->status === 200 && !empty($result->results)) {
            return $result->results[0];
        }
        return null;
    }

    // Retrieve a single CMS setting value by key
    public static function getSetting($key) {
        $url    = 'cms_settings?linkTo=key_setting&equalTo=' . urlencode($key);
        $result = CurlController::request($url, 'GET', []);

        if ($result->status === 200 && !empty($result->results)) {
            return $result->results[0]->value_setting ?? '';
        }
        return '';
    }

    // Regenerate the sitemap cache file (web/sitemap.xml)
    public static function regenerateSitemap() {
        $sitemapPhp = dirname(__DIR__, 2) . '/web/sitemap.php';
        if (file_exists($sitemapPhp)) {
            // Include with output buffering to suppress output during background regeneration
            ob_start();
            $_GET['regenerate'] = '1';
            @include $sitemapPhp;
            ob_end_clean();
            unset($_GET['regenerate']);
        }
    }
}
