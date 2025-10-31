# 🏛️ Clayton County Jail Scraper Dashboard

A comprehensive PHP-based web scraper and dashboard for tracking inmate data from Clayton County Jail's public records.

## ✨ Features

- **Automated Scraping**: Runs for 48 hours, checking every hour
- **Beautiful Dashboard**: Modern, responsive UI with real-time statistics
- **Advanced Filtering**: Filter by charge type, sex, felony/misdemeanor
- **Search Functionality**: Search by name or charge description
- **SQLite Database**: Lightweight, serverless database
- **Detailed Analytics**: Statistics cards with visual breakdown
- **Logging System**: Complete activity and error logging

## 📋 Requirements

- PHP 7.4 or higher
- SQLite3 extension (usually included with PHP)
- cURL extension (usually included with PHP)
- Command-line access for scraper

## 🚀 Installation

### 1. Clone or Download

```bash
git clone <your-repo-url>
cd clayton-county-jail-scraper
```

### 2. Initialize Database

```bash
php -r "require 'config.php'; \$db = new PDO('sqlite:' . DB_PATH); \$db->exec(file_get_contents('schema.sql'));"
```

Or simply run the scraper once (it will create the database automatically):

```bash
php scraper.php --once
```

### 3. Start the Web Server

```bash
php -S localhost:8000
```

Then open http://localhost:8000 in your browser.

## 🔧 Usage

### Running the Scraper

**48-hour continuous scraping:**
```bash
php scraper.php
```

**Single scrape (for testing):**
```bash
php scraper.php --once
```

**Run in background:**
```bash
nohup php scraper.php > /dev/null 2>&1 &
```

### Setting Up Cron Job

To run automatically every hour:

```bash
crontab -e
```

Add this line:
```
0 * * * * cd /path/to/scraper && php scraper.php --once
```

## 📊 Dashboard Features

### Statistics Cards
- Total Inmates
- Male/Female breakdown
- Felony count
- Misdemeanor count

### Filtering Options
- View all inmates
- Filter by Felonies
- Filter by Misdemeanors
- Filter by Male inmates
- Filter by Female inmates

### Search
- Search by inmate name
- Search by charge description

### Data Table
- Sortable columns
- Color-coded badges for charge types
- Responsive design for mobile devices

## 🗂️ File Structure

```
.
├── config.php          # Configuration settings
├── schema.sql          # Database schema
├── scraper.php         # Main scraper script
├── index.php           # Dashboard interface
├── jail_data.db        # SQLite database (created automatically)
├── scraper.log         # Activity logs (created automatically)
└── README.md           # This file
```

## ⚙️ Configuration

Edit `config.php` to customize:

```php
// Scraping interval (in seconds)
define('SCRAPE_INTERVAL', 3600); // 1 hour

// Total duration (in seconds)
define('SCRAPE_DURATION', 172800); // 48 hours

// Request timeout
define('TIMEOUT', 30);
```

## 🔍 Database Schema

### Tables:
1. **inmates**: Stores inmate personal information
2. **charges**: Stores charge details linked to inmates
3. **scrape_logs**: Tracks all scraping activities

## 🐛 Troubleshooting

### Database not created
```bash
chmod 755 .
php -r "require 'config.php'; \$db = new PDO('sqlite:' . DB_PATH); \$db->exec(file_get_contents('schema.sql'));"
```

### Scraper not working
- Check if cURL is enabled: `php -m | grep curl`
- Check error logs: `tail -f scraper.log`
- Verify URL is accessible: `curl -I https://weba.claytoncountyga.gov/sjiinqcgi-bin/wsj210r.pgm?days=02&rtype=F`

### Permission denied
```bash
chmod +x scraper.php
chmod 755 .
```

## 📝 Notes

- The scraper respects the website's structure and adds delays between requests
- Data is stored locally in SQLite database
- The HTML parsing is adaptive and handles different formats
- All timestamps are in America/New_York timezone

## ⚖️ Legal Notice

This tool scrapes publicly available data. Ensure you comply with:
- The website's Terms of Service
- Local laws regarding data scraping
- Data privacy regulations

Use responsibly and ethically.

## 🤝 Contributing

Feel free to submit issues, fork the repository, and create pull requests for any improvements.

## 📄 License

MIT License - Feel free to use and modify as needed.

---

**Happy Scraping! 🎉**