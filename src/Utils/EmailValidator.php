<?php
/**
 * Email Validator Utilities
 *
 * @package KissPlugins\WooOrderMonitor
 * @since 1.7.0
 */

namespace KissPlugins\WooOrderMonitor\Utils;

/**
 * Email Validator Class
 * 
 * Provides utility functions for email validation and processing.
 * 
 * @since 1.7.0
 */
class EmailValidator {
    
    /**
     * Validate a single email address
     *
     * @param string $email Email address to validate
     * @return bool True if valid email address
     */
    public static function isValid(string $email): bool {
        return \is_email($email) !== false;
    }
    
    /**
     * Validate multiple email addresses
     *
     * @param array $emails Array of email addresses
     * @return array Array with 'valid' and 'invalid' email arrays
     */
    public static function validateMultiple(array $emails): array {
        $valid = [];
        $invalid = [];
        
        foreach ($emails as $email) {
            $email = \trim($email);
            
            if (empty($email)) {
                continue;
            }
            
            if (self::isValid($email)) {
                $valid[] = $email;
            } else {
                $invalid[] = $email;
            }
        }
        
        return [
            'valid' => $valid,
            'invalid' => $invalid
        ];
    }
    
    /**
     * Parse comma-separated email list
     *
     * @param string $email_list Comma-separated email addresses
     * @return array Array of valid email addresses
     */
    public static function parseEmailList(string $email_list): array {
        if (empty($email_list)) {
            return [];
        }
        
        // Split by comma and trim whitespace
        $emails = \array_map('trim', \explode(',', $email_list));
        
        // Filter out empty values and validate
        $emails = \array_filter($emails, function($email) {
            return !empty($email) && self::isValid($email);
        });
        
        // Remove duplicates and re-index
        return \array_values(\array_unique($emails));
    }
    
    /**
     * Sanitize email address
     *
     * @param string $email Email address to sanitize
     * @return string Sanitized email address
     */
    public static function sanitize(string $email): string {
        return \sanitize_email(\trim($email));
    }
    
    /**
     * Sanitize and validate email address
     *
     * @param string $email Email address to sanitize and validate
     * @return string|null Sanitized email if valid, null otherwise
     */
    public static function sanitizeAndValidate(string $email): ?string {
        $sanitized = self::sanitize($email);
        return self::isValid($sanitized) ? $sanitized : null;
    }
    
    /**
     * Get admin email as fallback
     *
     * @return string WordPress admin email
     */
    public static function getAdminEmail(): string {
        return \get_option('admin_email', '');
    }
    
    /**
     * Ensure at least one valid email exists
     *
     * If the provided array is empty, returns admin email as fallback.
     *
     * @param array $emails Array of email addresses
     * @return array Array with at least one valid email
     */
    public static function ensureValidEmails(array $emails): array {
        $valid_emails = \array_filter($emails, [self::class, 'isValid']);
        
        if (empty($valid_emails)) {
            $admin_email = self::getAdminEmail();
            if (self::isValid($admin_email)) {
                return [$admin_email];
            }
        }
        
        return \array_values($valid_emails);
    }
    
    /**
     * Format email list for display
     *
     * @param array $emails Array of email addresses
     * @param string $separator Separator string (default: ', ')
     * @return string Formatted email list
     */
    public static function formatEmailList(array $emails, string $separator = ', '): string {
        return \implode($separator, $emails);
    }
    
    /**
     * Check if email domain is valid
     *
     * @param string $email Email address
     * @return bool True if domain is valid
     */
    public static function hasValidDomain(string $email): bool {
        if (!self::isValid($email)) {
            return false;
        }
        
        $parts = \explode('@', $email);
        if (\count($parts) !== 2) {
            return false;
        }
        
        $domain = $parts[1];
        
        // Check if domain has valid DNS records
        return \checkdnsrr($domain, 'MX') || \checkdnsrr($domain, 'A');
    }
    
    /**
     * Validate email list from settings
     *
     * Parses and validates email list, with admin email fallback.
     *
     * @param string $email_list Comma-separated email list
     * @return array Array of valid email addresses
     */
    public static function validateEmailListFromSettings(string $email_list): array {
        $emails = self::parseEmailList($email_list);
        return self::ensureValidEmails($emails);
    }
}

