<?php

/**
 * Token Service
 * Handles generation and validation of admin security codes (2FA).
 */
class TokenService {

    /**
     * Generate a security code, store it with expiration, and return the code.
     * Returns the plain-text code to be sent by email.
     */
    public static function generateSecurityCode($adminId, $length = 8) {
        $code    = TemplateController::genPassword($length);
        $expiry  = time() + (15 * 60); // 15 minutes

        CurlController::request(
            "admins?id={$adminId}&nameId=id_admin&token=no&except=scode_admin,scode_exp_admin,scode_attempts_admin",
            "PUT",
            "scode_admin={$code}&scode_exp_admin={$expiry}&scode_attempts_admin=0"
        );

        return $code;
    }

    /**
     * Validate a submitted security code against the stored one.
     * Returns 'ok', 'expired', 'locked', or 'invalid'.
     */
    public static function validateSecurityCode($adminData, $submittedCode) {
        $now      = time();
        $attempts = (int)($adminData->scode_attempts_admin ?? 0);
        $exp      = (int)($adminData->scode_exp_admin      ?? 0);
        $stored   = $adminData->scode_admin                ?? '';

        if ($attempts >= 3) {
            return 'locked';
        }

        if ($exp > 0 && $now > $exp) {
            return 'expired';
        }

        if (!hash_equals($stored, $submittedCode)) {
            return 'invalid';
        }

        return 'ok';
    }

    /**
     * Clear the security code fields after a successful login.
     */
    public static function clearSecurityCode($adminId) {
        CurlController::request(
            "admins?id={$adminId}&nameId=id_admin&token=no&except=scode_admin,scode_exp_admin,scode_attempts_admin",
            "PUT",
            "scode_admin=&scode_exp_admin=0&scode_attempts_admin=0"
        );
    }
}
