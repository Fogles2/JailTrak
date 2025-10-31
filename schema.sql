-- Inmates Table
CREATE TABLE IF NOT EXISTS inmates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    inmate_id TEXT UNIQUE,  -- Docket Number
    name TEXT NOT NULL,
    booking_date TEXT,
    booking_time TEXT,
    sex TEXT,
    race TEXT,
    age INTEGER,
    bond_amount TEXT,
    in_jail INTEGER DEFAULT 1,  -- 1 = in jail, 0 = released
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Charges Table
CREATE TABLE IF NOT EXISTS charges (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    inmate_id TEXT NOT NULL,
    charge_description TEXT NOT NULL,
    charge_type TEXT, -- 'Felony', 'Misdemeanor', or 'Unknown'
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (inmate_id) REFERENCES inmates(inmate_id) ON DELETE CASCADE
);

-- Scraping Logs Table
CREATE TABLE IF NOT EXISTS scrape_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    scrape_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    status TEXT NOT NULL, -- 'success' or 'error'
    inmates_found INTEGER DEFAULT 0,
    message TEXT,
    error_details TEXT
);

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_inmates_name ON inmates(name);
CREATE INDEX IF NOT EXISTS idx_inmates_sex ON inmates(sex);
CREATE INDEX IF NOT EXISTS idx_inmates_booking_date ON inmates(booking_date);
CREATE INDEX IF NOT EXISTS idx_inmates_in_jail ON inmates(in_jail);
CREATE INDEX IF NOT EXISTS idx_charges_type ON charges(charge_type);
CREATE INDEX IF NOT EXISTS idx_charges_inmate_id ON charges(inmate_id);
CREATE INDEX IF NOT EXISTS idx_scrape_logs_time ON scrape_logs(scrape_time);