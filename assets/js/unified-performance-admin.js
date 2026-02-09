/**
 * Unified Performance & Cache Admin JavaScript
 * 统一性能与缓存管理页面交互功能
 */

(function($) {
    'use strict';

    // 统一管理对象
    const UnifiedPerformanceAdmin = {
        
        // 初始化
        init: function() {
            this.bindEvents();
            this.initCharts();
            this.loadInitialData();
        },

        // 绑定事件
        bindEvents: function() {
            // 标签页切换已在PHP中处理
            
            // 性能概览页面事件
            $('#refresh-all-stats').on('click', this.refreshAllStats);
            $('#clear-all-cache').on('click', this.clearAllCache);
            $('#run-health-check').on('click', this.runHealthCheck);
            $('#optimize-performance').on('click', this.optimizePerformance);
            
            // 缓存管理页面事件
            $(document).on('click', '.cache-clear-btn', this.clearSingleCache);
            $(document).on('click', '.cache-refresh-btn', this.refreshCacheStats);
            $('#clear-all-cache-detailed').on('click', this.clearAllCache);
            $('#clear-expired-cache').on('click', this.clearExpiredCache);
            $('#clear-user-cache').on('click', this.clearUserCache);
            $('#optimize-cache').on('click', this.optimizeCache);
            $('#preload-cache').on('click', this.preloadCache);
            $('#analyze-cache').on('click', this.analyzeCache);
            $('#export-cache-stats').on('click', this.exportStats);
            $('#reset-cache-stats').on('click', this.resetStats);
            $('#schedule-cleanup').on('click', this.scheduleCleanup);
            
            // 对象缓存管理事件
            $('#install-object-cache-btn').on('click', this.installObjectCache);
            $('#uninstall-object-cache-btn').on('click', this.uninstallObjectCache);
            $('#reinstall-object-cache-btn').on('click', this.reinstallObjectCache);
            $('#replace-object-cache-btn').on('click', this.replaceObjectCache);
        },

        // 初始化图表
        initCharts: function() {
            if (typeof Chart === 'undefined') {
                console.warn('Chart.js not loaded');
                return;
            }

            // 性能趋势图
            const performanceCtx = document.getElementById('performanceChart');
            if (performanceCtx) {
                this.performanceChart = new Chart(performanceCtx, {
                    type: 'line',
                    data: {
                        labels: ['1小时前', '45分钟前', '30分钟前', '15分钟前', '现在'],
                        datasets: [{
                            label: '页面加载时间(s)',
                            data: [1.5, 1.3, 1.2, 1.1, 1.2],
                            borderColor: '#0073aa',
                            backgroundColor: 'rgba(0, 115, 170, 0.15)',
                            fill: true,
                            tension: 0.4,
                            borderWidth: 3,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            pointBackgroundColor: '#0073aa',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2
                        }, {
                            label: '缓存命中率(%)',
                            data: [75, 78, 82, 85, 87],
                            borderColor: '#00a32a',
                            backgroundColor: 'rgba(0, 163, 42, 0.15)',
                            fill: true,
                            tension: 0.4,
                            borderWidth: 3,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            pointBackgroundColor: '#00a32a',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            yAxisID: 'y1'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        interaction: {
                            mode: 'index',
                            intersect: false
                        },
                        plugins: {
                            legend: {
                                display: true,
                                position: 'bottom',
                                labels: {
                                    padding: 15,
                                    usePointStyle: true,
                                    font: {
                                        size: 12
                                    }
                                }
                            },
                            tooltip: {
                                mode: 'index',
                                intersect: false,
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                padding: 12,
                                titleFont: {
                                    size: 14,
                                    weight: 'bold'
                                },
                                bodyFont: {
                                    size: 13
                                },
                                callbacks: {
                                    label: function(context) {
                                        return context.dataset.label + ': ' + context.parsed.y.toLocaleString();
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                grid: {
                                    display: true,
                                    color: 'rgba(0, 0, 0, 0.05)'
                                },
                                ticks: {
                                    font: {
                                        size: 11
                                    }
                                }
                            },
                            y: {
                                beginAtZero: true,
                                position: 'left',
                                grid: {
                                    display: true,
                                    color: 'rgba(0, 0, 0, 0.05)'
                                },
                                ticks: {
                                    precision: 0,
                                    font: {
                                        size: 11
                                    },
                                    callback: function(value) {
                                        return value.toLocaleString();
                                    }
                                }
                            },
                            y1: {
                                beginAtZero: true,
                                type: 'linear',
                                display: true,
                                position: 'right',
                                grid: {
                                    drawOnChartArea: false,
                                    color: 'rgba(0, 0, 0, 0.05)'
                                },
                                ticks: {
                                    precision: 0,
                                    font: {
                                        size: 11
                                    },
                                    callback: function(value) {
                                        return value.toLocaleString();
                                    }
                                }
                            }
                        }
                    }
                });
            }

            // 缓存分布图
            const cacheCtx = document.getElementById('cacheChart');
            if (cacheCtx) {
                this.cacheChart = new Chart(cacheCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['权限缓存', '预览缓存', '查询缓存', '对象缓存'],
                        datasets: [{
                            data: [30, 25, 20, 25],
                            backgroundColor: [
                                'rgba(220, 50, 50, 0.8)',
                                'rgba(0, 115, 170, 0.8)',
                                'rgba(255, 185, 0, 0.8)',
                                'rgba(0, 163, 42, 0.8)'
                            ],
                            borderColor: [
                                '#dc3232',
                                '#0073aa',
                                '#ffb900',
                                '#00a32a'
                            ],
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        aspectRatio: 1.6,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 15,
                                    font: {
                                        size: 12
                                    }
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const label = context.label || '';
                                        const value = context.parsed || 0;
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                        return label + ': ' + value.toLocaleString() + ' (' + percentage + '%)';
                                    }
                                }
                            }
                        }
                    }
                });
            }
        },

        // 加载初始数据
        loadInitialData: function() {
            // 延迟加载，避免与PHP渲染冲突
            setTimeout(() => {
                this.updatePerformanceStats();
                this.updateCacheStats();
            }, 1000);
        },

        // 刷新所有统计
        refreshAllStats: function(e) {
            if (e) e.preventDefault();
            
            const $btn = $(this);
            const originalText = $btn.text();
            
            $btn.prop('disabled', true).text('🔄 刷新中...');
            
            // 刷新缓存统计
            $.post(folioPerformanceAdmin.ajaxurl, {
                action: 'folio_cache_refresh_stats',
                nonce: folioPerformanceAdmin.nonce
            })
            .done(function(response) {
                if (response.success) {
                    UnifiedPerformanceAdmin.showNotification('统计数据已刷新', 'success');
                    
                    // 更新显示
                    UnifiedPerformanceAdmin.updatePerformanceStats();
                    UnifiedPerformanceAdmin.updateCacheStats();
                } else {
                    UnifiedPerformanceAdmin.showNotification('刷新失败: ' + response.data, 'error');
                }
            })
            .fail(function() {
                UnifiedPerformanceAdmin.showNotification('网络错误，请重试', 'error');
            })
            .always(function() {
                $btn.prop('disabled', false).text(originalText);
            });
        },

        // 更新性能统计
        updatePerformanceStats: function() {
            $.post(folioPerformanceAdmin.ajaxurl, {
                action: 'folio_performance_stats',
                nonce: folioPerformanceAdmin.nonce
            })
            .done(function(response) {
                if (response.success && response.data) {
                    UnifiedPerformanceAdmin.updatePerformanceDisplay(response.data);
                } else {
                    console.warn('Performance stats request failed:', response);
                }
            })
            .fail(function(xhr, status, error) {
                console.error('Performance stats AJAX error:', status, error);
            });
        },

        // 更新缓存统计
        updateCacheStats: function() {
            $.post(folioPerformanceAdmin.ajaxurl, {
                action: 'folio_cache_stats',
                nonce: folioPerformanceAdmin.nonce
            })
            .done(function(response) {
                if (response.success && response.data) {
                    UnifiedPerformanceAdmin.updateCacheDisplay(response.data);
                } else {
                    console.warn('Cache stats request failed:', response);
                }
            })
            .fail(function(xhr, status, error) {
                console.error('Cache stats AJAX error:', status, error);
            });
        },

        // 更新性能显示
        updatePerformanceDisplay: function(stats) {
            // 检查stats对象是否有效
            if (!stats || typeof stats !== 'object') {
                console.warn('Invalid performance stats data:', stats);
                return;
            }

            // 更新性能卡片
            $('.stat-card').each(function() {
                const $card = $(this);
                const $h3 = $card.find('h3');
                const $value = $card.find('.stat-value');
                const $status = $card.find('.stat-status');
                
                const title = $h3.text();
                
                if (title.includes('页面加载时间') && typeof stats.avg_load_time === 'number') {
                    $value.text(stats.avg_load_time.toFixed(2) + 's');
                    $status.removeClass('good warning critical')
                           .addClass(stats.avg_load_time < 2 ? 'good' : 'warning')
                           .text(stats.avg_load_time < 2 ? '优秀' : '需要优化');
                }
                
                if (title.includes('内存使用') && typeof stats.memory_usage === 'number') {
                    $value.text(UnifiedPerformanceAdmin.formatBytes(stats.memory_usage));
                    $status.removeClass('good warning critical')
                           .addClass(stats.memory_usage < 128 * 1024 * 1024 ? 'good' : 'warning')
                           .text(stats.memory_usage < 128 * 1024 * 1024 ? '正常' : '偏高');
                }
                
                if (title.includes('数据库查询') && typeof stats.db_queries === 'number') {
                    $value.text(stats.db_queries);
                    $status.removeClass('good warning critical')
                           .addClass(stats.db_queries < 50 ? 'good' : 'warning')
                           .text(stats.db_queries < 50 ? '优秀' : '需要优化');
                }
            });
        },

        // 更新缓存显示
        updateCacheDisplay: function(stats) {
            // 检查stats对象是否有效
            if (!stats || typeof stats !== 'object') {
                console.warn('Invalid cache stats data:', stats);
                return;
            }

            // 更新缓存卡片
            $('.cache-card').each(function() {
                const $card = $(this);
                const $h4 = $card.find('h4');
                const $value = $card.find('.cache-card-value');
                const $status = $card.find('.cache-card-status');
                
                const title = $h4.text();
                
                if (title.includes('缓存后端') && stats.cache_backend) {
                    $value.text(stats.cache_backend);
                    if (stats.backend_status) {
                        $status.removeClass('good warning critical')
                               .addClass(stats.backend_status)
                               .text(stats.backend_status === 'good' ? '已优化' : '可改进');
                    }
                }
                
                if (title.includes('缓存条目') && typeof stats.total_entries === 'number') {
                    $value.text(stats.total_entries.toLocaleString());
                }
                
                if (title.includes('性能提升') && typeof stats.performance_boost === 'number') {
                    $value.text(stats.performance_boost + '%');
                }
            });
            
            // 更新表格数据
            $('.cache-types-section tbody tr').each(function() {
                const $row = $(this);
                const cacheType = $row.find('td:first strong').text();
                
                if (cacheType.includes('权限验证') && stats.permission_cache && 
                    typeof stats.permission_cache.count === 'number' && 
                    typeof stats.permission_cache.hit_rate === 'number') {
                    $row.find('td:nth-child(3)').text(stats.permission_cache.count.toLocaleString());
                    $row.find('td:nth-child(4)').text(stats.permission_cache.hit_rate.toFixed(1) + '%');
                }
                
                if (cacheType.includes('内容预览') && stats.preview_cache && 
                    typeof stats.preview_cache.count === 'number' && 
                    typeof stats.preview_cache.hit_rate === 'number') {
                    $row.find('td:nth-child(3)').text(stats.preview_cache.count.toLocaleString());
                    $row.find('td:nth-child(4)').text(stats.preview_cache.hit_rate.toFixed(1) + '%');
                }
                
                if (cacheType.includes('查询缓存') && stats.query_cache && 
                    typeof stats.query_cache.count === 'number' && 
                    typeof stats.query_cache.hit_rate === 'number') {
                    $row.find('td:nth-child(3)').text(stats.query_cache.count.toLocaleString());
                    $row.find('td:nth-child(4)').text(stats.query_cache.hit_rate.toFixed(1) + '%');
                }
                
                if (cacheType.includes('对象缓存') && stats.object_cache && 
                    typeof stats.object_cache.count === 'number' && 
                    typeof stats.object_cache.hit_rate === 'number') {
                    $row.find('td:nth-child(3)').text(stats.object_cache.count.toLocaleString());
                    $row.find('td:nth-child(4)').text(stats.object_cache.hit_rate.toFixed(1) + '%');
                }
            });
        },

        // 清除所有缓存
        clearAllCache: function(e) {
            e.preventDefault();
            
            if (!confirm(folioPerformanceAdmin.strings.confirm_clear_all)) {
                return;
            }
            
            const $btn = $(this);
            const originalText = $btn.text();
            
            $btn.prop('disabled', true).text('清除中...');
            
            // 调试信息
            if (folioPerformanceAdmin.debug) {
                console.log('Clear cache request:', {
                    action: 'folio_cache_clear',
                    cache_type: 'all',
                    nonce: folioPerformanceAdmin.nonce,
                    ajaxurl: folioPerformanceAdmin.ajaxurl
                });
            }
            
            $.post(folioPerformanceAdmin.ajaxurl, {
                action: 'folio_cache_clear',
                cache_type: 'all',
                nonce: folioPerformanceAdmin.nonce
            })
            .done(function(response) {
                if (response.success) {
                    UnifiedPerformanceAdmin.showNotification('所有缓存已清除', 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    UnifiedPerformanceAdmin.showNotification('清除失败: ' + response.data, 'error');
                    $btn.prop('disabled', false).text(originalText);
                }
            })
            .fail(function() {
                UnifiedPerformanceAdmin.showNotification('网络错误，请重试', 'error');
                $btn.prop('disabled', false).text(originalText);
            });
        },

        // 清除单个缓存
        clearSingleCache: function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const cacheType = $btn.data('type');
            const originalText = $btn.text();
            
            $btn.prop('disabled', true).text('清除中...');
            
            $.post(folioPerformanceAdmin.ajaxurl, {
                action: 'folio_cache_clear',
                cache_type: cacheType,
                nonce: folioPerformanceAdmin.nonce
            })
            .done(function(response) {
                if (response.success) {
                    UnifiedPerformanceAdmin.showNotification('缓存清除成功', 'success');
                    $btn.text('已清除').addClass('button-primary');
                    
                    setTimeout(() => {
                        $btn.removeClass('button-primary').prop('disabled', false).text(originalText);
                        UnifiedPerformanceAdmin.updateCacheStats();
                    }, 2000);
                } else {
                    UnifiedPerformanceAdmin.showNotification('清除失败: ' + response.data, 'error');
                    $btn.prop('disabled', false).text(originalText);
                }
            })
            .fail(function() {
                UnifiedPerformanceAdmin.showNotification('网络错误，请重试', 'error');
                $btn.prop('disabled', false).text(originalText);
            });
        },

        // 运行健康检查
        runHealthCheck: function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const originalText = $btn.text();
            
            $btn.prop('disabled', true).text('🔍 检查中...');
            
            $.post(folioPerformanceAdmin.ajaxurl, {
                action: 'folio_cache_health_check',
                nonce: folioPerformanceAdmin.nonce
            })
            .done(function(response) {
                if (response.success) {
                    UnifiedPerformanceAdmin.showHealthCheckResults(response.data);
                    UnifiedPerformanceAdmin.showNotification('健康检查完成', 'success');
                } else {
                    UnifiedPerformanceAdmin.showNotification('健康检查失败: ' + response.data, 'error');
                }
            })
            .fail(function() {
                UnifiedPerformanceAdmin.showNotification('网络错误，请重试', 'error');
            })
            .always(function() {
                $btn.prop('disabled', false).text(originalText);
            });
        },

        // 显示健康检查结果
        showHealthCheckResults: function(results) {
            let html = '<div class="health-check-results"><h4>健康检查结果</h4>';
            
            Object.keys(results).forEach(key => {
                const result = results[key];
                const statusClass = result.status === 'good' ? 'success' : 'warning';
                html += `<div class="health-item ${statusClass}">
                    <strong>${key}:</strong> ${result.message}
                </div>`;
            });
            
            html += '</div>';
            
            // 显示在页面上或弹窗中
            if ($('#health-check-results').length) {
                $('#health-check-results').html(html).show();
            } else {
                this.showNotification(html, 'info');
            }
        },

        // 优化性能
        optimizePerformance: function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const originalText = $btn.text();
            
            $btn.prop('disabled', true).text('⚡ 优化中...');
            
            $.post(folioPerformanceAdmin.ajaxurl, {
                action: 'folio_cache_optimize',
                nonce: folioPerformanceAdmin.nonce
            })
            .done(function(response) {
                if (response.success) {
                    UnifiedPerformanceAdmin.showNotification('性能优化完成', 'success');
                    if (response.data.optimizations) {
                        const details = response.data.optimizations.join(', ');
                        UnifiedPerformanceAdmin.showNotification('优化项目: ' + details, 'info');
                    }
                    UnifiedPerformanceAdmin.updatePerformanceStats();
                    UnifiedPerformanceAdmin.updateCacheStats();
                } else {
                    UnifiedPerformanceAdmin.showNotification('优化失败: ' + response.data, 'error');
                }
            })
            .fail(function() {
                UnifiedPerformanceAdmin.showNotification('网络错误，请重试', 'error');
            })
            .always(function() {
                $btn.prop('disabled', false).text(originalText);
            });
        },

        // 安装对象缓存
        installObjectCache: function(e) {
            e.preventDefault();
            
            if (!confirm('确定要安装Folio对象缓存吗？这将显著提升网站性能。')) {
                return;
            }
            
            const $btn = $(this);
            const originalText = $btn.text();
            
            $btn.prop('disabled', true).text('安装中...');
            
            $.post(folioPerformanceAdmin.ajaxurl, {
                action: 'folio_install_object_cache',
                nonce: folioPerformanceAdmin.object_cache_nonce
            })
            .done(function(response) {
                if (response.success) {
                    UnifiedPerformanceAdmin.showNotification('✅ 对象缓存安装成功！', 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    UnifiedPerformanceAdmin.showNotification('❌ 安装失败: ' + response.data, 'error');
                    $btn.prop('disabled', false).text(originalText);
                }
            })
            .fail(function() {
                UnifiedPerformanceAdmin.showNotification('❌ 网络错误，请重试', 'error');
                $btn.prop('disabled', false).text(originalText);
            });
        },

        // 其他缓存操作方法
        refreshCacheStats: function(e) {
            e.preventDefault();
            UnifiedPerformanceAdmin.updateCacheStats();
        },

        clearExpiredCache: function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const originalText = $btn.text();
            
            $btn.prop('disabled', true).text('清除中...');
            
            $.post(folioPerformanceAdmin.ajaxurl, {
                action: 'folio_cache_clear',
                cache_type: 'expired',
                nonce: folioPerformanceAdmin.nonce
            })
            .done(function(response) {
                if (response.success) {
                    UnifiedPerformanceAdmin.showNotification('过期缓存已清除', 'success');
                } else {
                    UnifiedPerformanceAdmin.showNotification('清除失败: ' + response.data, 'error');
                }
            })
            .fail(function() {
                UnifiedPerformanceAdmin.showNotification('网络错误，请重试', 'error');
            })
            .always(function() {
                $btn.prop('disabled', false).text(originalText);
            });
        },

        clearUserCache: function(e) {
            e.preventDefault();
            
            const userId = prompt('请输入用户ID（留空清除当前用户缓存）:');
            if (userId === null) return; // 用户取消
            
            const $btn = $(this);
            const originalText = $btn.text();
            
            $btn.prop('disabled', true).text('清除中...');
            
            $.post(folioPerformanceAdmin.ajaxurl, {
                action: 'folio_cache_clear',
                cache_type: 'user',
                user_id: userId || '',
                nonce: folioPerformanceAdmin.nonce
            })
            .done(function(response) {
                if (response.success) {
                    UnifiedPerformanceAdmin.showNotification('用户缓存已清除', 'success');
                } else {
                    UnifiedPerformanceAdmin.showNotification('清除失败: ' + response.data, 'error');
                }
            })
            .fail(function() {
                UnifiedPerformanceAdmin.showNotification('网络错误，请重试', 'error');
            })
            .always(function() {
                $btn.prop('disabled', false).text(originalText);
            });
        },

        optimizeCache: function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const originalText = $btn.text();
            
            $btn.prop('disabled', true).text('优化中...');
            
            $.post(folioPerformanceAdmin.ajaxurl, {
                action: 'folio_cache_optimize',
                nonce: folioPerformanceAdmin.nonce
            })
            .done(function(response) {
                if (response.success) {
                    UnifiedPerformanceAdmin.showNotification('缓存优化完成', 'success');
                    if (response.data.optimizations) {
                        const details = response.data.optimizations.join(', ');
                        UnifiedPerformanceAdmin.showNotification('优化项目: ' + details, 'info');
                    }
                } else {
                    UnifiedPerformanceAdmin.showNotification('优化失败: ' + response.data, 'error');
                }
            })
            .fail(function() {
                UnifiedPerformanceAdmin.showNotification('网络错误，请重试', 'error');
            })
            .always(function() {
                $btn.prop('disabled', false).text(originalText);
            });
        },

        preloadCache: function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const originalText = $btn.text();
            
            $btn.prop('disabled', true).text('预热中...');
            
            $.post(folioPerformanceAdmin.ajaxurl, {
                action: 'folio_cache_preload',
                nonce: folioPerformanceAdmin.nonce
            })
            .done(function(response) {
                if (response.success) {
                    UnifiedPerformanceAdmin.showNotification('缓存预热完成', 'success');
                } else {
                    UnifiedPerformanceAdmin.showNotification('预热失败: ' + response.data, 'error');
                }
            })
            .fail(function() {
                UnifiedPerformanceAdmin.showNotification('网络错误，请重试', 'error');
            })
            .always(function() {
                $btn.prop('disabled', false).text(originalText);
            });
        },

        analyzeCache: function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const originalText = $btn.text();
            
            $btn.prop('disabled', true).text('分析中...');
            
            $.post(folioPerformanceAdmin.ajaxurl, {
                action: 'folio_cache_analyze',
                nonce: folioPerformanceAdmin.nonce
            })
            .done(function(response) {
                if (response.success) {
                    UnifiedPerformanceAdmin.showNotification('缓存分析完成', 'success');
                    // 显示分析结果
                    if (response.data.analysis) {
                        const analysis = response.data.analysis;
                        let message = '分析结果:\n';
                        message += '命中率: ' + analysis.hit_rate + '%\n';
                        message += '效率评分: ' + analysis.efficiency_score + '/100';
                        UnifiedPerformanceAdmin.showNotification(message, 'info');
                    }
                } else {
                    UnifiedPerformanceAdmin.showNotification('分析失败: ' + response.data, 'error');
                }
            })
            .fail(function() {
                UnifiedPerformanceAdmin.showNotification('网络错误，请重试', 'error');
            })
            .always(function() {
                $btn.prop('disabled', false).text(originalText);
            });
        },

        exportStats: function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const originalText = $btn.text();
            
            $btn.prop('disabled', true).text('导出中...');
            
            $.post(folioPerformanceAdmin.ajaxurl, {
                action: 'folio_cache_export_stats',
                nonce: folioPerformanceAdmin.nonce
            })
            .done(function(response) {
                if (response.success) {
                    // 创建下载链接
                    const blob = new Blob([JSON.stringify(response.data, null, 2)], {type: 'application/json'});
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'folio-cache-stats-' + new Date().toISOString().split('T')[0] + '.json';
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    window.URL.revokeObjectURL(url);
                    
                    UnifiedPerformanceAdmin.showNotification('统计数据已导出', 'success');
                } else {
                    UnifiedPerformanceAdmin.showNotification('导出失败: ' + response.data, 'error');
                }
            })
            .fail(function() {
                UnifiedPerformanceAdmin.showNotification('网络错误，请重试', 'error');
            })
            .always(function() {
                $btn.prop('disabled', false).text(originalText);
            });
        },

        resetStats: function(e) {
            e.preventDefault();
            
            if (!confirm('确定要重置所有统计数据吗？此操作不可恢复。')) {
                return;
            }
            
            const $btn = $(this);
            const originalText = $btn.text();
            
            $btn.prop('disabled', true).text('重置中...');
            
            $.post(folioPerformanceAdmin.ajaxurl, {
                action: 'folio_cache_reset_stats',
                nonce: folioPerformanceAdmin.nonce
            })
            .done(function(response) {
                if (response.success) {
                    UnifiedPerformanceAdmin.showNotification('统计数据已重置', 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    UnifiedPerformanceAdmin.showNotification('重置失败: ' + response.data, 'error');
                }
            })
            .fail(function() {
                UnifiedPerformanceAdmin.showNotification('网络错误，请重试', 'error');
            })
            .always(function() {
                $btn.prop('disabled', false).text(originalText);
            });
        },

        scheduleCleanup: function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const originalText = $btn.text();
            
            $btn.prop('disabled', true).text('设置中...');
            
            $.post(folioPerformanceAdmin.ajaxurl, {
                action: 'folio_cache_schedule_cleanup',
                nonce: folioPerformanceAdmin.nonce
            })
            .done(function(response) {
                if (response.success) {
                    UnifiedPerformanceAdmin.showNotification('定时清理已设置', 'success');
                } else {
                    UnifiedPerformanceAdmin.showNotification('设置失败: ' + response.data, 'error');
                }
            })
            .fail(function() {
                UnifiedPerformanceAdmin.showNotification('网络错误，请重试', 'error');
            })
            .always(function() {
                $btn.prop('disabled', false).text(originalText);
            });
        },

        uninstallObjectCache: function(e) {
            e.preventDefault();
            
            if (!confirm('确定要卸载对象缓存吗？这会影响网站性能。')) {
                return;
            }
            
            const $btn = $(this);
            const originalText = $btn.text();
            
            $btn.prop('disabled', true).text('卸载中...');
            
            $.post(folioPerformanceAdmin.ajaxurl, {
                action: 'folio_uninstall_object_cache',
                nonce: folioPerformanceAdmin.object_cache_nonce
            })
            .done(function(response) {
                if (response.success) {
                    UnifiedPerformanceAdmin.showNotification('对象缓存已卸载', 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    UnifiedPerformanceAdmin.showNotification('卸载失败: ' + response.data, 'error');
                }
            })
            .fail(function() {
                UnifiedPerformanceAdmin.showNotification('网络错误，请重试', 'error');
            })
            .always(function() {
                $btn.prop('disabled', false).text(originalText);
            });
        },

        reinstallObjectCache: function(e) {
            e.preventDefault();
            $('#uninstall-object-cache-btn').click();
            setTimeout(() => {
                $('#install-object-cache-btn').click();
            }, 3000);
        },

        replaceObjectCache: function(e) {
            e.preventDefault();
            this.reinstallObjectCache(e);
        },

        // 工具方法
        formatBytes: function(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },

        // 显示通知
        showNotification: function(message, type = 'info') {
            const $notification = $(`
                <div class="folio-notification folio-notification-${type}">
                    <span class="notification-message">${message}</span>
                    <button class="notification-close">&times;</button>
                </div>
            `);
            
            if ($('.folio-notifications').length === 0) {
                $('body').append('<div class="folio-notifications"></div>');
            }
            
            $('.folio-notifications').append($notification);
            
            setTimeout(() => {
                $notification.fadeOut(() => {
                    $notification.remove();
                });
            }, 5000);
            
            $notification.find('.notification-close').on('click', function() {
                $notification.fadeOut(() => {
                    $notification.remove();
                });
            });
        }
    };

    // 页面加载完成后初始化
    $(document).ready(function() {
        UnifiedPerformanceAdmin.init();
    });

    // 全局优化建议处理函数
    window.optimizeCacheConfig = function() {
        UnifiedPerformanceAdmin.showNotification('正在优化缓存配置...', 'info');
        // 实际的优化逻辑
    };

    window.increaseCacheTime = function() {
        UnifiedPerformanceAdmin.showNotification('正在增加缓存时间...', 'info');
        // 实际的优化逻辑
    };

    window.installObjectCache = function() {
        $('#install-object-cache-btn').click();
    };

    window.showCacheGuide = function() {
        const guideContent = `
            <div style="max-width: 500px;">
                <h3>对象缓存安装指南</h3>
                <ol>
                    <li>确保服务器已安装Memcached服务</li>
                    <li>安装PHP Memcached扩展</li>
                    <li>点击"安装Memcached"按钮</li>
                    <li>验证安装是否成功</li>
                </ol>
                <p><strong>注意：</strong>需要服务器管理员权限</p>
            </div>
        `;
        UnifiedPerformanceAdmin.showNotification(guideContent, 'info');
    };

    window.analyzeMemoryUsage = function() {
        UnifiedPerformanceAdmin.showNotification('正在分析内存使用情况...', 'info');
        // 实际的分析逻辑
    };

    window.cleanupPlugins = function() {
        UnifiedPerformanceAdmin.showNotification('建议在插件管理页面检查并停用不需要的插件', 'info');
    };

    window.enableQueryCache = function() {
        UnifiedPerformanceAdmin.showNotification('正在启用查询缓存...', 'info');
        // 实际的启用逻辑
    };

    window.optimizeDatabase = function() {
        UnifiedPerformanceAdmin.showNotification('正在优化数据库...', 'info');
        // 实际的优化逻辑
    };

    window.cleanupDatabase = function() {
        UnifiedPerformanceAdmin.showNotification('正在清理数据库...', 'info');
        // 实际的清理逻辑
    };

    window.checkUpdates = function() {
        window.location.href = '/wp-admin/update-core.php';
    };

    window.setupMonitoring = function() {
        UnifiedPerformanceAdmin.showNotification('性能监控功能正在开发中...', 'info');
    };

    window.viewReports = function() {
        UnifiedPerformanceAdmin.showNotification('性能报告功能正在开发中...', 'info');
    };

    window.runOptimization = function() {
        $('#optimize-performance').click();
    };

    window.preloadCache = function() {
        $('#preload-cache').click();
    };

    window.runHealthCheck = function() {
        $('#run-health-check').click();
    };

})(jQuery);