-- Enhanced schema for JailTrak

-- Users table for authentication
CREATE TABLE Users (
    user_id SERIAL PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL, -- e.g., 'admin', 'officer', 'visitor'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Court Cases table
CREATE TABLE CourtCases (
    case_id SERIAL PRIMARY KEY,
    user_id INT REFERENCES Users(user_id),
    case_number VARCHAR(50) NOT NULL,
    case_description TEXT,
    status VARCHAR(20), -- e.g., 'active', 'closed'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Hearings table
CREATE TABLE Hearings (
    hearing_id SERIAL PRIMARY KEY,
    case_id INT REFERENCES CourtCases(case_id),
    hearing_date TIMESTAMP NOT NULL,
    location VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Jail Records table (with improved structure)
CREATE TABLE JailRecords (
    record_id SERIAL PRIMARY KEY,
    user_id INT REFERENCES Users(user_id),
    case_id INT REFERENCES CourtCases(case_id),
    admission_date TIMESTAMP NOT NULL,
    release_date TIMESTAMP,
    status VARCHAR(20), -- e.g., 'incarcerated', 'released'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);