#!/usr/bin/env python3
"""Chat API server - 消息队列 + Telegram 通知"""
import json, os, time, threading, http.server
from urllib.parse import urlparse, parse_qs

# Telegram
BOT_TOKEN = os.environ.get('BOT_TOKEN', '8663024939:AAGn9t06-DXn6H6mF5IjsjxrqAr8G52UUn0')
TG_API = 'https://api.telegram.org/bot' + BOT_TOKEN
CHAT_ID = '861063885'

import urllib.request

def tg_send(text):
    data = json.dumps({'chat_id': CHAT_ID, 'text': text}).encode()
    req = urllib.request.Request(TG_API + '/sendMessage', data=data,
                                 headers={'Content-Type': 'application/json'})
    try:
        urllib.request.urlopen(req, timeout=5)
    except:
        pass

# 消息队列
conversations = {}
next_id = 1

def write_msg(conv_id, role, text):
    global next_id
    if conv_id not in conversations:
        conversations[conv_id] = []
    msg = {'id': f'{role}_{conv_id}_{next_id}', 'role': role, 'text': text, 'ts': int(time.time()*1000)}
    next_id += 1
    conversations[conv_id].append(msg)
    return msg

class Handler(http.server.BaseHTTPRequestHandler):
    def do_GET(self):
        parsed = urlparse(self.path)
        if parsed.path.startswith('/poll/'):
            conv_id = parsed.path[6:]
            qs = parse_qs(parsed.query)
            after = qs.get('after', [''])[0]
            conv = conversations.get(conv_id, [])
            msgs = [m for m in conv if m['id'] != after]
            msgs.sort(key=lambda x: x['ts'])
            self._json({'ok': True, 'messages': msgs})
        elif parsed.path == '/':
            with open('/root/weapp-mall/chat.html') as f:
                html = f.read()
            self.send_response(200)
            self.send_header('Content-Type', 'text/html; charset=utf-8')
            self.end_headers()
            self.wfile.write(html.encode())
        else:
            self._json({'ok': False, 'error': 'not found'})

    def do_POST(self):
        length = int(self.headers.get('Content-Length', 0))
        body = self.rfile.read(length) if length else b'{}'
        data = json.loads(body)
        
        if self.path == '/send':
            text = data.get('text', '')
            conv_id = data.get('conv_id', 'default')
            if not text:
                self._json({'ok': False, 'error': '消息不能为空'})
                return
            write_msg(conv_id, 'user', text)
            # 通知 Telegram
            threading.Thread(target=tg_send, args=(f'💬 网页端消息\n\n{text}',), daemon=True).start()
            self._json({'ok': True})
            
        elif self.path == '/reply':
            conv_id = data.get('conv_id', '')
            text = data.get('text', '')
            if not conv_id or not text:
                self._json({'ok': False, 'error': '缺少参数'})
                return
            write_msg(conv_id, 'assistant', text)
            self._json({'ok': True})
            
        else:
            self._json({'ok': False, 'error': 'not found'})

    def _json(self, data):
        self.send_response(200)
        self.send_header('Content-Type', 'application/json')
        self.send_header('Access-Control-Allow-Origin', '*')
        self.end_headers()
        self.wfile.write(json.dumps(data, ensure_ascii=False).encode())

    def log_message(self, format, *args):
        pass

if __name__ == '__main__':
    port = 3205
    server = http.server.HTTPServer(('127.0.0.1', port), Handler)
    print(f'Chat API running on port {port}')
    server.serve_forever()
