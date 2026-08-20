#!/usr/bin/env python3
"""把「毕业设计题目分类整理」Markdown 转成前端数据文件。

用法：
    python3 tools/build-thesis-topics.py 毕业设计题目分类整理_宣传选题版.md

输出：js/thesis-topics.data.js（挂载到 window.THESIS_TOPICS）
Markdown 结构要求：`## <序号>. <方向名>` 标题 + 四列表格（序号 / 题目 / 技术栈 / 功能亮点）。
"""

import json
import os
import re
import sys
from datetime import date

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUTPUT = os.path.join(ROOT, 'js', 'thesis-topics.data.js')


def parse(markdown):
    categories = []
    current = None
    for line in markdown.split('\n'):
        heading = re.match(r'^## (\d+)\.\s*(.+?)\s*$', line)
        if heading:
            current = {'id': int(heading.group(1)), 'name': heading.group(2).strip(), 'topics': []}
            categories.append(current)
            continue
        if current is None or not line.startswith('|'):
            continue
        cells = [c.strip() for c in line.strip().strip('|').split('|')]
        if len(cells) < 4 or not cells[0].isdigit():
            continue
        current['topics'].append({
            'no': int(cells[0]),
            'title': cells[1],
            'stack': cells[2],
            'highlights': [h for h in (p.strip() for p in re.split(r' / ', cells[3])) if h],
        })
    return categories


def main():
    if len(sys.argv) != 2:
        print(__doc__)
        return 1
    with open(sys.argv[1], encoding='utf-8') as f:
        categories = parse(f.read())
    if not categories:
        print('未解析到任何方向，请检查 Markdown 结构')
        return 1

    data = {
        'updated': date.today().isoformat(),
        'total': sum(len(c['topics']) for c in categories),
        'categories': [
            {'id': c['id'], 'name': c['name'], 'count': len(c['topics']), 'topics': c['topics']}
            for c in categories
        ],
    }
    payload = json.dumps(data, ensure_ascii=False, separators=(',', ':'))
    with open(OUTPUT, 'w', encoding='utf-8') as f:
        f.write('/* 毕设题目参考数据（由 tools/build-thesis-topics.py 生成，勿手改） */\n')
        f.write('window.THESIS_TOPICS = %s;\n' % payload)
    print('已写入 %s：%d 个方向 / %d 个题目' % (OUTPUT, len(categories), data['total']))
    return 0


if __name__ == '__main__':
    sys.exit(main())
