# -*- coding: utf-8 -*-
# Python 2.7 - CMS fingerprinting batch runner
# Use only on systems you own or are explicitly authorized to assess.

import os
import sys
import requests
from Queue import Queue
from threading import Thread, Lock

OUTPUT_DIR = "cms"
HEADERS = {"User-Agent": "Linux Mozilla 7.0"}
print_lock = Lock()
write_lock = Lock()


class Worker(Thread):
    def __init__(self, tasks):
        Thread.__init__(self)
        self.tasks = tasks
        self.daemon = True
        self.start()

    def run(self):
        while True:
            func, args = self.tasks.get()
            try:
                func(*args)
            except Exception as error:
                safe_print("[!] %s" % error)
            finally:
                self.tasks.task_done()


class ThreadPool(object):
    def __init__(self, threads):
        self.tasks = Queue()
        for _ in range(threads):
            Worker(self.tasks)

    def add_task(self, func, *args):
        self.tasks.put((func, args))

    def wait(self):
        self.tasks.join()


def safe_print(text):
    with print_lock:
        print text


def save_result(name, url):
    path = os.path.join(OUTPUT_DIR, name + ".txt")
    with write_lock:
        known = set()
        if os.path.isfile(path):
            with open(path, "r") as handle:
                known = set(line.strip() for line in handle if line.strip())
        if url in known:
            return False
        with open(path, "a") as handle:
            handle.write(url + "\n")
    return True


def detect_cms(url):
    try:
        if "://" not in url:
            url = "http://" + url
        session = requests.Session()
        response = session.get(url, headers=HEADERS, timeout=5)
        html = response.text.lower()
        headers = str(response.headers)

        if "=eyj" in headers.lower() or "xsrf-token" in headers.lower():
            name = "Laravel"
        elif "/wp-content/" in html:
            name = "Wordpress"
        elif "component" in html and "com_" in html:
            name = "Joomla"
        elif "/sites/default/" in html:
            name = "Drupal"
        elif "skin/frontend/" in html:
            name = "Magento"
        elif "prestashop" in html:
            name = "PrestaShop"
        else:
            name = "Other"

        is_new = save_result(name, url)
        color = "\033[32;1m" if is_new else "\033[31;1m"
        safe_print("%s -> %s%s\033[0m" % (url, color, name))
    except Exception:
        save_result("EXCEPTION_SITES", url)
        safe_print("%s -> \033[31;1mEXCEPTION\033[0m" % url)


def get_input():
    if len(sys.argv) >= 4:
        return sys.argv[1], int(sys.argv[2]), int(sys.argv[3])

    print "[!] Authorized targets only."
    listfile = raw_input("websitelist: ").strip()
    threads = int(raw_input("threads per batch: ").strip())
    batches = int(raw_input("how many batches: ").strip())
    return listfile, threads, batches


def main():
    try:
        listfile, threads, batches = get_input()
        if threads < 1 or batches < 1:
            raise ValueError("threads and batches must be at least 1")
        if not os.path.isfile(listfile):
            raise IOError("list file not found")

        with open(listfile, "r") as handle:
            targets = [line.strip() for line in handle if line.strip() and not line.lstrip().startswith("#")]
        if not targets:
            raise ValueError("target list is empty")

        if not os.path.isdir(OUTPUT_DIR):
            os.makedirs(OUTPUT_DIR)

        batches = min(batches, len(targets))
        groups = [[] for _ in range(batches)]
        for index, target in enumerate(targets):
            groups[index % batches].append(target)

        safe_print("[*] Targets: %d | Batches: %d | Threads/batch: %d | Max concurrent requests: %d" % (len(targets), batches, threads, batches * threads))
        pools = []
        for number, group in enumerate(groups, 1):
            pool = ThreadPool(threads)
            pools.append(pool)
            safe_print("[+] Starting batch %d: %d targets" % (number, len(group)))
            for target in group:
                pool.add_task(detect_cms, target)

        for number, pool in enumerate(pools, 1):
            pool.wait()
            safe_print("[+] Batch %d complete" % number)

        safe_print("[*] Done. Results saved in ./%s/" % OUTPUT_DIR)
    except KeyboardInterrupt:
        safe_print("\n[!] Interrupted")
    except Exception as error:
        safe_print("[!] %s" % error)
        safe_print("Usage: python2 cms_batch.py list.txt THREADS_PER_BATCH BATCHES")


if __name__ == "__main__":
    main()
