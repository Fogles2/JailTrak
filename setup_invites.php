<?php
require_once __DIR__ . '/../../../config/config.php';

echo "Setting up Invite Code System...\n\n";

try {
    $db = new PDO('sqlite:' . __DIR__ . '/../data/jailtrak.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Reading schema...\n";
    $schema = file_get_contents(__DIR__ . '/invite_schema.sql');
    
    echo "Creating tables...\n";
    $db->exec($schema);
    
    echo "✓ Invite system tables created successfully!\n\n";
    
    // Show default codes
    $codes = $db->query("SELECT code, description FROM invite_codes WHERE active = 1")->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Default invite codes created:\n";
    echo "========================================\n";
    foreach ($codes as $code) {
        echo "Code: {$code['code']}\n";
        echo "Purpose: {$code['description']}\n";
        echo "----------------------------------------\n";
    }
    
    echo "\nSetup complete!\n\n";
    echo "Next steps:\n";
    echo "1. Visit beta_access.php to test invite code entry\n";
    echo "2. Visit admin_invites.php to manage codes (password: admin123)\n";
    echo "3. Share invite codes with beta testers\n\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>