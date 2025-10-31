<?php
session_start();
require_once __DIR__ . '/../../../config/config.php';

// Check if user has valid invite code in session
function checkInviteAccess() {
    if (!isset($_SESSION['invite_verified']) || $_SESSION['invite_verified'] !== true) {
        header('Location: beta_access.php');
        exit;
    }
}

// Validate invite code
function validateInviteCode($code) {
    try {
        $db = new PDO('sqlite:' . __DIR__ . '/../data/jailtrak.db');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $db->prepare("
            SELECT * FROM invite_codes 
            WHERE code = ? AND active = 1 
            AND (max_uses = -1 OR uses < max_uses)
            AND (expires_at IS NULL OR expires_at > datetime('now'))
        ");
        $stmt->execute([strtoupper($code)]);
        $invite = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($invite) {
            // Increment usage count
            $db->prepare("UPDATE invite_codes SET uses = uses + 1, last_used_at = CURRENT_TIMESTAMP WHERE id = ?")
               ->execute([$invite['id']]);
            
            // Log the access
            $db->prepare("
                INSERT INTO invite_usage_log (invite_code_id, ip_address, user_agent, used_at)
                VALUES (?, ?, ?, CURRENT_TIMESTAMP)
            ")->execute([
                $invite['id'],
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
            
            return true;
        }
        
        return false;
    } catch (PDOException $e) {
        error_log("Invite code validation error: " . $e->getMessage());
        return false;
    }
}
?>