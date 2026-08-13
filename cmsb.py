# -*- coding: utf-8 -*-
# Python 2.7 - bounded-memory CMS batch scanner
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
handles = {}
quiet = True


def safe_print(message):
    with print_lock:
        print message


def save_result(name, url):
    with write_lock:
        if name not in handles:
            handles[name] = open(os.path.join(OUTPUT_DIR, name + ".txt"), "a")
        handles[name].write(url + "\n")
        handles[name].flush()


def detect_cms(url):
    try:
        if "://" not in url:
            url = "http://" + url
        response = requests.get(url, headers=HEADERS, timeout=5)
        html = response.text.lower()
        headers = str(response.headers).lower()
        if "=eyj" in headers or "xsrf-token" in headers:
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
        save_result(name, url)
        if not quiet:
            safe_print("%s -> %s" % (url, name))
    except Exception:
        save_result("EXCEPTION_SITES", url)
        if not quiet:
            safe_print("%s -> EXCEPTION" % url)


class Worker(Thread):
    def __init__(self, queue):
        Thread.__init__(self)
        self.queue = queue
        self.daemon = True
        self.start()

    def run(self):
        while True:
            url = self.queue.get()
            try:
                if url is None:
                    return
                detect_cms(url)
            finally:
                self.queue.task_done()


class Batch(object):
    def __init__(self, threads):
        self.queue = Queue(5000)
        self.workers = [Worker(self.queue) for _ in range(threads)]

    def put(self, url):
        self.queue.put(url)

    def finish(self):
        for _ in self.workers:
            self.queue.put(None)
        self.queue.join()


def main():
    global quiet
    if len(sys.argv) >= 4:
        listfile = sys.argv[1]
        threads = int(sys.argv[2])
        batch_count = int(sys.argv[3])
        quiet = "--verbose" not in sys.argv[4:]
    else:
        print "[!] Authorized targets only."
        listfile = raw_input("websitelist: ").strip()
        threads = int(raw_input("threads per batch: ").strip())
        batch_count = int(raw_input("how many batches: ").strip())
        quiet = raw_input("show every result? [y/N]: ").strip().lower() not in ("y", "yes")

    if threads < 1 or batch_count < 1:
        raise ValueError("threads and batches must be at least 1")
    if not os.path.isfile(listfile):
        raise IOError("list file not found: %s" % listfile)
    if not os.path.isdir(OUTPUT_DIR):
        os.makedirs(OUTPUT_DIR)

    batches = [Batch(threads) for _ in range(batch_count)]
    safe_print("[*] Streaming input: %d batches x %d threads = %d max requests" % (batch_count, threads, batch_count * threads))
    safe_print("[*] No cms.log and no full target list is loaded into RAM.")

    submitted = 0
    with open(listfile, "r") as source:
        for line in source:
            target = line.strip()
            if not target or target.startswith("#"):
                continue
            batches[submitted % batch_count].put(target)
            submitted += 1
            if submitted % 100000 == 0:
                safe_print("[*] Queued %d targets" % submitted)

    safe_print("[*] Input complete: %d targets queued. Waiting for workers..." % submitted)
    for batch in batches:
        batch.finish()
    with write_lock:
        for handle in handles.values():
            handle.close()
    safe_print("[*] Done. Results are in ./%s/" % OUTPUT_DIR)


if __name__ == "__main__":
    try:
        main()
    except KeyboardInterrupt:
        safe_print("\n[!] Interrupted")
    except Exception as error:
        safe_print("[!] %s" % error)
        safe_print("Usage: python2 cms_stream_batch_fixed.py list.txt THREADS_PER_BATCH BATCHES [--verbose]")
