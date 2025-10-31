<?php
session_start();
require_once __DIR__ . '/../../../config/config.php';

// If already verified, redirect to main dashboard
if (isset($_SESSION['invite_verified']) && $_SESSION['invite_verified'] === true) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inviteCode = trim(strtoupper($_POST['invite_code']));
    
    if (empty($inviteCode)) {
        $error = 'Please enter an invite code';
    } else {
        require_once __DIR__ . '/../../../config/invite_gate.php';
        
        if (validateInviteCode($inviteCode)) {
            $_SESSION['invite_verified'] = true;
            $_SESSION['invite_code_used'] = $inviteCode;
            $_SESSION['invite_timestamp'] = time();
            
            // Redirect to main dashboard
            header('Location: index.php');
            exit;
        } else {
            $error = 'Invalid or expired invite code';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beta Access - Clayton County Jail Dashboard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0f0f23 0%, #1a1a2e 50%, #16213e 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            color: #e0e0e0;
        }
        
        .beta-container {
            background: linear-gradient(135deg, #1e1e3f 0%, #2a2a4a 100%);
            padding: 50px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
            max-width: 600px;
            width: 100%;
            border: 1px solid rgba(0,212,255,0.3);
            text-align: center;
        }
        
        .beta-badge {
            display: inline-block;
            background: linear-gradient(135deg, #ff6b00 0%, #ff4444 100%);
            color: white;
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 700;
            margin-bottom: 20px;
            text-transform: uppercase;
            letter-spacing: 2px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.8; transform: scale(1.05); }
        }
        
        h1 {
            color: #00d4ff;
            font-size: 2.5em;
            margin-bottom: 15px;
            text-shadow: 0 0 20px rgba(0,212,255,0.5);
        }
        
        .subtitle {
            color: #a0a0a0;
            font-size: 1.2em;
            margin-bottom: 30px;
        }
        
        .info-box {
            background: rgba(0,212,255,0.1);
            padding: 20px;
            border-radius: 10px;
            border-left: 4px solid #00d4ff;
            margin-bottom: 30px;
            text-align: left;
        }
        
        .info-box h3 {
            color: #00d4ff;
            margin-bottom: 10px;
        }
        
        .info-box p {
            color: #e0e0e0;
            line-height: 1.6;
            margin-bottom: 10px;
        }
        
        .info-box ul {
            list-style: none;
            padding: 0;
            margin-top: 15px;
        }
        
        .info-box ul li {
            padding: 8px 0;
            color: #a0a0a0;
        }
        
        .info-box ul li:before {
            content: "✓ ";
            color: #00ff88;
            font-weight: bold;
            margin-right: 10px;
        }
        
        .form-container {
            background: rgba(22,33,62,0.6);
            padding: 30px;
            border-radius: 15px;
            margin-top: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 10px;
            color: #a0a0a0;
            font-weight: 600;
            font-size: 0.95em;
        }
        
        .form-group input {
            width: 100%;
            padding: 15px;
            border: 2px solid rgba(255,255,255,0.2);
            border-radius: 10px;
            background: #16213e;
            color: #e0e0e0;
            font-size: 1.2em;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 2px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #00d4ff;
            box-shadow: 0 0 20px rgba(0,212,255,0.3);
        }
        
        .btn-submit {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #00d4ff 0%, #0099cc 100%);
            color: #0f0f23;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 700;
            font-size: 1.1em;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .btn-submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0,212,255,0.5);
        }
        
        .error {
            background: rgba(255,68,68,0.2);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #ff4444;
            color: #ff6868;
            text-align: left;
        }
        
        .request-access {
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        
        .request-access p {
            color: #a0a0a0;
            margin-bottom: 15px;
        }
        
        .request-access a {
            color: #00d4ff;
            text-decoration: none;
            font-weight: 600;
        }
        
        .request-access a:hover {
            text-decoration: underline;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-top: 20px;
        }
        
        .feature-item {
            background: rgba(0,212,255,0.05);
            padding: 15px;
            border-radius: 8px;
            border: 1px solid rgba(0,212,255,0.2);
        }
        
        .feature-item .icon {
            font-size: 2em;
            margin-bottom: 10px;
        }
        
        .feature-item .title {
            color: #00d4ff;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .feature-item .desc {
            color: #a0a0a0;
            font-size: 0.9em;
        }
        
        @media (max-width: 768px) {
            .beta-container {
                padding: 30px 20px;
            }
            
            h1 {
                font-size: 2em;
            }
            
            .features-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="beta-container">
        <div class="beta-badge">🔒 Closed Beta</div>
        
        <h1>🏛️ Clayton County Jail Dashboard</h1>
        <p class="subtitle">Real-time Inmate Tracking & Analytics System</p>
        
        <div class="info-box">
            <h3>Welcome to the Beta Program</h3>
            <p>This platform is currently in closed beta testing. You need a valid invite code to access the dashboard.</p>
            
            <div class="features-grid">
                <div class="feature-item">
                    <div class="icon">📊</div>
                    <div class="title">Live Data</div>
                    <div class="desc">Real-time inmate information</div>
                </div>
                <div class="feature-item">
                    <div class="icon">🎰</div>
                    <div class="title">Bond Roulette</div>
                    <div class="desc">Random inmate bond info</div>
                </div>
                <div class="feature-item">
                    <div class="icon">🔍</div>
                    <div class="title">Advanced Search</div>
                    <div class="desc">Filter by charge type</div>
                </div>
                <div class="feature-item">
                    <div class="icon">👮</div>
                    <div class="title">Officer Alerts</div>
                    <div class="desc">Probation violation monitoring</div>
                </div>
            </div>
        </div>
        
        <?php if ($error): ?>
            <div class="error">
                ❌ <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <div class="form-container">
            <form method="POST">
                <div class="form-group">
                    <label>🔑 Enter Your Invite Code</label>
                    <input 
                        type="text" 
                        name="invite_code" 
                        placeholder="XXXX-XXXX-XXXX" 
                        required 
                        maxlength="20"
                        autocomplete="off"
                        autofocus
                    >
                </div>
                
                <button type="submit" class="btn-submit">🚀 Access Dashboard</button>
            </form>
        </div>
        
        <div class="request-access">
            <p>Don't have an invite code?</p>
            <a href="mailto:admin@claytoncounty-dashboard.com?subject=Beta Access Request">Request Beta Access</a>
        </div>
    </div>
</body>
</html>