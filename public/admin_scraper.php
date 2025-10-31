<?php
session_start();
require_once __DIR__ . '/../config/config.php';

// Simple admin password check
$adminPassword = 'admin123'; // Change this!

if (!isset($_SESSION['scraper_admin_logged_in'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_password'])) {
        if ($_POST['admin_password'] === $adminPassword) {
            $_SESSION['scraper_admin_logged_in'] = true;
        } else {
            $error = 'Invalid admin password';
        }
    }
    
    if (!isset($_SESSION['scraper_admin_logged_in'])) {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Admin Login - Scraper Control</title>
            <style>
                body { 
                    font-family: Arial; 
                    background: #1a1a2e; 
                    color: #e0e0e0; 
                    display: flex; 
                    justify-content: center; 
                    align-items: center; 
                    height: 100vh; 
                    margin: 0;
                }
                .login-box { 
                    background: #2a2a4a; 
                    padding: 40px; 
                    border-radius: 15px; 
                    box-shadow: 0 10px 30px rgba(0,0,0,0.5);
                    max-width: 400px;
                    width: 100%;
                }
                h2 { color: #00d4ff; margin-bottom: 20px; }
                input { 
                    width: 100%; 
                    padding: 12px; 
                    margin: 10px 0; 
                    border: 2px solid rgba(255,255,255,0.2); 
                    border-radius: 8px;
                    background: #16213e;
                    color: #e0e0e0;
                    font-size: 1em;
                }
                button { 
                    width: 100%; 
                    padding: 12px; 
                    background: #00d4ff; 
                    color: #0f0f23; 
                    border: none; 
                    border-radius: 8px; 
                    cursor: pointer;
                    font-weight: 600;
                    font-size: 1em;
                }
                button:hover { background: #00b8e6; }
                .error { background: rgba(255,68,68,0.2); padding: 10px; border-radius: 5px; color: #ff6868; margin-bottom: 15px; }
            </style>
        </head>
        <body>
            <div class="login-box">
                <h2>🔐 Scraper Admin Access</h2>
                <?php if (isset($error)): ?>
                    <div class="error"><?= $error ?></div>
                <?php endif; ?>
                <form method="POST">
                    <input type="password" name="admin_password" placeholder="Admin Password" required autofocus>
                    <button type="submit">Login</button>
                </form>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin_scraper.php');
    exit;
}

// Handle scraper actions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'run_scraper') {
        $scriptPath = __DIR__ . '/../scripts/scraper.php';
        
        // Run scraper in background
        if (PHP_OS_FAMILY === 'Windows') {
            $command = "start /B php \"$scriptPath\" --once > NUL 2>&1";
        } else {
            $command = "php \"$scriptPath\" --once > /dev/null 2>&1 &";
        }
        
        exec($command, $output, $returnCode);
        
        if ($returnCode === 0) {
            $message = 'Scraper started successfully! Check logs for progress.';
            $messageType = 'success';
        } else {
            $message = 'Failed to start scraper. Check file permissions.';
            $messageType = 'error';
        }
    } elseif ($action === 'clear_logs') {
        $logFile = __DIR__ . '/../logs/scraper.log';
        if (file_exists($logFile)) {
            file_put_contents($logFile, '');
            $message = 'Logs cleared successfully!';
            $messageType = 'success';
        }
    }
}

// Get database stats
try {
    $db = new PDO('sqlite:' . __DIR__ . '/../data/jailtrak.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stats = [
        'total_inmates' => $db->query("SELECT COUNT(*) FROM inmates")->fetchColumn(),
        'in_jail' => $db->query("SELECT COUNT(*) FROM inmates WHERE in_jail = 1")->fetchColumn(),
        'released' => $db->query("SELECT COUNT(*) FROM inmates WHERE in_jail = 0")->fetchColumn(),
        'total_charges' => $db->query("SELECT COUNT(*) FROM charges")->fetchColumn(),
        'last_scrape' => $db->query("SELECT MAX(scrape_time) FROM scrape_logs")->fetchColumn(),
        'total_scrapes' => $db->query("SELECT COUNT(*) FROM scrape_logs")->fetchColumn(),
        'successful_scrapes' => $db->query("SELECT COUNT(*) FROM scrape_logs WHERE status = 'success'")->fetchColumn(),
        'failed_scrapes' => $db->query("SELECT COUNT(*) FROM scrape_logs WHERE status = 'error'")->fetchColumn(),
    ];
    
    // Get recent scrape logs
    $recentLogs = $db->query("\n        SELECT * FROM scrape_logs \n        ORDER BY scrape_time DESC \n        LIMIT 20\n    ")->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $stats = null;
    $recentLogs = [];
}

// Get log file content (last 100 lines)
$logFile = __DIR__ . '/../logs/scraper.log';
$logContent = '';
if (file_exists($logFile)) {
    $lines = file($logFile);
    $logContent = implode('', array_slice($lines, -100));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scraper Admin Dashboard</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0f0f23 0%, #1a1a2e 100%);
            color: #e0e0e0;
            padding: 20px;
            min-height: 100vh;
        }
        .container { max-width: 1600px; margin: 0 auto; }
        header {
            background: linear-gradient(135deg, #1e1e3f 0%, #2a2a4a 100%);
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            border: 1px solid rgba(0,212,255,0.3);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        h1 { 
            color: #00d4ff; 
            text-shadow: 0 0 20px rgba(0,212,255,0.5);
            font-size: 2em;
        }
        .logout-btn {
            background: #ff4444;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }
        .logout-btn:hover { 
            background: #cc0000;
            box-shadow: 0 0 20px rgba(255,68,68,0.5);
        }
        .message {
            padding: 15px 25px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 600;
        }
        .message.success {
            background: rgba(0,255,136,0.2);
            border-left: 4px solid #00ff88;
            color: #00ff88;
        }
        .message.error {
            background: rgba(255,68,68,0.2);
            border-left: 4px solid #ff4444;
            color: #ff6868;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: linear-gradient(135deg, #1e1e3f 0%, #2a2a4a 100%);
            padding: 25px;
            border-radius: 15px;
            border: 1px solid rgba(255,255,255,0.1);
            text-align: center;
            transition: all 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,212,255,0.3);
        }
        .stat-card .label {
            color: #a0a0a0;
            font-size: 0.9em;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        .stat-card .value {
            font-size: 2.5em;
            font-weight: bold;
            color: #00d4ff;
            text-shadow: 0 0 10px rgba(0,212,255,0.5);
        }
        .control-panel {
            background: linear-gradient(135deg, #1e1e3f 0%, #2a2a4a 100%);
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .control-panel h2 {
            color: #00d4ff;
            margin-bottom: 20px;
            font-size: 1.5em;
        }
        .button-group {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            font-size: 1em;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #00d4ff 0%, #0099cc 100%);
            color: #0f0f23;
            box-shadow: 0 0 15px rgba(0,212,255,0.3);
        }
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 25px rgba(0,212,255,0.5);
        }
        .btn-warning {
            background: linear-gradient(135deg, #ffaa00 0%, #ff8800 100%);
            color: #0f0f23;
        }
        .btn-warning:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 25px rgba(255,170,0,0.5);
        }
        .btn-danger {
            background: linear-gradient(135deg, #ff4444 0%, #cc0000 100%);
            color: white;
        }
        .btn-danger:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 25px rgba(255,68,68,0.5);
        }
        .card {
            background: linear-gradient(135deg, #1e1e3f 0%, #2a2a4a 100%);
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .card h2 {
            color: #00d4ff;
            margin-bottom: 20px;
            font-size: 1.5em;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th {
            background: linear-gradient(135deg, #00d4ff 0%, #0099cc 100%);
            color: #0f0f23;
            padding: 15px;
            text-align: left;
            font-weight: 700;
        }
        td {
            padding: 15px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            color: #e0e0e0;
        }
        tr:hover { background: rgba(0,212,255,0.05); }
        .status-success { color: #00ff88; font-weight: 600; }
        .status-error { color: #ff6868; font-weight: 600; }
        .log-viewer {
            background: #16213e;
            padding: 20px;
            border-radius: 10px;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
            color: #00ff88;
            max-height: 400px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .log-viewer::-webkit-scrollbar {
            width: 10px;
        }
        .log-viewer::-webkit-scrollbar-track {
            background: #0f0f23;
            border-radius: 5px;
        }
        .log-viewer::-webkit-scrollbar-thumb {
            background: #00d4ff;
            border-radius: 5px;
        }
        .refresh-notice {
            background: rgba(0,212,255,0.1);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #00d4ff;
            color: #00d4ff;
        }
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: 1fr; }
            .button-group { flex-direction: column; }
            header { flex-direction: column; gap: 20px; text-align: center; }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>🔧 Scraper Admin Dashboard</h1>
            <a href="?logout=1" class="logout-btn">Logout</a>
        </header>
        
        <?php if ($message): ?>
            <div class="message <?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if ($stats): ?>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="label">Total Inmates</div>
                <div class="value"><?= $stats['total_inmates'] ?></div>
            </div>
            <div class="stat-card">
                <div class="label">In Jail</div>
                <div class="value"><?= $stats['in_jail'] ?></div>
            </div>
            <div class="stat-card">
                <div class="label">Released</div>
                <div class="value"><?= $stats['released'] ?></div>
            </div>
            <div class="stat-card">
                <div class="label">Total Charges</div>
                <div class="value"><?= $stats['total_charges'] ?></div>
            </div>
            <div class="stat-card">
                <div class="label">Total Scrapes</div>
                <div class="value"><?= $stats['total_scrapes'] ?></div>
            </div>
            <div class="stat-card">
                <div class="label">Successful</div>
                <div class="value" style="color: #00ff88;">
                    <?= $stats['successful_scrapes'] ?></div>
            </div>
            <div class="stat-card">
                <div class="label">Failed</div>
                <div class="value" style="color: #ff6868;">
                    <?= $stats['failed_scrapes'] ?></div>
            </div>
            <div class="stat-card">
                <div class="label">Last Scrape</div>
                <div class="value" style="font-size: 1.2em;">
                    <?= $stats['last_scrape'] ? date('M j, g:i A', strtotime($stats['last_scrape'])) : 'Never' ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="control-panel">
            <h2>⚡ Scraper Controls</h2>
            <div class="refresh-notice">
                ℹ️ Scraper runs in the background. Check "Recent Scrape Logs" below for results.
            </div>
            <form method="POST" class="button-group">
                <button type="submit" name="action" value="run_scraper" class="btn btn-primary">
                    🚀 Run Scraper Now
                </button>
                <button type="submit" name="action" value="clear_logs" class="btn btn-danger" 
                        onclick="return confirm('Clear all logs?')">
                    🗑️ Clear Logs
                </button>
                <a href="index.php" class="btn btn-warning">
                    📊 View Dashboard
                </a>
            </form>
        </div>
        
        <div class="card">
            <h2>📋 Recent Scrape Logs (Last 20)</h2>
            <?php if (!empty($recentLogs)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Status</th>
                        <th>Inmates Found</th>
                        <th>Message</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentLogs as $log): ?>
                    <tr>
                        <td><?= date('M j, Y g:i A', strtotime($log['scrape_time'])) ?></td>
                        <td class="status-<?= $log['status'] ?>"><?= strtoupper($log['status']) ?></td>
                        <td><?= $log['inmates_found'] ?></td>
                        <td><?= htmlspecialchars($log['message']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p style="text-align: center; color: #808080; padding: 20px;">No scrape logs found</p>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <h2>📄 Live Log Viewer (Last 100 Lines)</h2>
            <div class="log-viewer">
                <?= htmlspecialchars($logContent) ?: 'No logs available' ?>
            </div>
        </div>
    </div>
</body>
</html>
