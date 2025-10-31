<?php
require_once __DIR__ . '/../../../config/config.php';

class JailScraper {
    private $db;
    private $baseUrl = 'https://weba.claytoncountyga.gov';
    
    public function __construct() {
        $this->initDatabase();
    }
    
    private function initDatabase() {
        try {
            $this->db = new PDO('sqlite:' . __DIR__ . '/../data/jailtrak.db');
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Create tables
            $schema = file_get_contents(__DIR__ . '/schema.sql');
            $this->db->exec($schema);
            
            $this->log("Database initialized successfully");
        } catch (PDOException $e) {
            $this->log("Database error: " . $e->getMessage(), 'error');
            die("Database initialization failed\n");
        }
    }
    
    private function log($message, $level = 'info') {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [$level] $message\n";
        file_put_contents(LOG_FILE, $logMessage, FILE_APPEND);
        echo $logMessage;
    }
    
    private function fetchPage($url) {
        $this->log("Fetching URL: $url");
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => TIMEOUT,
            CURLOPT_USERAGENT => USER_AGENT,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("cURL Error: $error");
        }
        
        if ($httpCode !== 200) {
            throw new Exception("HTTP Error: $httpCode");
        }
        
        if (empty($response)) {
            throw new Exception("Empty response received");
        }
        
        $this->log("Successfully fetched page (Length: " . strlen($response) . " bytes)");
        return $response;
    }
    
    private function findNextPageLink($html) {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();
        
        $xpath = new DOMXPath($dom);
        
        // Try multiple methods to find Next link
        $patterns = [
            "//a[contains(translate(., 'NEXT', 'next'), 'next')]",
            "//a[contains(text(), 'Next')]",
            "//a[contains(text(), 'NEXT')]",
            "//a[contains(@href, 'wsj210r.pgm')]"
        ];
        
        foreach ($patterns as $pattern) {
            $links = $xpath->query($pattern);
            
            foreach ($links as $link) {
                $text = trim($link->textContent);
                $href = $link->getAttribute('href');
                
                // Check if this is actually a "Next" link
                if (stripos($text, 'next') !== false && !empty($href)) {
                    // Make absolute URL
                    if (strpos($href, 'http') === 0) {
                        return $href;
                    } else {
                        $href = ltrim($href, '/');
                        return $this->baseUrl . '/' . $href;
                    }
                }
            }
        }
        
        // Look for pagination links with page numbers
        $pageLinks = $xpath->query("//a[contains(@href, 'page=') or contains(@href, 'offset=')]");
        foreach ($pageLinks as $link) {
            $href = $link->getAttribute('href');
            if (!empty($href) && strpos($href, 'javascript:') === false) {
                if (strpos($href, 'http') === 0) {
                    return $href;
                } else {
                    $href = ltrim($href, '/');
                    return $this->baseUrl . '/' . $href;
                }
            }
        }
        
        return null;
    }
    
    private function parseInmates($html) {
        $inmates = [];
        
        // Create DOMDocument
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();
        
        $xpath = new DOMXPath($dom);
        
        // Find the table with id="myTable"
        $table = $xpath->query('//table[@id="myTable"]')->item(0);
        
        if (!$table) {
            $this->log("Could not find table with id='myTable', trying alternative selectors...", 'warning');
            
            // Try to find any table with inmate data
            $tables = $xpath->query('//table');
            foreach ($tables as $t) {
                $headers = $xpath->query('.//th', $t);
                if ($headers->length >= 6) {
                    $table = $t;
                    $this->log("Found alternative table with {$headers->length} headers");
                    break;
                }
            }
        }
        
        if (!$table) {
            $this->log("No suitable table found in HTML", 'error');
            return $inmates;
        }
        
        // Get all rows except the header
        $rows = $xpath->query('.//tr', $table);
        $this->log("Found {$rows->length} total rows in table");
        
        $headerFound = false;
        foreach ($rows as $rowIndex => $row) {
            $cells = $xpath->query('.//td', $row);
            
            // Skip header row
            if (!$headerFound) {
                $ths = $xpath->query('.//th', $row);
                if ($ths->length > 0) {
                    $headerFound = true;
                    continue;
                }
            }
            
            if ($cells->length >= 7) {
                try {
                    // Extract docket number from link
                    $docketCell = $cells->item(0);
                    $docketLink = $xpath->query('.//a', $docketCell)->item(0);
                    $docketNumber = $docketLink ? trim($docketLink->textContent) : trim($docketCell->textContent);
                    
                    // Extract intake date/time
                    $intakeCell = $cells->item(1);
                    $intakeRaw = trim($intakeCell->textContent);
                    $intakeRaw = preg_replace('/\s+/', ' ', $intakeRaw);
                    
                    // Extract released date/time
                    $releasedCell = $cells->item(2);
                    $releasedRaw = trim($releasedCell->textContent);
                    $releasedRaw = preg_replace('/\s+/', ' ', $releasedRaw);
                    $isInJail = stripos($releasedRaw, 'IN JAIL') !== false;
                    
                    // Extract name
                    $nameCell = $cells->item(3);
                    $name = trim($nameCell->textContent);
                    
                    // Extract age
                    $ageCell = $cells->item(4);
                    $age = trim($ageCell->textContent);
                    
                    // Extract charge
                    $chargeCell = $cells->item(5);
                    $charge = trim($chargeCell->textContent);
                    
                    // Extract bond information
                    $bondCell = $cells->item(6);
                    $bondRaw = trim($bondCell->textContent);
                    $bondRaw = preg_replace('/\s+/', ' ', $bondRaw);
                    
                    // Parse bond status and amounts
                    $bondStatus = '';
                    $bondAmount = '';
                    $bondType = '';
                    
                    if (stripos($bondRaw, 'NOT READY') !== false) {
                        $bondStatus = 'NOT READY';
                    } elseif (stripos($bondRaw, 'READY') !== false) {
                        $bondStatus = 'READY';
                    }
                    
                    // Extract bond amounts
                    if (preg_match('/Cash:\s*\$?\s*([0-9,.]+)/i', $bondRaw, $matches)) {
                        $bondType = 'Cash';
                        $bondAmount = '$' . $matches[1];
                    } elseif (preg_match('/Property:\s*\$?\s*([0-9,.]+)/i', $bondRaw, $matches)) {
                        $bondType = 'Property';
                        $bondAmount = '$' . $matches[1];
                    } elseif (stripos($bondRaw, 'No Amount Set') !== false) {
                        $bondAmount = 'No Amount Set';
                    }
                    
                    // Extract fees if present
                    $fees = '';
                    if (preg_match('/Fees:\s*\$?\s*([0-9,.]+)/i', $bondRaw, $matches)) {
                        $fees = '$' . $matches[1];
                    }
                    
                    // Combine bond info
                    $fullBondInfo = $bondStatus;
                    if ($bondAmount) {
                        $fullBondInfo .= ($fullBondInfo ? ' | ' : '') . ($bondType ? $bondType . ': ' : '') . $bondAmount;
                    }
                    if ($fees) {
                        $fullBondInfo .= ' + Fees: ' . $fees;
                    }
                    
                    // Skip if no valid name or if it's the header row
                    if (empty($name) || stripos($name, 'name') !== false || strlen($name) < 3) {
                        continue;
                    }
                    
                    if (empty($docketNumber) || strlen($docketNumber) < 3) {
                        continue;
                    }
                    
                    $inmate = [
                        'docket_number' => $docketNumber,
                        'name' => $name,
                        'age' => $age,
                        'booking_date' => $intakeRaw,
                        'released_date' => $isInJail ? 'IN JAIL' : $releasedRaw,
                        'charge' => $charge,
                        'bond_info' => $fullBondInfo,
                        'bond_raw' => $bondRaw,
                        'in_jail' => $isInJail
                    ];
                    
                    $inmates[] = $inmate;
                    $this->log("Parsed: {$inmate['name']} (Docket: {$docketNumber})");
                    
                } catch (Exception $e) {
                    $this->log("Error parsing row $rowIndex: " . $e->getMessage(), 'warning');
                    continue;
                }
            }
        }
        
        $this->log("Successfully parsed " . count($inmates) . " inmates from this page");
        return $inmates;
    }
    
    private function determineChargeType($charge) {
        $charge = strtoupper($charge);
        
        // Felony keywords
        $felonyKeywords = [
            'FELONY', 'MURDER', 'ROBBERY', 'BURGLARY', 'AGGRAVATED', 'AGG ',
            'RAPE', 'KIDNAPPING', 'ARSON', 'TRAFFICKING', 'POSSESSION WITH INTENT',
            'ARMED ROBBERY', 'HOME INVASION', 'CHILD MOLESTATION', 'WEAPON',
            'FIREARM', 'DISTRIBUTION', 'SEXUAL BATTERY', 'ASSAULT WITH'
        ];
        
        // Misdemeanor keywords
        $misdemeanorKeywords = [
            'MISDEMEANOR', 'MISD', 'BATTERY', 'SIMPLE', 'DISORDERLY',
            'TRESPASS', 'THEFT BY TAKING', 'SHOPLIFTING', 'DUI', 'DRIVING',
            'LICENSE', 'SUSPENDED', 'STOP SIGN', 'YIELD SIGN', 'SOLICITATION',
            'FAILURE TO APPEAR', 'ARREST ORDER', 'VIOLATION OF PROBATION',
            'CRIMINAL DAMAGE-2ND'
        ];
        
        foreach ($felonyKeywords as $keyword) {
            if (strpos($charge, $keyword) !== false) {
                return 'Felony';
            }
        }
        
        foreach ($misdemeanorKeywords as $keyword) {
            if (strpos($charge, $keyword) !== false) {
                return 'Misdemeanor';
            }
        }
        
        return 'Unknown';
    }
    
    private function saveInmates($inmates) {
        $saved = 0;
        $updated = 0;
        
        $this->db->beginTransaction();
        
        try {
            foreach ($inmates as $inmate) {
                $inmateId = $inmate['docket_number'];
                
                // Check if inmate already exists
                $stmt = $this->db->prepare("SELECT id FROM inmates WHERE inmate_id = ?");
                $stmt->execute([$inmateId]);
                $exists = $stmt->fetch();
                
                // Insert or update inmate
                $stmt = $this->db->prepare("
                    INSERT OR REPLACE INTO inmates 
                    (inmate_id, name, booking_date, booking_time, sex, race, age, bond_amount, in_jail, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
                ");
                
                // Parse date and time from booking_date
                $dateTimeParts = explode(' ', $inmate['booking_date']);
                $bookingDate = isset($dateTimeParts[0]) ? $dateTimeParts[0] : '';
                $bookingTime = '';
                if (isset($dateTimeParts[1])) {
                    $bookingTime = $dateTimeParts[1];
                    if (isset($dateTimeParts[2])) {
                        $bookingTime .= ' ' . $dateTimeParts[2];
                    }
                }
                
                $stmt->execute([
                    $inmateId,
                    $inmate['name'],
                    $bookingDate,
                    $bookingTime,
                    '', // sex - not in data
                    '', // race - not in data
                    (int)$inmate['age'],
                    $inmate['bond_info'],
                    $inmate['in_jail'] ? 1 : 0
                ]);
                
                if ($exists) {
                    $updated++;
                } else {
                    $saved++;
                }
                
                // Delete old charges for this inmate
                $this->db->prepare("DELETE FROM charges WHERE inmate_id = ?")->execute([$inmateId]);
                
                // Insert charge(s)
                $charges = explode(';', $inmate['charge']);
                foreach ($charges as $charge) {
                    $charge = trim($charge);
                    if (!empty($charge) && strlen($charge) > 2) {
                        $chargeType = $this->determineChargeType($charge);
                        
                        $stmt = $this->db->prepare("
                            INSERT INTO charges (inmate_id, charge_description, charge_type)
                            VALUES (?, ?, ?)
                        ");
                        $stmt->execute([$inmateId, $charge, $chargeType]);
                    }
                }
            }
            
            $this->db->commit();
            $this->log("Saved: $saved new, Updated: $updated existing inmates");
            return $saved + $updated;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    private function logScrape($status, $count = 0, $message = '', $error = '') {
        $stmt = $this->db->prepare("
            INSERT INTO scrape_logs (status, inmates_found, message, error_details)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$status, $count, $message, $error]);
    }
    
    public function scrapeAllPages($startUrl) {
        try {
            $url = $startUrl;
            $pageNumber = 1;
            $allInmates = [];
            $maxPages = 50; // Safety limit
            $visitedUrls = []; // Prevent infinite loops
            
            while ($url && $pageNumber <= $maxPages) {
                // Check if we've already visited this URL
                if (in_array($url, $visitedUrls)) {
                    $this->log("Already visited URL: $url - stopping pagination");
                    break;
                }
                
                $visitedUrls[] = $url;
                $this->log("=== PAGE $pageNumber ===");
                
                try {
                    // Fetch the page
                    $html = $this->fetchPage($url);
                    
                    // Parse inmates from this page
                    $inmates = $this->parseInmates($html);
                    $inmateCount = count($inmates);
                    
                    if ($inmateCount > 0) {
                        $allInmates = array_merge($allInmates, $inmates);
                        $this->log("Found $inmateCount inmates on page $pageNumber (Total so far: " . count($allInmates) . ")");
                    } else {
                        $this->log("No inmates found on page $pageNumber - stopping pagination", 'warning');
                        break;
                    }
                    
                    // Look for next page link
                    $nextUrl = $this->findNextPageLink($html);
                    
                    if ($nextUrl && $nextUrl !== $url) {
                        $this->log("Found 'Next' page link: $nextUrl");
                        $url = $nextUrl;
                        $pageNumber++;
                        
                        // Be polite - wait between pages
                        sleep(2);
                    } else {
                        $this->log("No more pages found. Pagination complete.");
                        break;
                    }
                    
                } catch (Exception $e) {
                    $this->log("Error on page $pageNumber: " . $e->getMessage(), 'error');
                    break;
                }
            }
            
            if ($pageNumber > $maxPages) {
                $this->log("Reached maximum page limit ($maxPages)", 'warning');
            }
            
            return $allInmates;
            
        } catch (Exception $e) {
            $this->log("Pagination error: " . $e->getMessage(), 'error');
            return [];
        }
    }
    
    public function scrapeAll() {
        try {
            $this->log("========================================");
            $this->log("Starting COMPLETE scrape of all periods");
            $this->log("========================================");
            
            $allInmates = [];
            $scrapeUrls = SCRAPE_URLS;
            
            foreach ($scrapeUrls as $period => $url) {
                $this->log("\n>>> Starting scrape for: $period <<<");
                $this->log("URL: $url\n");
                
                $inmates = $this->scrapeAllPages($url);
                
                if (!empty($inmates)) {
                    $allInmates = array_merge($allInmates, $inmates);
                    $this->log("Completed $period: " . count($inmates) . " inmates");
                } else {
                    $this->log("No inmates found for $period", 'warning');
                }
                
                // Wait between different time periods
                sleep(3);
            }
            
            // Remove duplicates based on docket number
            $uniqueInmates = [];
            $docketNumbers = [];
            
            foreach ($allInmates as $inmate) {
                $docket = $inmate['docket_number'];
                if (!in_array($docket, $docketNumbers)) {
                    $uniqueInmates[] = $inmate;
                    $docketNumbers[] = $docket;
                }
            }
            
            $this->log("\n========================================");
            $this->log("Total inmates collected: " . count($allInmates));
            $this->log("Unique inmates: " . count($uniqueInmates));
            $this->log("========================================\n");
            
            // Save all inmates to database
            if (!empty($uniqueInmates)) {
                $saved = $this->saveInmates($uniqueInmates);
                $this->log("Complete scrape finished: $saved inmates processed");
                $this->logScrape('success', $saved, "Successfully scraped $saved inmates from all periods");
                return $saved;
            } else {
                $this->log("No inmates to save", 'warning');
                $this->logScrape('success', 0, "No inmates found");
                return 0;
            }
            
        } catch (Exception $e) {
            $this->log("Scrape failed: " . $e->getMessage(), 'error');
            $this->logScrape('error', 0, 'Scrape failed', $e->getMessage());
            return false;
        }
    }
    
    public function scrape() {
        return $this->scrapeAll();
    }
    
    public function run48Hours() {
        $this->log("Starting 48-hour scraping session...");
        $startTime = time();
        $endTime = $startTime + SCRAPE_DURATION;
        
        while (time() < $endTime) {
            $this->scrapeAll();
            
            $remainingTime = $endTime - time();
            if ($remainingTime > 0) {
                $sleepTime = min(SCRAPE_INTERVAL, $remainingTime);
                $this->log("Sleeping for " . ($sleepTime / 60) . " minutes until next scrape...\n");
                sleep($sleepTime);
            }
        }
        
        $this->log("48-hour scraping session completed");
    }
}

// Run the scraper
if (php_sapi_name() === 'cli') {
    $scraper = new JailScraper();
    
    if (isset($argv[1]) && $argv[1] === '--once') {
        $scraper->scrape();
    } else {
        $scraper->run48Hours();
    }
} else {
    echo "This script must be run from command line.\n";
    echo "Usage: php scraper.php [--once]\n";
}
?>