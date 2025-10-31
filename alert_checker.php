<?php
require_once __DIR__ . '/../../../config/config.php';

/**
 * Alert Checker - Monitors new inmates for probation violations
 * Run this script via cron every 10-15 minutes
 * Example cron: */15 * * * * php /path/to/alert_checker.php
 */

class AlertChecker {
    private $db;
    
    public function __construct() {
        try {
            $this->db = new PDO('sqlite:' . __DIR__ . '/../data/jailtrak.db');
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            $this->log("Database connection failed: " . $e->getMessage(), 'error');
            die();
        }
    }
    
    private function log($message, $level = 'info') {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [ALERT-CHECKER] [$level] $message\n";
        file_put_contents(LOG_FILE, $logMessage, FILE_APPEND);
        echo $logMessage;
    }
    
    public function checkForMatches() {
        $this->log("Starting alert check...");
        
        // Get all active watchlists
        $watchlists = $this->db->query("
            SELECT w.*, po.name as officer_name, po.email as officer_email, po.email_notifications
            FROM watchlist w
            INNER JOIN probation_officers po ON w.officer_id = po.id
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        $this->log("Found " . count($watchlists) . " active watchlist entries");
        
        $alertCount = 0;
        
        foreach ($watchlists as $watch) {
            // Check for new inmates matching this name with probation violations
            $stmt = $this->db->prepare("
                SELECT i.*, c.charge_description, c.charge_type
                FROM inmates i
                INNER JOIN charges c ON i.inmate_id = c.inmate_id
                WHERE UPPER(i.name) = UPPER(?)
                AND (c.charge_description LIKE '%PROBATION%' OR c.charge_description LIKE '%VIOLATION%')
                AND i.updated_at >= datetime('now', '-1 hour')
                AND NOT EXISTS (
                    SELECT 1 FROM probation_alerts 
                    WHERE inmate_id = i.inmate_id 
                    AND officer_id = ?
                )
            ");
            
            $stmt->execute([$watch['inmate_name'], $watch['officer_id']]);
            $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($matches as $match) {
                // Create alert
                $alertTitle = "Watchlist Alert: {$match['name']} Booked";
                $alertMessage = "{$match['name']} was booked on {$match['booking_date']} at {$match['booking_time']} with charge: {$match['charge_description']}. Docket: {$match['inmate_id']}";
                
                $stmt = $this->db->prepare("
                    INSERT INTO probation_alerts 
                    (officer_id, inmate_id, inmate_name, alert_title, alert_message, email_sent, created_at)
                    VALUES (?, ?, ?, ?, ?, 0, CURRENT_TIMESTAMP)
                ");
                
                $stmt->execute([
                    $watch['officer_id'],
                    $match['inmate_id'],
                    $match['name'],
                    $alertTitle,
                    $alertMessage
                ]);
                
                $this->log("Alert created for Officer {$watch['officer_name']}: {$match['name']} (Docket: {$match['inmate_id']})");
                
                // Send email if notifications enabled
                if ($watch['email_notifications'] == 1) {
                    $emailSent = $this->sendEmailAlert(
                        $watch['officer_email'],
                        $watch['officer_name'],
                        $alertTitle,
                        $alertMessage,
                        $match,
                        $watch['notes']
                    );
                    
                    if ($emailSent) {
                        // Mark as email sent
                        $alertId = $this->db->lastInsertId();
                        $this->db->prepare("UPDATE probation_alerts SET email_sent = 1 WHERE id = ?")
                                 ->execute([$alertId]);
                    }
                }
                
                $alertCount++;
            }
        }
        
        $this->log("Alert check completed. Created $alertCount new alerts.");
        return $alertCount;
    }
    
    private function sendEmailAlert($toEmail, $officerName, $alertTitle, $alertMessage, $inmateData, $notes) {
        $subject = "🚨 Probation Violation Alert - " . $inmateData['name'];
        
        $htmlBody = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #6495ed 0%, #4169e1 100%); color: white; padding: 30px; border-radius: 10px 10px 0 0; }
                .header h1 { margin: 0; font-size: 24px; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                .alert-box { background: #fff3cd; border-left: 4px solid #ff6b00; padding: 15px; margin: 20px 0; border-radius: 5px; }
                .info-grid { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; }
                .info-row { display: flex; padding: 10px 0; border-bottom: 1px solid #eee; }
                .info-label { font-weight: bold; width: 150px; color: #555; }
                .info-value { flex: 1; color: #333; }
                .button { display: inline-block; background: #6495ed; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin-top: 20px; }
                .footer { text-align: center; color: #777; font-size: 12px; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🚨 Probation Violation Alert</h1>
                    <p>Clayton County Jail - Inmate Alert System</p>
                </div>
                <div class='content'>
                    <p>Hello <strong>{$officerName}</strong>,</p>
                    
                    <div class='alert-box'>
                        <strong>⚠️ WATCHLIST MATCH DETECTED</strong><br>
                        An inmate on your watchlist has been booked with a probation violation.
                    </div>
                    
                    <div class='info-grid'>
                        <h3 style='margin-top: 0; color: #6495ed;'>Inmate Information</h3>
                        <div class='info-row'>
                            <div class='info-label'>Name:</div>
                            <div class='info-value'><strong>{$inmateData['name']}</strong></div>
                        </div>
                        <div class='info-row'>
                            <div class='info-label'>Docket Number:</div>
                            <div class='info-value'>{$inmateData['inmate_id']}</div>
                        </div>
                        <div class='info-row'>
                            <div class='info-label'>Age:</div>
                            <div class='info-value'>{$inmateData['age']}</div>
                        </div>
                        <div class='info-row'>
                            <div class='info-label'>Booking Date:</div>
                            <div class='info-value'>{$inmateData['booking_date']}</div>
                        </div>
                        <div class='info-row'>
                            <div class='info-label'>Booking Time:</div>
                            <div class='info-value'>{$inmateData['booking_time']}</div>
                        </div>
                        <div class='info-row'>
                            <div class='info-label'>Charge:</div>
                            <div class='info-value'><strong>{$inmateData['charge_description']}</strong></div>
                        </div>
                        <div class='info-row'>
                            <div class='info-label'>Bond Amount:</div>
                            <div class='info-value'>{$inmateData['bond_amount']}</div>
                        </div>
                        <div class='info-row'>
                            <div class='info-label'>Status:</div>
                            <div class='info-value'>" . ($inmateData['in_jail'] ? '🔒 IN JAIL' : '✓ Released') . "</div>
                        </div>
                    </div>
                    
                    " . (!empty($notes) ? "
                    <div style='background: #e3f2fd; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                        <strong>Your Notes:</strong><br>
                        {$notes}
                    </div>
                    " : "") . "
                    
                    <p style='margin-top: 20px;'>
                        <a href='" . (defined('SITE_URL') ? SITE_URL : 'http://localhost') . "/probation_dashboard.php' class='button'>
                            View Dashboard
                        </a>
                    </p>
                    
                    <div class='footer'>
                        <p>This is an automated alert from the Clayton County Jail Inmate Alert System.</p>
                        <p>To manage your watchlist or notification settings, please login to your dashboard.</p>
                    </div>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=utf-8',
            'From: Clayton County Alerts <noreply@claytoncountyalerts.com>',
            'Reply-To: noreply@claytoncountyalerts.com',
            'X-Mailer: PHP/' . phpversion(),
            'X-Priority: 1 (Highest)',
            'Importance: High'
        ];
        
        $success = mail($toEmail, $subject, $htmlBody, implode("\r\n", $headers));
        
        if ($success) {
            $this->log("Email sent successfully to {$toEmail} for {$inmateData['name']}");
        } else {
            $this->log("Failed to send email to {$toEmail}", 'error');
        }
        
        return $success;
    }
}

// Run the checker
if (php_sapi_name() === 'cli') {
    $checker = new AlertChecker();
    $checker->checkForMatches();
} else {
    echo "This script must be run from command line.\n";
    echo "Setup a cron job: */15 * * * * php " . __FILE__ . "\n";
}
?>