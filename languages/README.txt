主题翻译说明
================

本目录为 Folio 主题语言文件。WordPress 只会加载编译后的 .mo 文件，因此需要将 .po 编译为 .mo 后，前端/无痕模式下中文等翻译才会生效（例如「Login To Load More」显示为「登录后查看更多」）。

生成 .mo 方法任选其一：

1) 使用 Python 脚本（推荐）
   - 依赖: pip install polib
   - 在项目根目录执行: python languages/compile_mo.py
   - 会将该目录下所有 .po 编译为同名的 .mo

2) 使用 Poedit
   - 用 Poedit 打开 folio-zh_CN.po，保存即可自动生成 folio-zh_CN.mo。
   - 下载：https://poedit.net/

3) 使用 gettext 命令行（需先安装 gettext）
   - Windows (MSYS2/Git Bash): msgfmt -o folio-zh_CN.mo folio-zh_CN.po
   - Linux/macOS: msgfmt -o folio-zh_CN.mo folio-zh_CN.po

4) 使用 WP-CLI（若已安装）
   - wp i18n make-mo languages languages

编译完成后，.mo 会生成在本目录（与 .po 同级）。刷新前台并访问 ?lang=zh_CN 或使用无痕模式测试中文按钮等文案。
