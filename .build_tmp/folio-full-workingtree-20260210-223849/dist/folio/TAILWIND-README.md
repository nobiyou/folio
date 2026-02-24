# Tailwind CSS 本地打包使用指南

## 安装步骤

### 1. 安装 Node.js
确保你的系统已安装 Node.js（建议 v16 或更高版本）

### 2. 安装依赖
在主题目录下运行：
```bash
cd folio
npm install
```

### 3. 构建 Tailwind CSS

#### 开发模式（实时监听文件变化）
```bash
npm run dev
```

#### 生产模式（压缩优化）
```bash
npm run build
```

## 文件说明

- `tailwind.config.js` - Tailwind 配置文件，定义扫描路径和自定义样式
- `assets/css/tailwind-input.css` - Tailwind 源文件
- `assets/css/tailwind.css` - 编译后的 CSS 文件（自动生成，不要手动编辑）
- `package.json` - Node.js 依赖和脚本配置

## 部署到生产环境

1. 在本地运行 `npm run build` 生成压缩后的 CSS
2. 将以下文件上传到服务器：
   - `assets/css/tailwind.css`（编译后的文件）
   - 其他主题文件

**注意：** 生产环境不需要上传 `node_modules/`、`tailwind.config.js`、`package.json` 等开发文件

## 自定义样式

### 修改 Tailwind 配置
编辑 `tailwind.config.js` 添加自定义颜色、字体等：

```javascript
theme: {
  extend: {
    colors: {
      'brand': '#your-color',
    }
  }
}
```

### 添加自定义 CSS
在 `assets/css/tailwind-input.css` 中添加：

```css
@layer components {
  .btn-primary {
    @apply bg-blue-500 text-white px-4 py-2 rounded;
  }
}
```

修改后运行 `npm run build` 重新编译。

## 优势

✅ 不依赖外部 CDN，加载更快更稳定
✅ 支持自定义配置和样式
✅ 生产环境文件经过压缩优化
✅ 支持暗黑模式（已配置 `darkMode: 'class'`）
✅ 只包含实际使用的 CSS 类，文件更小

## 故障排除

### 如果 CSS 没有更新
1. 确保运行了 `npm run build`
2. 清除浏览器缓存
3. 检查 `assets/css/tailwind.css` 文件是否存在

### 如果样式丢失
1. 检查 `tailwind.config.js` 中的 `content` 路径是否正确
2. 确保 PHP 文件中使用的 Tailwind 类名正确
3. 重新运行 `npm run build`
