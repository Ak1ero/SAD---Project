<?php
/**
 * Email Mailer Class
 * Handles all email sending functionality using PHPMailer
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class Mailer {
    private $mailer;
    private $smtp_host;
    private $smtp_port;
    private $smtp_username;
    private $smtp_password;
    private $smtp_encryption;
    private $from_email;
    private $from_name;
    
    /**
     * Initialize the mailer with default settings
     */
    public function __construct() {
        try {
            // Check if PHPMailer is available
            if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                error_log("PHPMailer class not found. Email functionality will be limited.");
                $this->mailer = null;
                return;
            }

            // Load configuration
            $config = $this->loadConfig();
            
            // Initialize PHPMailer
            $this->mailer = new PHPMailer(true);
            
            // Set SMTP settings from config
            $this->smtp_host = $config['smtp_host'];
            $this->smtp_port = $config['smtp_port'];
            $this->smtp_username = $config['smtp_username'];
            $this->smtp_password = $config['smtp_password'];
            $this->smtp_encryption = $config['smtp_encryption'];
            $this->from_email = $config['from_email'];
            $this->from_name = $config['from_name'];
            
            // Configure PHPMailer
            $this->setupMailer();
        } catch (Exception $e) {
            error_log("Error initializing Mailer: " . $e->getMessage());
            // Initialize with null mailer, allowing other methods to detect this
            $this->mailer = null;
        }
    }
    
    /**
     * Load email configuration from file
     */
    private function loadConfig() {
        $config_file = __DIR__ . '/config.php';
        
        if (file_exists($config_file)) {
            return include $config_file;
        }
        
        // Default configuration if file doesn't exist
        return [
            'smtp_host' => 'smtp.example.com',
            'smtp_port' => 587,
            'smtp_username' => 'your-email@example.com',
            'smtp_password' => 'your-password',
            'smtp_encryption' => 'tls',
            'from_email' => 'no-reply@barnbackyard.com',
            'from_name' => 'The Barn & Backyard'
        ];
    }
    
    /**
     * Setup the PHPMailer instance with SMTP configuration
     */
    private function setupMailer() {
        try {
            // Load configuration again in case it was updated
            $config = $this->loadConfig();
            
            // Set debug level from config
            $debug_level = isset($config['debug_mode']) ? intval($config['debug_mode']) : 0;
            
            // Server settings
            $this->mailer->SMTPDebug = $debug_level;
            $this->mailer->Debugoutput = function($str, $level) {
                error_log("PHPMailer Debug: $str");
            };
            
            $this->mailer->isSMTP();
            $this->mailer->Host = $this->smtp_host;
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = $this->smtp_username;
            $this->mailer->Password = $this->smtp_password;
            $this->mailer->SMTPSecure = $this->smtp_encryption;
            $this->mailer->Port = $this->smtp_port;
            $this->mailer->setFrom($this->from_email, $this->from_name);
            $this->mailer->isHTML(true);
            $this->mailer->CharSet = 'UTF-8';
            
            // Set reasonable timeout values
            $this->mailer->Timeout = 60;
            $this->mailer->SMTPKeepAlive = true;
            
            // Check if we're in test mode
            if (!empty($config['test_mode']) && $config['test_mode']) {
                error_log("Mailer is in TEST MODE. Emails will not be sent.");
            }
            
        } catch (Exception $e) {
            error_log('Mailer setup error: ' . $e->getMessage());
            throw new Exception('Failed to set up email system: ' . $e->getMessage());
        }
    }
    
    /**
     * Send an email
     * 
     * @param string|array $to Recipient email(s)
     * @param string $subject Email subject
     * @param string $body Email body (HTML)
     * @param string $alt_body Plain text alternative (optional)
     * @param array $attachments Optional attachments
     * @return bool Success status
     */
    public function send($to, $subject, $body, $alt_body = '', $attachments = []) {
        try {
            // Get configuration to check test mode
            $config = $this->loadConfig();
            
            // If in test mode, log but don't actually send
            if (!empty($config['test_mode']) && $config['test_mode']) {
                error_log("TEST MODE: Would send email to: " . (is_array($to) ? implode(', ', $to) : $to));
                error_log("TEST MODE: Subject: $subject");
                
                // If test email is set, redirect to it
                if (!empty($config['test_email'])) {
                    $to = $config['test_email'];
                    error_log("TEST MODE: Redirecting to test email: $to");
                } else {
                    // Just return success without sending in test mode
                    return true;
                }
            }
            
            // Check if mailer is properly initialized
            if ($this->mailer === null) {
                // PHPMailer isn't available, use fallback mechanism
                error_log("Using fallback email mechanism as PHPMailer isn't available");
                return $this->sendFallbackEmail($to, $subject, $body, $attachments);
            }
            
            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();
            
            // Add recipient(s)
            if (is_array($to)) {
                foreach ($to as $email) {
                    $this->mailer->addAddress($email);
                }
            } else {
                $this->mailer->addAddress($to);
            }
            
            // Set content
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $body;
            
            if (!empty($alt_body)) {
                $this->mailer->AltBody = $alt_body;
            } else {
                // Create plain text version by stripping HTML
                $this->mailer->AltBody = strip_tags($body);
            }
            
            // Add attachments
            if (!empty($attachments)) {
                foreach ($attachments as $attachment) {
                    if (isset($attachment['path'])) {
                        if (isset($attachment['cid'])) {
                            // This is an embedded image
                            $this->mailer->addEmbeddedImage(
                                $attachment['path'], 
                                $attachment['cid'],
                                isset($attachment['name']) ? $attachment['name'] : basename($attachment['path']),
                                'base64',
                                'image/png'
                            );
                            error_log("Added embedded image: " . $attachment['path'] . " with CID: " . $attachment['cid']);
                        } else if (isset($attachment['name'])) {
                            // Regular attachment with a custom name
                            $this->mailer->addAttachment($attachment['path'], $attachment['name']);
                        } else {
                            // Regular attachment with default filename
                            $this->mailer->addAttachment($attachment['path']);
                        }
                    }
                }
            }
            
            // Send the email
            if (!$this->mailer->send()) {
                error_log('Email sending error: ' . $this->mailer->ErrorInfo);
                return false;
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log('Email sending error: ' . $e->getMessage());
            // Try fallback method
            return $this->sendFallbackEmail($to, $subject, $body, $attachments);
        }
    }
    
    /**
     * Fallback email sending method using PHP's mail() function
     * 
     * @param string|array $to Recipient email(s)
     * @param string $subject Email subject
     * @param string $body Email body (HTML)
     * @param array $attachments Optional attachments (will be ignored)
     * @return bool Success status
     */
    private function sendFallbackEmail($to, $subject, $body, $attachments = []) {
        try {
            // Get email address to send from
            $config = $this->loadConfig();
            $from_email = $config['from_email'] ?: 'noreply@barnbackyard.com';
            $from_name = $config['from_name'] ?: 'The Barn & Backyard';
            
            // Format recipient(s)
            $recipients = is_array($to) ? implode(', ', $to) : $to;
            
            // Basic headers
            $headers = "From: $from_name <$from_email>\r\n";
            $headers .= "Reply-To: $from_email\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            
            // Log that attachments are being skipped
            if (!empty($attachments)) {
                error_log("Fallback email method doesn't support attachments - " . count($attachments) . " attachment(s) skipped");
            }
            
            // For debugging, write email to a file
            $debug_dir = __DIR__ . '/logs';
            if (!file_exists($debug_dir)) {
                mkdir($debug_dir, 0755, true);
            }
            $debug_file = $debug_dir . '/email_' . time() . '_' . md5($recipients) . '.html';
            file_put_contents($debug_file, $body);
            error_log("Saved email content to $debug_file");
            
            // Send the email using mail() function
            $result = mail($recipients, $subject, $body, $headers);
            
            if (!$result) {
                error_log("Failed to send fallback email to $recipients");
            } else {
                error_log("Fallback email sent successfully to $recipients");
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("Fallback email error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send an invitation email to event guests
     * 
     * @param array $guest Guest information (email, name)
     * @param array $event Event details
     * @param string $unique_code_path Path to unique code image (not used)
     * @return bool Success status
     */
    public function sendInvitation($guest, $event, $unique_code_path = null) {
        try {
            // Load invitation template
            $template_path = __DIR__ . '/templates/invitation.html';
            if (!file_exists($template_path)) {
                error_log("Invitation template not found at: $template_path");
                return false;
            }
            
            $template = file_get_contents($template_path);
            
            // Replace placeholders in template
            $template = str_replace('{GUEST_NAME}', $guest['name'], $template);
            $template = str_replace('{EVENT_DATE}', $event['formatted_date'], $template);
            $template = str_replace('{EVENT_TIME}', $event['start_time'] . ' - ' . $event['end_time'], $template);
            $template = str_replace('{EVENT_LOCATION}', $event['venue_name'], $template);
            $template = str_replace('{EVENT_PACKAGE}', $event['package_name'], $template);
            $template = str_replace('{EVENT_THEME}', $event['theme_name'], $template);
            $template = str_replace('{BOOKING_REFERENCE}', $event['booking_reference'], $template);
            $template = str_replace('{BASE_URL}', $this->baseUrl, $template);
            
            // Get unique code from guest record
            $unique_code = !empty($guest['unique_code']) ? $guest['unique_code'] : '';
            
            // If no unique code exists, generate one
            if (empty($unique_code)) {
                $unique_code = substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 8);
                
                // Update the guest record with the new unique code
                $conn = $this->db_connection;
                $update_code_sql = "UPDATE guests SET unique_code = ? WHERE id = ?";
                $stmt = $conn->prepare($update_code_sql);
                $stmt->bind_param('si', $unique_code, $guest['id']);
                $stmt->execute();
                $stmt->close();
            }
            
            // Replace the unique code placeholder in the template
            $template = str_replace('{UNIQUE_CODE}', $unique_code, $template);
            
            // Configure email
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $this->smtp_host;
            $mail->SMTPAuth = true;
            $mail->Username = $this->smtp_username;
            $mail->Password = $this->smtp_password;
            $mail->SMTPSecure = $this->smtp_encryption;
            $mail->Port = $this->smtp_port;
            $mail->setFrom($this->from_email, $this->from_name);
            $mail->addAddress($guest['email'], $guest['name']);
            $mail->isHTML(true);
            $mail->Subject = "You're Invited: Event at " . $event['venue_name'];
            $mail->Body = $template;
            $mail->AltBody = strip_tags(str_replace('<br>', "\n", $template));
            
            // Send email
            $mail->send();
            
            return true;
        } catch (\Exception $e) {
            error_log("Mailer error (invitation): " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send invitation emails to multiple guests for an event
     * 
     * @param array $guests Array of guest information
     * @param array $event Event details
     * @return array Results of send operations
     */
    public function sendMultipleInvitations($guests, $event) {
        $results = [];
        
        foreach ($guests as $guest) {
            // Send invitation without QR code
            $success = $this->sendInvitation($guest, $event);
            
            $results[] = [
                'guest' => $guest,
                'success' => $success
            ];
        }
        
        return $results;
    }
} 