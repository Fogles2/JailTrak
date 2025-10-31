-- Probation Officers Table
CREATE TABLE IF NOT EXISTS probation_officers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    badge_number TEXT,
    department TEXT,
    email_notifications INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Watchlist Table
CREATE TABLE IF NOT EXISTS watchlist (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    officer_id INTEGER NOT NULL,
    inmate_name TEXT NOT NULL,
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (officer_id) REFERENCES probation_officers(id) ON DELETE CASCADE
);

-- Probation Alerts Table
CREATE TABLE IF NOT EXISTS probation_alerts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    officer_id INTEGER NOT NULL,
    inmate_id TEXT NOT NULL,
    inmate_name TEXT NOT NULL,
    alert_title TEXT NOT NULL,
    alert_message TEXT NOT NULL,
    read_status INTEGER DEFAULT 0,
    email_sent INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (officer_id) REFERENCES probation_officers(id) ON DELETE CASCADE
);

-- Create indexes
CREATE INDEX IF NOT EXISTS idx_watchlist_officer ON watchlist(officer_id);
CREATE INDEX IF NOT EXISTS idx_watchlist_name ON watchlist(inmate_name);
CREATE INDEX IF NOT EXISTS idx_alerts_officer ON probation_alerts(officer_id);
CREATE INDEX IF NOT EXISTS idx_alerts_read ON probation_alerts(read_status);