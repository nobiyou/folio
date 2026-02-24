/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './*.php',
    './inc/**/*.php',
    './template-parts/**/*.php',
    './user-center/**/*.php',
    './assets/js/**/*.js'
  ],
  darkMode: 'class', // 支持暗黑模式
  theme: {
    extend: {
      colors: {
        // 可以在这里添加自定义颜色
      },
      fontFamily: {
        // 可以在这里添加自定义字体
      }
    }
  },
  plugins: []
}
