#!/root/sandbox/scrapling-test/.venv/bin/python
"""
Scrapling runner — SGP /root/server/scraper/scraper_runner.py
江小鱼 2026-09-13: 给 GDQ 加的 Scrapling 抓取子进程入口。

输入(stdin JSON):
  {"url": "...", "selector": ".item .title", "selector_type": "css|xpath|text",
   "fetch_mode": "fetcher|dynamic|stealthy", "timeout_ms": 30000,
   "headers": {...}      # 可选,附加 HTTP 头
   "auto_save": false,   # 自适应签名保存
   "adaptive":  false }  # 自适应签名匹配

输出(stdout JSON):
  成功: {"ok": true, "items": [...], "elapsed_ms": 850, "url": "final", "status": 200,
         "count": 10, "adaptive_signature_saved": "..."}
  失败: {"ok": false, "error": "msg", "trace": "..."}

JSON 协议, 因为 Scrapling 进程是 Node child_process.spawn 调起来的, 不走 HTTP。
"""
import json
import sys
import time
import traceback

VENV_PYTHON = "/root/sandbox/scrapling-test/.venv/bin/python"
# 这个文件本身用 shebang 指 venv python, 双保险: 也强制 re-exec
import os
if not os.environ.get("SCRAPER_REEXECED"):
    os.environ["SCRAPER_REEXECED"] = "1"
    os.execv(VENV_PYTHON, [VENV_PYTHON] + sys.argv)


def main():
    try:
        payload = json.loads(sys.stdin.read() or "{}")
    except json.JSONDecodeError as e:
        return _emit({"ok": False, "error": f"bad JSON input: {e}"})

    url = payload.get("url")
    selector = payload.get("selector")
    if not url or not selector:
        return _emit({"ok": False, "error": "url 和 selector 都必填"})

    sel_type = payload.get("selector_type", "css")
    fetch_mode = payload.get("fetch_mode", "fetcher")
    timeout_ms = int(payload.get("timeout_ms", 30000))
    headers = payload.get("headers") or {}
    auto_save = bool(payload.get("auto_save", False))
    adaptive = bool(payload.get("adaptive", False))

    try:
        # 懒加载 fetcher — 避免 dynamic/stealthy 路径上没装浏览器
        if fetch_mode == "dynamic":
            from scrapling.fetchers import DynamicFetcher
            cls = DynamicFetcher
            kwargs = {"headless": True, "network_idle": True}
        elif fetch_mode == "stealthy":
            from scrapling.fetchers import StealthyFetcher
            StealthyFetcher.adaptive = bool(adaptive)
            cls = StealthyFetcher
            kwargs = {"headless": True, "google_search": False}
        else:
            from scrapling.fetchers import Fetcher
            cls = Fetcher
            kwargs = {"stealthy_headers": True}

        if headers:
            kwargs["headers"] = headers

        t0 = time.time()
        page = cls.get(url, **kwargs) if fetch_mode == "fetcher" else cls.fetch(url, **kwargs)
        elapsed_ms = int((time.time() - t0) * 1000)

        # 选元素
        if sel_type == "xpath":
            elements = page.xpath(selector)
        elif sel_type == "text":
            elements = page.find_by_text(selector)
        else:
            if auto_save and adaptive:
                elements = page.css(selector, auto_save=True, adaptive=True)
            else:
                elements = page.css(selector)

        # 序列化元素 — 取 ::text + attr
        items = []
        for el in elements:
            text = el.css("::text").get() if hasattr(el, "css") else str(el)
            items.append(text)

        result = {
            "ok": True,
            "items": items,
            "count": len(items),
            "elapsed_ms": elapsed_ms,
            "url": page.url,
            "status": getattr(page, "status", 200),
        }
        return _emit(result)

    except Exception as e:
        return _emit({
            "ok": False,
            "error": str(e),
            "trace": traceback.format_exc()[:2000],
        })


def _emit(obj):
    sys.stdout.write(json.dumps(obj, ensure_ascii=False))
    sys.stdout.write("\n")
    sys.stdout.flush()


if __name__ == "__main__":
    main()