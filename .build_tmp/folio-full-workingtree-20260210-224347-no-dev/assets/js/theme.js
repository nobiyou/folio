/**
 * Folio Theme JavaScript
 *
 * @package Folio
 */

(function() {
    'use strict';

    function t(key, fallback) {
        if (window.folio_ajax && window.folio_ajax.strings && window.folio_ajax.strings[key]) {
            return window.folio_ajax.strings[key];
        }
        return fallback;
    }

    /**
     * DOM Ready
     */
    document.addEventListener('DOMContentLoaded', function() {
        initThemeToggle();
        initMobileNav();
        initLazyLoading();
        initSmoothScroll();
        initKeyboardNavigation();
        initBackToTop();
        initLikeButton();
        // 文章卡片已自带会员徽章，关闭额外的标题内徽章渲染，避免重复和多余的 AJAX 请求
        // initArticleBadges();
    });
    
    /**
     * 启用/禁用自动日落切换的全局方法
     * 使用方法：
     *   window.enableAutoSunset() - 启用自动日落切换
     *   window.disableAutoSunset() - 禁用自动日落切换
     */
    window.enableAutoSunset = function() {
        localStorage.setItem('folio-auto-sunset', 'true');
        // 清除手动设置的主题，让自动切换生效
        localStorage.removeItem('mpb-theme');
        // 重新初始化自动切换
        if (typeof initAutoSunsetTheme === 'function') {
            initAutoSunsetTheme();
        } else {
            // 如果函数不可用，刷新页面
            location.reload();
        }
        // Auto sunset enabled
    };
    
    window.disableAutoSunset = function() {
        localStorage.setItem('folio-auto-sunset', 'false');
        // 清除定时器
        if (window.folioSunsetTimer) {
            clearTimeout(window.folioSunsetTimer);
            window.folioSunsetTimer = null;
        }
        // Auto sunset disabled
    };

    /**
     * Theme Toggle (Dark/Light Mode)
     */
    function initThemeToggle() {
        const toggle = document.getElementById('theme-toggle');
        if (!toggle) return;

        // 防止重复绑定事件监听器
        if (toggle.dataset.themeToggleInitialized === 'true') {
            return;
        }
        toggle.dataset.themeToggleInitialized = 'true';

        // 获取当前主题
        function getCurrentTheme() {
            // 优先从 DOM 读取当前实际主题（最准确）
            const domTheme = document.documentElement.getAttribute('data-theme');
            if (domTheme && (domTheme === 'dark' || domTheme === 'light')) {
                return domTheme;
            }
            
            // 其次从 localStorage 读取
            const stored = localStorage.getItem('mpb-theme');
            if (stored && (stored === 'dark' || stored === 'light')) {
                return stored;
            }
            
            // 最后使用系统偏好
            return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }

        // 设置主题
        function setTheme(theme, isAutoSwitch) {
            // 立即应用主题，避免闪烁
            document.documentElement.setAttribute('data-theme', theme);
            if (document.body) {
            document.body.setAttribute('data-theme', theme);
            }
            // 只有在非自动切换时才保存到 localStorage（标记为用户手动设置）
            if (!isAutoSwitch) {
                localStorage.setItem('mpb-theme', theme);
            }
        }

        // 切换主题处理函数（使用防抖防止重复触发）
        let isToggling = false;
        function handleThemeToggle(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // 防止重复触发
            if (isToggling) {
                return;
            }
            isToggling = true;
            
            // 获取当前实际主题（从 DOM 读取，确保准确）
            const currentDomTheme = document.documentElement.getAttribute('data-theme');
            let current = currentDomTheme;
            
            // 如果 DOM 没有值，使用 getCurrentTheme()
            if (!current || (current !== 'dark' && current !== 'light')) {
                current = getCurrentTheme();
        }

        // 切换主题
            const newTheme = current === 'dark' ? 'light' : 'dark';
            setTheme(newTheme, false);
            
            // 调试信息（仅在开发模式下）
            // Theme switch logged (debug mode only)
            
            // 重置标志（延迟一点，防止快速连续点击）
            setTimeout(function() {
                isToggling = false;
            }, 100);
        }

        // 绑定点击事件
        toggle.addEventListener('click', handleThemeToggle);

        // 监听系统主题变化（仅在未手动设置时）
        // 使用 once 选项确保只绑定一次
        if (!window.folioThemeSystemListenerAdded) {
            window.folioThemeSystemListenerAdded = true;
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
            // 只有在没有手动设置时才跟随系统
            if (!localStorage.getItem('mpb-theme')) {
                    const newTheme = e.matches ? 'dark' : 'light';
                    document.documentElement.setAttribute('data-theme', newTheme);
                    if (document.body) {
                        document.body.setAttribute('data-theme', newTheme);
                    }
            }
        });
        }
        
        // 初始化基于日落时间的自动切换
        initAutoSunsetTheme();
    }
    
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
     * 计算下次切换时间（毫秒）
     * @param {string} sunriseTime - 日出时间 (HH:MM)
     * @param {string} sunsetTime - 日落时间 (HH:MM)
     * @returns {number} 距离下次切换的毫秒数
     */
    function getNextSwitchTime(sunriseTime, sunsetTime) {
        var now = new Date();
        var currentHour = now.getHours();
        var currentMinute = now.getMinutes();
        var currentTime = currentHour * 60 + currentMinute;
        
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
        
        var nextSwitch = new Date();
        var shouldDark = shouldUseDarkThemeByTime(sunriseTime, sunsetTime);
        
        if (shouldDark) {
            // 当前是暗色主题，下次切换是日出
            if (currentTime < sunriseTimeMinutes) {
                // 今天还没到日出时间
                nextSwitch.setHours(sunriseHour, sunriseMinute, 0, 0);
            } else {
                // 今天已经过了日出时间，切换到明天日出
                nextSwitch.setDate(nextSwitch.getDate() + 1);
                nextSwitch.setHours(sunriseHour, sunriseMinute, 0, 0);
            }
        } else {
            // 当前是亮色主题，下次切换是日落
            if (currentTime < sunsetTimeMinutes) {
                // 今天还没到日落时间
                nextSwitch.setHours(sunsetHour, sunsetMinute, 0, 0);
            } else {
                // 今天已经过了日落时间，切换到明天日落
                nextSwitch.setDate(nextSwitch.getDate() + 1);
                nextSwitch.setHours(sunsetHour, sunsetMinute, 0, 0);
            }
        }
        
        return nextSwitch.getTime() - now.getTime();
    }
    
    /**
     * 基于日落时间的自动主题切换
     */
    function initAutoSunsetTheme() {
        // 检查是否启用了自动日落切换
        // 优先级：1. 后台设置 2. localStorage
        
        // 从后台设置获取配置（如果不存在则使用默认值）
        if (typeof folioThemeInit === 'undefined' || !folioThemeInit) {
            return; // If config does not exist, do not enable auto switch
        }
        
        // 兼容字符串和布尔值：true, "1", 1 都视为启用
        var backendAutoSunset = folioThemeInit.autoSunsetEnabled === true 
            || folioThemeInit.autoSunsetEnabled === '1' 
            || folioThemeInit.autoSunsetEnabled === 1
            || folioThemeInit.autoSunsetEnabled === 'true';
        var sunriseTime = folioThemeInit.sunriseTime || '06:00';
        var sunsetTime = folioThemeInit.sunsetTime || '18:00';
        
        const localStorageAutoSunset = localStorage.getItem('folio-auto-sunset') === 'true';
        const autoSunsetEnabled = backendAutoSunset || localStorageAutoSunset;
        
        if (!autoSunsetEnabled) {
            return;
        }
        
        // 如果后台强制启用，清除手动设置的主题
        if (backendAutoSunset) {
            const manualTheme = localStorage.getItem('mpb-theme');
            if (manualTheme) {
                // 后台强制启用自动切换，清除手动设置
                localStorage.removeItem('mpb-theme');
            }
            localStorage.setItem('folio-auto-sunset', 'true');
        } else {
            // 检查是否有手动设置的主题（如果有，则不自动切换）
            const manualTheme = localStorage.getItem('mpb-theme');
            if (manualTheme) {
                return;
            }
        }
        
        // 立即检查并应用主题
        checkAndApplySunsetTheme(sunriseTime, sunsetTime);
        
        // 设置定时检查
        scheduleNextCheck(sunriseTime, sunsetTime);
    }
    
    /**
     * 检查并应用基于日落时间的主题
     */
    function checkAndApplySunsetTheme(sunriseTime, sunsetTime) {
        const shouldDark = shouldUseDarkThemeByTime(sunriseTime, sunsetTime);
        const currentTheme = document.documentElement.getAttribute('data-theme');
        const targetTheme = shouldDark ? 'dark' : 'light';
        
        // 只在主题需要改变时才切换
        if (currentTheme !== targetTheme) {
            document.documentElement.setAttribute('data-theme', targetTheme);
            if (document.body) {
                document.body.setAttribute('data-theme', targetTheme);
            }
        }
    }
    
    /**
     * 安排下次检查（精确时间切换）
     */
    function scheduleNextCheck(sunriseTime, sunsetTime) {
        // 清除之前的定时器
        if (window.folioSunsetTimer) {
            clearTimeout(window.folioSunsetTimer);
            window.folioSunsetTimer = null;
        }
        
        // 检查是否仍然启用自动切换
        const autoSunsetEnabled = localStorage.getItem('folio-auto-sunset') === 'true';
        const manualTheme = localStorage.getItem('mpb-theme');
        
        // 如果用户手动设置了主题或禁用了自动切换，停止定时器
        if (manualTheme || !autoSunsetEnabled) {
            return;
        }
        
        // 计算下次切换的精确时间（毫秒）
        const nextSwitchTime = getNextSwitchTime(sunriseTime, sunsetTime);
        
        // 如果计算出的时间无效或为负数，不设置定时器
        if (!nextSwitchTime || nextSwitchTime < 0) {
            return;
        }
        
        // 设置精确的定时器，在准确的时间点切换
        window.folioSunsetTimer = setTimeout(function() {
            // 切换主题
            checkAndApplySunsetTheme(sunriseTime, sunsetTime);
            // 重新安排下次切换
            scheduleNextCheck(sunriseTime, sunsetTime);
        }, nextSwitchTime);
    }

    /**
     * Mobile Navigation Toggle
     */
    function initMobileNav() {
        const toggle = document.getElementById('mobile-menu-toggle');
        const closeBtn = document.getElementById('mobile-menu-close');
        const menu = document.getElementById('mobile-menu');
        
        if (!toggle || !menu) return;
        
        function toggleMenu() {
            const isExpanded = toggle.getAttribute('aria-expanded') === 'true';
            
            toggle.setAttribute('aria-expanded', !isExpanded);
            menu.setAttribute('aria-hidden', isExpanded);
            menu.classList.toggle('is-open');
            menu.classList.toggle('hidden');
            
            // Toggle menu icon
            const openIcon = toggle.querySelector('.menu-icon-open');
            const closeIcon = toggle.querySelector('.menu-icon-close');
            if (openIcon && closeIcon) {
                openIcon.classList.toggle('hidden');
                closeIcon.classList.toggle('hidden');
            }
            
            // Prevent body scroll when menu is open
            document.body.style.overflow = isExpanded ? '' : 'hidden';
        }
        
        toggle.addEventListener('click', toggleMenu);
        
        // Close button
        if (closeBtn) {
            closeBtn.addEventListener('click', toggleMenu);
        }
        
        // Close menu on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && menu.classList.contains('is-open')) {
                toggleMenu();
            }
        });
        
        // Close menu when clicking on a link
        menu.querySelectorAll('a').forEach(function(link) {
            link.addEventListener('click', function() {
                if (menu.classList.contains('is-open')) {
                    toggleMenu();
                }
            });
        });
    }

    /**
     * Lazy Loading for Images
     */
    function initLazyLoading() {
        // 处理使用 data-src 的图片（旧版本懒加载方式）
        const dataSrcImages = document.querySelectorAll('img[data-src]');
        if (dataSrcImages.length > 0) {
            // 立即加载可见的图片
            dataSrcImages.forEach(function(img) {
                // 检查图片是否在视口中
                const rect = img.getBoundingClientRect();
                const isVisible = (
                    rect.top >= 0 &&
                    rect.left >= 0 &&
                    rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
                    rect.right <= (window.innerWidth || document.documentElement.clientWidth)
                );
                
                // 如果图片可见，立即加载
                if (isVisible) {
                    const dataSrc = img.getAttribute('data-src');
                    if (dataSrc) {
                        img.src = dataSrc;
                        img.removeAttribute('data-src');
                        img.classList.add('loaded');
                    }
                }
            });
            
            // 使用 IntersectionObserver 处理不可见的图片
            if ('IntersectionObserver' in window) {
                const remainingImages = document.querySelectorAll('img[data-src]');
                if (remainingImages.length > 0) {
                    const dataSrcObserver = new IntersectionObserver(function(entries, observer) {
                        entries.forEach(function(entry) {
                            if (entry.isIntersecting) {
                                const img = entry.target;
                                const dataSrc = img.getAttribute('data-src');
                                if (dataSrc) {
                                    img.src = dataSrc;
                                    img.removeAttribute('data-src');
                                    img.classList.add('loaded');
                                    observer.unobserve(img);
                                }
                            }
                        });
                    }, {
                        rootMargin: '50px' // Start loading 50px before entering viewport
                    });

                    remainingImages.forEach(function(img) {
                        dataSrcObserver.observe(img);
                    });
                }
            } else {
                // 降级方案：如果浏览器不支持 IntersectionObserver，直接加载所有图片
                dataSrcImages.forEach(function(img) {
                    const dataSrc = img.getAttribute('data-src');
                    if (dataSrc) {
                        img.src = dataSrc;
                        img.removeAttribute('data-src');
                        img.classList.add('loaded');
                    }
                });
            }
        }
        
        // 处理原生懒加载的图片（添加loaded类用于样式）
        if ('IntersectionObserver' in window) {
            const lazyImages = document.querySelectorAll('img[loading="lazy"]:not([data-src])');
            
            const imageObserver = new IntersectionObserver(function(entries, observer) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.classList.add('loaded');
                        observer.unobserve(img);
                    }
                });
            });

            lazyImages.forEach(function(img) {
                imageObserver.observe(img);
            });
        }
    }

    /**
     * Smooth Scroll for Anchor Links
     */
    function initSmoothScroll() {
        document.querySelectorAll('a[href^="#"]').forEach(function(anchor) {
            anchor.addEventListener('click', function(e) {
                const targetId = this.getAttribute('href');
                if (targetId === '#') return;
                
                const target = document.querySelector(targetId);
                if (target) {
                    e.preventDefault();
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    }

    /**
     * Keyboard Navigation Enhancement
     */
    function initKeyboardNavigation() {
        // Add keyboard support for content cards
        const contentCards = document.querySelectorAll('.post-card, article.post');
        
        contentCards.forEach(function(card) {
            const link = card.querySelector('a');
            if (link) {
                card.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        link.click();
                    }
                });
            }
        });
    }

    /**
     * Share Function (exposed globally)
     */
    window.mpbShare = function() {
        if (navigator.share) {
            navigator.share({
                title: document.title,
                url: window.location.href
            }).catch(function(err) {
                if (err.name !== 'AbortError') {
                    showShareModal();
                }
            });
        } else {
            // Fallback: copy to clipboard or show modal
            if (window.FolioCore) {
                FolioCore.copyToClipboard(window.location.href).catch(function() {
                    showShareModal();
                });
            } else if (navigator.clipboard) {
                navigator.clipboard.writeText(window.location.href).then(function() {
                    showNotification(t('copy_link_success', 'Link copied to clipboard'));
                }).catch(function() {
                    showShareModal();
                });
            } else {
                showShareModal();
            }
        }
    };

    /**
     * Show share modal with social links
     */
    function showShareModal() {
        const url = window.location.href;
        const title = encodeURIComponent(document.title);
        const encodedUrl = encodeURIComponent(url);
        
        // Remove existing modal
        const existing = document.querySelector('.mpb-share-modal');
        if (existing) existing.remove();
        
        const modal = document.createElement('div');
        modal.className = 'mpb-share-modal';
        modal.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;z-index:9999;';
        modal.innerHTML = `
            <div style="background:#fff;border-radius:12px;padding:24px;max-width:320px;margin:16px;box-shadow:0 20px 25px -5px rgba(0,0,0,0.1);">
                <h3 style="font-size:18px;font-weight:700;margin-bottom:16px;">${t('share_to', 'Share to')}</h3>
                <div style="display:flex;gap:16px;justify-content:center;margin-bottom:16px;">
                    <a href="https://twitter.com/intent/tweet?url=${encodedUrl}&text=${title}" target="_blank" rel="noopener" style="padding:12px;background:#f3f4f6;border-radius:50%;color:#333;transition:background 0.2s;" onmouseover="this.style.background='#e5e7eb'" onmouseout="this.style.background='#f3f4f6'">
                        <svg style="width:20px;height:20px;" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                    </a>
                    <a href="https://www.facebook.com/sharer/sharer.php?u=${encodedUrl}" target="_blank" rel="noopener" style="padding:12px;background:#f3f4f6;border-radius:50%;color:#333;transition:background 0.2s;" onmouseover="this.style.background='#e5e7eb'" onmouseout="this.style.background='#f3f4f6'">
                        <svg style="width:20px;height:20px;" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                    </a>
                    <a href="https://www.linkedin.com/shareArticle?mini=true&url=${encodedUrl}&title=${title}" target="_blank" rel="noopener" style="padding:12px;background:#f3f4f6;border-radius:50%;color:#333;transition:background 0.2s;" onmouseover="this.style.background='#e5e7eb'" onmouseout="this.style.background='#f3f4f6'">
                        <svg style="width:20px;height:20px;" fill="currentColor" viewBox="0 0 24 24"><path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/></svg>
                    </a>
                </div>
                <div style="display:flex;align-items:center;gap:8px;background:#f3f4f6;border-radius:8px;padding:8px 12px;">
                    <input type="text" value="${url}" readonly style="flex:1;background:transparent;font-size:12px;border:none;outline:none;" id="mpb-share-url">
                    <button onclick="document.getElementById('mpb-share-url').select();navigator.clipboard.writeText('${url}');" style="font-size:11px;font-weight:700;text-transform:uppercase;color:#666;cursor:pointer;border:none;background:none;">${t('copy', 'Copy')}</button>
                </div>
                <button onclick="this.closest('.mpb-share-modal').remove()" style="margin-top:16px;width:100%;padding:8px;font-size:12px;font-weight:700;text-transform:uppercase;color:#666;cursor:pointer;border:none;background:none;">${t('close', 'Close')}</button>
            </div>
        `;
        
        modal.addEventListener('click', function(e) {
            if (e.target === modal) modal.remove();
        });
        
        document.body.appendChild(modal);
    }

    /**
     * Fallback copy to clipboard for older browsers
     */
    function fallbackCopyToClipboard(text) {
        if (window.FolioCore) {
            return FolioCore.fallbackCopyToClipboard(text);
        } else {
            // 原有的降级方案
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            document.body.appendChild(textArea);
            textArea.select();
            
            try {
                document.execCommand('copy');
                showNotification(t('copy_link_success', 'Link copied to clipboard'));
            } catch (err) {
                showNotification(t('copy_failed_manual', 'Copy failed, please copy manually'));
            }
            
            document.body.removeChild(textArea);
        }
    }

    /**
     * Show notification toast - 使用核心功能
     */
    function showNotification(message) {
        if (window.FolioCore) {
            FolioCore.showNotification(message, 'info');
        } else {
            // 降级方案 - 保留原有实现
            const existing = document.querySelector('.mpb-notification');
            if (existing) {
                existing.remove();
            }

            const notification = document.createElement('div');
            notification.className = 'mpb-notification';
            notification.textContent = message;
            notification.style.cssText = `
                position: fixed;
                bottom: 20px;
                left: 50%;
                transform: translateX(-50%);
                background: #000;
                color: #fff;
                padding: 12px 24px;
                border-radius: 4px;
                font-size: 14px;
                font-weight: 500;
                z-index: 9999;
                animation: fadeInUp 0.3s ease-out;
            `;

            document.body.appendChild(notification);

            setTimeout(function() {
                notification.style.animation = 'fadeOut 0.3s ease-out';
                setTimeout(function() {
                    notification.remove();
                }, 300);
            }, 3000);
        }
    }

    /**
     * Back to Top Button
     */
    function initBackToTop() {
        const backToTop = document.getElementById('back-to-top');
        if (!backToTop) return;
        
        let isVisible = false;
        let ticking = false;
        
        // 滚动显示/隐藏（使用节流优化性能）
        function toggleBackToTop() {
            const scrollY = window.pageYOffset;
            const shouldShow = scrollY > 300;
            
            if (shouldShow !== isVisible) {
                isVisible = shouldShow;
                if (shouldShow) {
                    backToTop.classList.add('show');
                } else {
                    backToTop.classList.remove('show');
                }
            }
            ticking = false;
        }
        
        // 节流滚动事件
        function requestTick() {
            if (!ticking) {
                requestAnimationFrame(toggleBackToTop);
                ticking = true;
            }
        }
        
        // 初始检查
        toggleBackToTop();
        
        // 滚动事件（使用节流）
        window.addEventListener('scroll', requestTick, { passive: true });
        
        // 点击返回顶部
        backToTop.addEventListener('click', function(e) {
            e.preventDefault();
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }

    /**
     * Like Button
     */
    function initLikeButton() {
        const likeBtn = document.getElementById('like-btn');
        if (!likeBtn) return;

        likeBtn.addEventListener('click', function() {
            if (likeBtn.disabled) return;

            const postId = likeBtn.dataset.postId;
            const likeCount = document.getElementById('like-count');
            const heartIcon = likeBtn.querySelector('svg');

            // 检查是否有 AJAX 配置
            if (typeof folio_ajax === 'undefined') {
                showNotification(t('like_unavailable', 'Like feature is currently unavailable'));
                return;
            }

            // 发送 AJAX 请求
            const formData = new FormData();
            formData.append('action', 'folio_like_post');
            formData.append('post_id', postId);
            formData.append('nonce', folio_ajax.nonce);

            fetch(folio_ajax.ajax_url, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // 更新 UI
                    likeCount.textContent = data.data.likes;
                    likeBtn.classList.add('text-red-500');
                    heartIcon.setAttribute('fill', 'currentColor');
                    likeBtn.disabled = true;
                    showNotification(data.data.message);
                } else {
                    showNotification(data.data.message || t('like_failed', 'Like failed'));
                }
            })
            .catch(function() {
                showNotification(t('network_error_retry_later', 'Network error, please try again later'));
            });
        });

        // 收藏按钮
        initFavoriteButton();
    }

    /**
     * Favorite Button
     */
    function initFavoriteButton() {
        const favoriteBtn = document.getElementById('favorite-btn');
        if (!favoriteBtn) return;

        favoriteBtn.addEventListener('click', function() {
            const postId = favoriteBtn.dataset.postId;
            const isFavorited = favoriteBtn.dataset.favorited === '1';
            const favoriteCount = document.getElementById('favorite-count');
            const starIcon = favoriteBtn.querySelector('svg');

            // 检查是否有 AJAX 配置
            if (typeof folio_ajax === 'undefined') {
                showNotification(t('favorite_unavailable', 'Favorite feature is currently unavailable'));
                return;
            }

            // 发送 AJAX 请求
            const formData = new FormData();
            formData.append('action', 'folio_favorite_post');
            formData.append('post_id', postId);
            formData.append('action_type', isFavorited ? 'remove' : 'add');
            formData.append('nonce', folio_ajax.nonce);

            fetch(folio_ajax.ajax_url, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                // 检查响应状态
                if (!response.ok) {
                    throw new Error('HTTP error! status: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // 更新 UI
                    favoriteCount.textContent = data.data.favorites;
                    
                    if (data.data.is_favorited) {
                        favoriteBtn.classList.add('text-yellow-500');
                        starIcon.setAttribute('fill', 'currentColor');
                        favoriteBtn.dataset.favorited = '1';
                    } else {
                        favoriteBtn.classList.remove('text-yellow-500');
                        starIcon.setAttribute('fill', 'none');
                        favoriteBtn.dataset.favorited = '0';
                    }
                    
                    showNotification(data.data.message);
                } else {
                    // 检查是否需要登录
                    if (data.data && data.data.need_login) {
                        showNotification(t('login_before_favorite', 'Please login before favoriting'));
                        // 可选：跳转到登录页
                        // window.location.href = '/wp-login.php';
                    } else {
                        showNotification(data.data && data.data.message ? data.data.message : t('operation_failed', 'Operation failed'));
                    }
                }
            })
            .catch(function(error) {
                console.error('Favorite action error:', error);
                showNotification(t('network_error_retry_later', 'Network error, please try again later'));
            });
        });
    }

})();
