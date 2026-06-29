<?php

/**
 * Web Pages Setup Controller
 *
 * Ensures the "Páginas Web" (visual page builder) section exists in the CMS menu.
 * The section's view lives at cms/views/pages/custom/web-pages/, but its `pages`
 * row must also exist for it to appear. This self-heals on every CMS load so the
 * builder is always available — on a fresh install, after a factory reset, or if
 * the row was ever removed.
 */

require_once __DIR__ . '/curl.controller.php';

class WebPagesSetupController {

    /**
     * Ensure the "Páginas Web" page exists. Creates it if missing.
     *
     * @return array Result with success status
     */
    public static function ensureWebPagesPage() {
        // Check if the page already exists.
        $existingPage = CurlController::request("pages?linkTo=url_page&equalTo=web-pages", "GET", array());

        if ($existingPage && isset($existingPage->status) && $existingPage->status == 200 &&
            isset($existingPage->results) && is_array($existingPage->results) && count($existingPage->results) > 0) {
            return [
                'success' => true,
                'message' => 'Web pages section already exists',
                'created' => false
            ];
        }

        // Create the page (custom type → routes to views/pages/custom/web-pages/web-pages.php).
        $fields = array(
            "title_page"        => "Páginas Web",
            "url_page"          => "web-pages",
            "icon_page"         => "bi bi-window-stack",
            "type_page"         => "custom",
            "parent_page"       => 0,
            "order_page"        => 7,
            "date_created_page" => date("Y-m-d")
        );

        $createPage = CurlController::request("pages?token=no&except=id_page", "POST", $fields);

        if ($createPage && isset($createPage->status) && $createPage->status == 200) {
            return [
                'success' => true,
                'message' => 'Web pages section created successfully',
                'created' => true
            ];
        }

        return [
            'success' => false,
            'message' => 'Failed to create web pages section',
            'created' => false
        ];
    }
}
