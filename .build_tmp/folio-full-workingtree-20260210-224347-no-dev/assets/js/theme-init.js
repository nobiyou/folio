/**
 * Theme Initialization Script
 * 
 * 主题初始化脚本 - 必须在 head 中尽早执行以防止闪烁
 * 
 * @package Folio
 */

(function() {
    'use strict';
    
    /**
     * 根据时间判断是否应该使用暗色主题
     * @param {string} sunriseTime - 日出时间 (HH:MM)
     * @param {string} sunsetTime - 日落时间 (HH:MM)
     * @returns {boolean} true 表示应该使用暗色主题
     */
    function shouldUseDarkThemeByTime(sunriseTime, sunsetTime) {
        var now = new Date();
        var currentHour = now.getHours();
        var currentMinute = now.getMinutes();
                var currentTime = currentHour * 60 + currentMinute; // Convert to minutes
        
        // 解析日出时间
        var sunriseParts = sunriseTime.split(':');
        var sunriseHour = parseInt(sunriseParts[0], 10);
        var sunriseMinute = parseInt(sunriseParts[1], 10);
        var sunriseTimeMinutes = sunriseHour * 60 + sunriseMinute;
        
        // 解析日落时间
        var sunsetParts = sunsetTime.split(':');
        var sunsetHour = parseInt(sunsetParts[0], 10);
        var sunsetMinute = parseInt(sunsetParts[1], 10);
        var sunsetTimeMinutes = sunsetHour * 60 + sunsetMinute;
        
        // 如果当前时间在日落之后或日出之前，使用暗色主题
        if (sunsetTimeMinutes < sunriseTimeMinutes) {
            // 日落时间早于日出时间（跨天情况，如 22:00 - 06:00）
            return currentTime >= sunsetTimeMinutes || currentTime < sunriseTimeMinutes;
        } else {
            // 正常情况（日出时间早于日落时间）
            return currentTime >= sunsetTimeMinutes || currentTime < sunriseTimeMinutes;
        }
    }
    
    /**
     * 初始化主题模式（防止闪烁）
     * 必须在页面渲染前执行
     */
    function initThemeMode() {
        // 从全局变量获取默认主题（由 PHP 通过 wp_localize_script 设置）
        var defaultTheme = 'auto';
        var sunriseTime = '06:00';
        var sunsetTime = '18:00';
        var backendAutoSunset = false;
        
        // 检查 folioThemeInit 是否存在且有效
        if (typeof folioThemeInit !== 'undefined' && folioThemeInit) {
            defaultTheme = folioThemeInit.defaultTheme || 'auto';
            sunriseTime = folioThemeInit.sunriseTime || '06:00';
            sunsetTime = folioThemeInit.sunsetTime || '18:00';
            // 兼容字符串和布尔值：true, "1", 1 都视为启用
            backendAutoSunset = folioThemeInit.autoSunsetEnabled === true 
                || folioThemeInit.autoSunsetEnabled === '1' 
                || folioThemeInit.autoSunsetEnabled === 1
                || folioThemeInit.autoSunsetEnabled === 'true';
        }
        
        var storedTheme = localStorage.getItem('mpb-theme');
        var autoSunsetEnabled = false;
        
        if (defaultTheme === 'auto') {
            // 后台设置为 auto，自动启用日落切换
            if (backendAutoSunset) {
                // 后台强制启用自动日落切换，清除手动设置的主题
                localStorage.setItem('folio-auto-sunset', 'true');
                if (storedTheme) {
                    // 清除手动设置，让自动切换生效
                    localStorage.removeItem('mpb-theme');
                    storedTheme = null;
                }
                autoSunsetEnabled = true;
            } else {
                // 检查 localStorage 中是否已启用
                autoSunsetEnabled = localStorage.getItem('folio-auto-sunset') === 'true' && !storedTheme;
            }
        } else {
            // 后台设置为非 auto，禁用自动日落切换
            localStorage.setItem('folio-auto-sunset', 'false');
            autoSunsetEnabled = false;
        }
        
        var theme;
        
        // 主题优先级逻辑：
        // 1. 如果启用了自动日落切换，且用户未手动设置，则根据日出日落时间自动切换
        // 2. 如果后台设置了明确的默认主题（非 auto），优先使用后台设置，忽略 localStorage
        //    这样可以确保后台设置的默认主题生效，即使用户之前手动切换过
        // 3. 如果后台设置为 auto，但未启用日落切换，则跟随系统偏好
        
        if (autoSunsetEnabled && !storedTheme) {
            // 启用了自动日落切换，且用户未手动设置主题
            // 使用后台设置的日出日落时间（已在函数开头获取）
            var shouldDark = shouldUseDarkThemeByTime(sunriseTime, sunsetTime);
            theme = shouldDark ? 'dark' : 'light';
        } else if (defaultTheme === 'auto') {
            // 后台设置为自动，但未启用日落切换或用户已手动设置
            if (storedTheme) {
                // 用户之前手动切换过，使用用户的选择
                theme = storedTheme;
            } else {
                // 用户没有手动选择过，跟随系统偏好
                theme = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            }
        } else {
            // 后台设置了明确的默认主题（light 或 dark）
            // 优先使用后台设置，忽略 localStorage 中的旧值
            // 这样可以确保后台设置的默认主题生效
            theme = defaultTheme;
            
            // 如果 localStorage 中的值与后台设置不一致，清除旧值
            // 这样用户下次手动切换时，会基于后台默认值进行切换
            if (storedTheme && storedTheme !== defaultTheme) {
                localStorage.removeItem('mpb-theme');
            }
            
            // 如果后台设置为非 auto，禁用自动日落切换
            if (defaultTheme !== 'auto') {
                localStorage.setItem('folio-auto-sunset', 'false');
            }
        }
        
        // 应用主题
        if (theme === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
            if (document.body) {
                document.body.setAttribute('data-theme', 'dark');
            }
        } else {
            document.documentElement.setAttribute('data-theme', 'light');
            if (document.body) {
                document.body.setAttribute('data-theme', 'light');
            }
        }
        
        // Theme initialization complete
    }
    
    /**
     * 配置 Tailwind CSS
     * 必须在 Tailwind CDN 加载后执行
     */
    function initTailwindConfig() {
        if (typeof tailwind !== 'undefined') {
            tailwind.config = {
                darkMode: 'class',
                theme: {
                    extend: {
                        fontFamily: {
                            'heading': ['Oswald', 'sans-serif'],
                            'body': ['Roboto Condensed', 'sans-serif'],
                        },
                    }
                }
            };
        } else {
            // 如果 Tailwind 还没加载，等待它加载
            var checkTailwind = setInterval(function() {
                if (typeof tailwind !== 'undefined') {
                    clearInterval(checkTailwind);
                    initTailwindConfig();
                }
            }, 50);
            
            // 10秒后停止检查
            setTimeout(function() {
                clearInterval(checkTailwind);
            }, 10000);
        }
    }
    
    // 立即执行主题初始化（防止闪烁）
    initThemeMode();
    
    // DOM 加载完成后配置 Tailwind
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTailwindConfig);
    } else {
        initTailwindConfig();
    }
    
    // 如果 Tailwind 在 DOMContentLoaded 之后加载，也尝试配置
    window.addEventListener('load', function() {
        if (typeof tailwind !== 'undefined' && !tailwind.config) {
            initTailwindConfig();
        }
    });
})();
