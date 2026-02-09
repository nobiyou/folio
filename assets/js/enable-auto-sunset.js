/**
 * Enable Auto Sunset Theme Switch
 * 
 * 启用自动日落切换的便捷脚本
 * 在浏览器控制台执行此代码即可启用自动日落切换功能
 * 
 * @package Folio
 */

(function() {
    'use strict';
    
    // 检查必要的工具是否已加载
    if (typeof window.SunTimesCalculator === 'undefined') {
        console.error('SunTimesCalculator 未加载，请刷新页面后重试');
        return;
    }
    
    // 启用自动日落切换
    localStorage.setItem('folio-auto-sunset', 'true');
    
    // 清除手动设置的主题（如果存在），让自动切换生效
    const manualTheme = localStorage.getItem('mpb-theme');
    if (manualTheme) {
        console.log('检测到手动设置的主题:', manualTheme);
        console.log('清除手动设置，启用自动切换...');
        localStorage.removeItem('mpb-theme');
    }
    
    console.log('✅ 已启用自动日落切换功能');
    console.log('主题将根据您的位置和日出日落时间自动切换');
    console.log('提示：刷新页面后生效');
    
    // 询问是否立即刷新
    if (confirm('已启用自动日落切换功能！\n\n是否立即刷新页面以应用更改？')) {
        location.reload();
    }
})();

