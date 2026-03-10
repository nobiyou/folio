#!/usr/bin/env python3
"""
将 languages 目录下的 .po 编译为 .mo，供 WordPress 主题加载翻译使用。
依赖: pip install polib
用法: 在项目根目录执行  python languages/compile_mo.py
      或在 languages 目录执行  python compile_mo.py
"""

import os
import sys

try:
    import polib
except ImportError:
    print("请先安装 polib: pip install polib")
    sys.exit(1)

# 脚本所在目录即 languages 目录
LANG_DIR = os.path.dirname(os.path.abspath(__file__))

def main():
    os.chdir(LANG_DIR)
    compiled = 0
    for name in os.listdir("."):
        if not name.endswith(".po"):
            continue
        base = name[:-3]  # 去掉 .po
        mo_name = base + ".mo"
        try:
            po = polib.pofile(name)
            po.save_as_mofile(mo_name)
            print(f"已生成: {mo_name}")
            compiled += 1
        except Exception as e:
            print(f"跳过 {name}: {e}", file=sys.stderr)
    if compiled == 0:
        print("未找到 .po 文件或全部跳过。")
    else:
        print(f"共编译 {compiled} 个 .mo 文件。")

if __name__ == "__main__":
    main()
