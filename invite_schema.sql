-- Invite Codes Table
CREATE TABLE IF NOT EXISTS invite_codes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT UNIQUE NOT NULL,
    description TEXT,
    created_by TEXT,
    max_uses INTEGER DEFAULT -1, -- -1 for unlimited
    uses INTEGER DEFAULT 0,
    active INTEGER DEFAULT 1,
    expires_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_used_at DATETIME
);

-- Invite Usage Log
CREATE TABLE IF NOT EXISTS invite_usage_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    invite_code_id INTEGER NOT NULL,
    ip_address TEXT,
    user_agent TEXT,
    used_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invite_code_id) REFERENCES invite_codes(id) ON DELETE CASCADE
);

-- Create indexes
CREATE INDEX IF NOT EXISTS idx_invite_code ON invite_codes(code);
CREATE INDEX IF NOT EXISTS idx_invite_active ON invite_codes(active);
CREATE INDEX IF NOT EXISTS idx_usage_code ON invite_usage_log(invite_code_id);
CREATE INDEX IF NOT EXISTS idx_usage_time ON invite_usage_log(used_at);

-- Insert some default invite codes for testing
INSERT OR IGNORE INTO invite_codes (code, description, created_by, max_uses, active) VALUES
('BETA-2025-LAUNCH', 'Initial beta launch code', 'System', -1, 1),
('TEAM-MEMBER-001', 'Team member access', 'System', 10, 1),
('DEMO-ACCESS-123', 'Demo account access', 'System', 100, 1);