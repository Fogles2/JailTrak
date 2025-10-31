<?php
session_start();
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/invite_gate.php';

// Simple admin password check (you can enhance this)
$adminPassword = 'admin123'; // Change this!

if (!isset($_SESSION['admin_logged_in'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_password'])) {
        if ($_POST['admin_password'] === $adminPassword) {
            $_SESSION['admin_logged_in'] = true;
        } else {
            $error = 'Invalid admin password';
        }
    }
    
    if (!isset($_SESSION['admin_logged_in'])) {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Admin Login</title>
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
                <h2>🔐 Admin Access</h2>
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

// Database connection
$db = new PDO('sqlite:' . __DIR__ . '/../data/jailtrak.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create') {
        $code = strtoupper(trim($_POST['code']));
        $description = trim($_POST['description']);
        $maxUses = intval($_POST['max_uses']);
        $expiresAt = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
        
        $stmt = $db->prepare("
            INSERT INTO invite_codes (code, description, created_by, max_uses, expires_at)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$code, $description, 'Admin', $maxUses, $expiresAt]);
        
        $success = "Invite code created: $code";
    } elseif ($_POST['action'] === 'toggle') {
        $id = intval($_POST['id']);
        $db->prepare("UPDATE invite_codes SET active = NOT active WHERE id = ?")->execute([$id]);
    } elseif ($_POST['action'] === 'delete') {
        $id = intval($_POST['id']);
        $db->prepare("DELETE FROM invite_codes WHERE id = ?")->execute([$id]);
    }
}

// Get all invite codes
$invites = $db->query("SELECT * FROM invite_codes ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Get usage stats
$stats = [
    'total_codes' => count($invites),
    'active_codes' => count(array_filter($invites, fn($i) => $i['active'] == 1)),
    'total_uses' => array_sum(array_column($invites, 'uses')),
    'recent_uses' => $db->query("SELECT COUNT(*) FROM invite_usage_log WHERE used_at >= datetime('now', '-24 hours')")->fetchColumn()
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invite Code Management</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0f0f23 0%, #1a1a2e 100%);
            color: #e0e0e0;
            padding: 20px;
        }
        .container { max-width: 1400px; margin: 0 auto; }
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
        h1 { color: #00d4ff; text-shadow: 0 0 20px rgba(0,212,255,0.5); }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: linear-gradient(135deg, #1e1e3f 0%, #2a2a4a 100%);
            padding: 25px;
            border-radius: 15px;
            border: 1px solid rgba(255,255,255,0.1);
            text-align: center;
        }
        .stat-card .label { color: #a0a0a0; margin-bottom: 10px; }
        .stat-card .value { font-size: 2.5em; font-weight: bold; color: #00d4ff; text-shadow: 0 0 10px rgba(0,212,255,0.5); }
        .card {
            background: linear-gradient(135deg, #1e1e3f 0%, #2a2a4a 100%);
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .card h2 { color: #00d4ff; margin-bottom: 20px; }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #a0a0a0;
            font-weight: 600;
        }
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid rgba(255,255,255,0.2);
            border-radius: 8px;
            background: #16213e;
            color: #e0e0e0;
            font-size: 1em;
        }
        .form-group input:focus, .form-group textarea:focus {
            outline: none;
            border-color: #00d4ff;
            box-shadow: 0 0 10px rgba(0,212,255,0.3);
        }
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-primary {
            background: linear-gradient(135deg, #00d4ff 0%, #0099cc 100%);
            color: #0f0f23;
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 5px 20px rgba(0,212,255,0.5); }
        .btn-danger { background: #ff4444; color: white; padding: 8px 15px; font-size: 0.9em; }
        .btn-danger:hover { background: #cc0000; }
        .btn-toggle { background: #ffaa00; color: white; padding: 8px 15px; font-size: 0.9em; margin-right: 10px; }
        .btn-toggle:hover { background: #cc8800; }
        table { width: 100%; border-collapse: collapse; }
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
        }
        tr:hover { background: rgba(0,212,255,0.05); }
        .code-display {
            font-family: 'Courier New', monospace;
            font-size: 1.1em;
            color: #00ff88;
            font-weight: bold;
        }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 600;
        }
        .badge-active { background: rgba(0,255,136,0.3); color: #00ff88; border: 1px solid #00cc6a; }
        .badge-inactive { background: rgba(255,68,68,0.3); color: #ff6868; border: 1px solid #ff4444; }
        .success {
            background: rgba(0,255,136,0.2);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #00ff88;
            color: #00ff88;
        }
        .logout-btn {
            background: #ff4444;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>🔑 Invite Code Management</h1>
            <a href="?logout=1" class="logout-btn">Logout</a>
        </header>
        
        <?php if (isset($success)): ?>
            <div class="success">✓ <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="label">Total Codes</div>
                <div class="value"><?= $stats['total_codes'] ?></div>
            </div>
            <div class="stat-card">
                <div class="label">Active Codes</div>
                <div class="value"><?= $stats['active_codes'] ?></div>
            </div>
            <div class="stat-card">
                <div class="label">Total Uses</div>
                <div class="value"><?= $stats['total_uses'] ?></div>
            </div>
            <div class="stat-card">
                <div class="label">Last 24h Uses</div>
                <div class="value"><?= $stats['recent_uses'] ?></div>
            </div>
        </div>
        
        <div class="card">
            <h2>➕ Create New Invite Code</h2>
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Code *</label>
                        <input type="text" name="code" placeholder="BETA-2025-XXXX" required>
                    </div>
                    <div class="form-group">
                        <label>Max Uses (-1 = unlimited)</label>
                        <input type="number" name="max_uses" value="-1">
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <input type="text" name="description" placeholder="Purpose of this code">
                    </div>
                    <div class="form-group">
                        <label>Expires At (optional)</label>
                        <input type="datetime-local" name="expires_at">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Create Invite Code</button>
            </form>
        </div>
        
        <div class="card">
            <h2>📋 All Invite Codes</h2>
            <table>
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Description</th>
                        <th>Uses</th>
                        <th>Max Uses</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Last Used</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invites as $invite): ?>
                        <tr>
                            <td class="code-display"><?= htmlspecialchars($invite['code']) ?></td>
                            <td><?= htmlspecialchars($invite['description']) ?></td>
                            <td><?= $invite['uses'] ?></td>
                            <td><?= $invite['max_uses'] == -1 ? '∞' : $invite['max_uses'] ?></td>
                            <td>
                                <span class="badge <?= $invite['active'] ? 'badge-active' : 'badge-inactive' ?>">
                                    <?= $invite['active'] ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td><?= date('M j, Y', strtotime($invite['created_at'])) ?></td>
                            <td><?= $invite['last_used_at'] ? date('M j, Y g:i A', strtotime($invite['last_used_at'])) : 'Never' ?></td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= $invite['id'] ?>">
                                    <button type="submit" class="btn btn-toggle">
                                        <?= $invite['active'] ? 'Deactivate' : 'Activate' ?>
                                    </button>
                                </form>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $invite['id'] ?>">
                                    <button type="submit" class="btn btn-danger" onclick="return confirm('Delete this code?')">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
<?php
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin_invites.php');
    exit;
}
?>