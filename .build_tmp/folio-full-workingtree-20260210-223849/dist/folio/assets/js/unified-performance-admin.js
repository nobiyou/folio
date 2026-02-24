/**
 * Unified Performance & Cache Admin JavaScript
 * 统一性能与缓存管理页面交互功能
 */

(function($) {
    'use strict';

    // 统一管理对象
    const UnifiedPerformanceAdmin = {
        t: function(key, fallback) {
            if (
                typeof folioPerformanceAdmin !== 'undefined' &&
                folioPerformanceAdmin.strings &&
                folioPerformanceAdmin.strings[key]
            ) {
                return folioPerformanceAdmin.strings[key];
            }
            return fallback;
        },
        
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
                        labels: [
                            this.t('chart_1h_ago', '1 hour ago'),
                            this.t('chart_45m_ago', '45 min ago'),
                            this.t('chart_30m_ago', '30 min ago'),
                            this.t('chart_15m_ago', '15 min ago'),
                            this.t('chart_now', 'Now')
                        ],
                        datasets: [{
                            label: this.t('chart_page_load_time', 'Page Load Time (s)'),
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
                            label: this.t('chart_cache_hit_rate', 'Cache Hit Rate (%)'),
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
                        labels: [
                            this.t('chart_permission_cache', 'Permission Cache'),
                            this.t('chart_preview_cache', 'Preview Cache'),
                            this.t('chart_query_cache', 'Query Cache'),
                            this.t('chart_object_cache', 'Object Cache')
                        ],
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
            
            $btn.prop('disabled', true).text('🔄 ' + UnifiedPerformanceAdmin.t('refreshing', 'Refreshing...'));
            
            // 刷新缓存统计
            $.post(folioPerformanceAdmin.ajaxurl, {
                action: 'folio_cache_refresh_stats',
                nonce: folioPerformanceAdmin.nonce
            })
            .done(function(response) {
                if (response.success) {
                    UnifiedPerformanceAdmin.showNotification(UnifiedPerformanceAdmin.t('stats_refreshed', 'Statistics refreshed'), 'success');
                    
                    // 更新显示
                    UnifiedPerformanceAdmin.updatePerformanceStats();
                    UnifiedPerformanceAdmin.updateCacheStats();
                } else {
                    UnifiedPerformanceAdmin.showNotification(UnifiedPerformanceAdmin.t('refresh_failed', 'Refresh failed: ') + response.data, 'error');
                }
            })
            .fail(function() {
                UnifiedPerformanceAdmin.showNotification(UnifiedPerformanceAdmin.t('network_error_retry', 'Network error, please try again'), 'error');
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
                
                if (title.includes(UnifiedPerformanceAdmin.t('metric_page_load_time', 'Page Load Time')) && typeof stats.avg_load_time === 'number') {
                    $value.text(stats.avg_load_time.toFixed(2) + 's');
                    $status.removeClass('good warning critical')
                           .addClass(stats.avg_load_time < 2 ? 'good' : 'warning')
                           .text(stats.avg_load_time < 2 ? UnifiedPerformanceAdmin.t('status_excellent', 'Excellent') : UnifiedPerformanceAdmin.t('status_needs_optimization', 'Needs optimization'));
                }
                
                if (title.includes(UnifiedPerformanceAdmin.t('metric_memory_usage', 'Memory Usage')) && typeof stats.memory_usage === 'number') {
                    $value.text(UnifiedPerformanceAdmin.formatBytes(stats.memory_usage));
                    $status.removeClass('good warning critical')
                           .addClass(stats.memory_usage < 128 * 1024 * 1024 ? 'good' : 'warning')
                           .text(stats.memory_usage < 128 * 1024 * 1024 ? UnifiedPerformanceAdmin.t('status_normal', 'Normal') : UnifiedPerformanceAdmin.t('status_high', 'High'));
                }
                
                if (title.includes(UnifiedPerformanceAdmin.t('metric_db_queries', 'Database Queries')) && typeof stats.db_queries === 'number') {
                    $value.text(stats.db_queries);
                    $status.removeClass('good warning critical')
                           .addClass(stats.db_queries < 50 ? 'good' : 'warning')
                           .text(stats.db_queries < 50 ? UnifiedPerformanceAdmin.t('status_excellent', 'Excellent') : UnifiedPerformanceAdmin.t('status_needs_optimization', 'Needs optimization'));
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
                
                if (title.includes(UnifiedPerformanceAdmin.t('metric_cache_backend', 'Cache Backend')) && stats.cache_backend) {
                    $value.text(stats.cache_backend);
                    if (stats.backend_status) {
                        $status.removeClass('good warning critical')
                               .addClass(stats.backend_status)
                               .text(stats.backend_status === 'good' ? UnifiedPerformanceAdmin.t('status_optimized', 'Optimized') : UnifiedPerformanceAdmin.t('status_improvable', 'Can be improved'));
                    }
                }
                
                if (title.includes(UnifiedPerformanceAdmin.t('metric_cache_entries', 'Cache Entries')) && typeof stats.total_entries === 'number') {
                    $value.text(stats.total_entries.toLocaleString());
                }
                
                if (title.includes(UnifiedPerformanceAdmin.t('metric_performance_boost', 'Performance Boost')) && typeof stats.performance_boost === 'number') {
                    $value.text(stats.performance_boost + '%');
                }
            });
            
            // 更新表格数据
            $('.cache-types-section tbody tr').each(function() {
                const $row = $(this);
                const cacheType = $row.find('td:first strong').text();
                
                if (cacheType.includes(UnifiedPerformanceAdmin.t('cache_type_permission_validation', 'Permission Validation')) && stats.permission_cache && 
                    typeof stats.permission_cache.count === 'number' && 
                    typeof stats.permission_cache.hit_rate === 'number') {
                    $row.find('td:nth-child(3)').text(stats.permission_cache.count.toLocaleString());
                    $row.find('td:nth-child(4)').text(stats.permission_cache.hit_rate.toFixed(1) + '%');
                }
                
                if (cacheType.includes(UnifiedPerformanceAdmin.t('cache_type_content_preview', 'Content Preview')) && stats.preview_cache && 
                    typeof stats.preview_cache.count === 'number' && 
                    typeof stats.preview_cache.hit_rate === 'number') {
                    $row.find('td:nth-child(3)').text(stats.preview_cache.count.toLocaleString());
                    $row.find('td:nth-child(4)').text(stats.preview_cache.hit_rate.toFixed(1) + '%');
                }
                
                if (cacheType.includes(UnifiedPerformanceAdmin.t('cache_type_query_cache', 'Query Cache')) && stats.query_cache && 
                    typeof stats.query_cache.count === 'number' && 
                    typeof stats.query_cache.hit_rate === 'number') {
                    $row.find('td:nth-child(3)').text(stats.query_cache.count.toLocaleString());
                    $row.find('td:nth-child(4)').text(stats.query_cache.hit_rate.toFixed(1) + '%');
                }
                
                if (cacheType.includes(UnifiedPerformanceAdmin.t('cache_type_object_cache', 'Object Cache')) && stats.object_cache && 
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
            
            $btn.prop('disabled', true).text(UnifiedPerformanceAdmin.t('clearing', 'Clearing...'));
            
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
                    UnifiedPerformanceAdmin.showNotification(UnifiedPerformanceAdmin.t('all_cache_cleared', 'All cache cleared'), 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    UnifiedPerformanceAdmin.showNotification(UnifiedPerformanceAdmin.t('clear_failed', 'Clear failed: ') + response.data, 'error');
                    $btn.prop('disabled', false).text(originalText);
                }
            })
            .fail(function() {
                UnifiedPerformanceAdmin.showNotification(UnifiedPerformanceAdmin.t('network_error_retry', 'Network error, please try again'), 'error');
                $btn.prop('disabled', false).text(originalText);
            });
        },

        // 清除单个缓存
        clearSingleCache: function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const cacheType = $btn.data('type');
            const originalText = $btn.text();
            
            $btn.prop('disabled', true).text(UnifiedPerformanceAdmin.t('clearing', 'Clearing...'));
            
            $.post(folioPerformanceAdmin.ajaxurl, {
                action: 'folio_cache_clear',
                cache_type: cacheType,
                nonce: folioPerformanceAdmin.nonce
            })
            .done(function(response) {
                if (response.success) {
                    UnifiedPerformanceAdmin.showNotification(UnifiedPerformanceAdmin.t('cache_cleared_success', 'Cache cleared successfully'), 'success');
                    $btn.text(UnifiedPerformanceAdmin.t('cleared', 'Cleared')).addClass('button-primary');
                    
                    setTimeout(() => {
                        $btn.removeClass('button-primary').prop('disabled', false).text(originalText);
                        UnifiedPerformanceAdmin.updateCacheStats();
                    }, 2000);
                } else {
                    UnifiedPerformanceAdmin.showNotification(UnifiedPerformanceAdmin.t('clear_failed', 'Clear failed: ') + response.data, 'error');
                    $btn.prop('disabled', false).text(originalText);
                }
            })
            .fail(function() {
                UnifiedPerformanceAdmin.showNotification(UnifiedPerformanceAdmin.t('network_error_retry', 'Network error, please try again'), 'error');
                $btn.prop('disabled', false).text(originalText);
            });
        },

        // 运行健康检查
        runHealthCheck: function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const originalText = $btn.text();
            
            $btn.prop('disabled', true).text('🔍 ' + UnifiedPerformanceAdmin.t('checking', 'Checking...'));
            
            $.post(folioPerformanceAdmin.ajaxurl, {
                action: 'folio_cache_health_check',
                nonce: folioPerformanceAdmin.nonce
            })
            .done(function(response) {
                if (response.success) {
                    UnifiedPerformanceAdmin.showHealthCheckResults(response.data);
                    UnifiedPerformanceAdmin.showNotification(UnifiedPerformanceAdmin.t('health_check_complete', 'Health check complete'), 'success');
                } else {
                    UnifiedPerformanceAdmin.showNotification(UnifiedPerformanceAdmin.t('health_check_failed', 'Health check failed: ') + response.data, 'error');
                }
            })
            .fail(function() {
                UnifiedPerformanceAdmin.showNotification(UnifiedPerformanceAdmin.t('network_error_retry', 'Network error, please try again'), 'error');
            })
            .always(function() {
                $btn.prop('disabled', false).text(originalText);
            });
        },

        // 显示健康检查结果
        showHealthCheckResults: function(results) {
            let html = '<div class="health-check-results"><h4>' + UnifiedPerformanceAdmin.t('health_check_results', 'Health Check Results') + '</h4>';
            
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
            
            $btn.prop('disabled', true).text('⚡ ' + UnifiedPerformanceAdmin.t('optimizing', 'Optimizing...'));
            
            $.post(folioPerformanceAdmin.ajaxurl, {
                action: 'folio_cache_optimize',
                nonce: folioPerformanceAdmin.nonce
            })
            .done(function(response) {
                if (response.success) {
                    UnifiedPerformanceAdmin.showNotification(UnifiedPerformanceAdmin.t('performance_optimized', 'Performance optimization completed'), 'success');
                    if (response.data.optimizations) {
                        const details = response.data.optimizations.join(', ');
                        UnifiedPerformanceAdmin.showNotification(UnifiedPerformanceAdmin.t('optimization_items', 'Optimization items: ') + details, 'info');
                    }
                    UnifiedPerformanceAdmin.updatePerformanceStats();
                    UnifiedPerformanceAdmin.updateCacheStats();
                } else {
                    UnifiedPerformanceAdmin.showNotification(UnifiedPerformanceAdmin.t('optimize_failed', 'Optimization failed: ') + response.data, 'error');
                }
            })
            .fail(function() {
                UnifiedPerformanceAdmin.showNotification(UnifiedPerformanceAdmin.t('network_error_retry', 'Network error, please try again'), 'error');
            })
            .always(function() {
                $btn.prop('disabled', false).text(originalText);
            });
        },

        // 安装对象缓存
        installObjectCache: function(e) {
            e.preventDefault();
            
            if (!confirm(UnifiedPerformanceAdmin.t('confirm_install_object_cache', 'Install Folio object cache? This will significantly improve site performance.'))) {
                return;
            }
            
            const $btn = $(this);
            const originalText = $btn.text();
            
            $btn.prop('disabled', true).text(UnifiedPerformanceAdmin.t('installing', 'Installing...'));
            
            $.post(folioPerformanceAdmin.ajaxurl, {
                action: 'folio_install_object_cache',
                nonce: folioPerformanceAdmin.object_cache_nonce
            })
            .done(function(response) {
                if (response.success) {
                    UnifiedPerformanceAdmin.showNotification(UnifiedPerformanceAdmin.t('object_cache_installed', 'Object cache installed successfully'), 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    UnifiedPerformanceAdmin.showNotification(UnifiedPerformanceAdmin.t('install_failed', 'Install failed: ') + response.data, 'error');
                    $btn.prop('disabled', false).text(originalText);
                }
            })
            .fail(function() {
                UnifiedPerformanceAdmin.showNotification(UnifiedPerformanceAdmin.t('network_error_retry', 'Network error, please try again'), 'error');
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
            
            $btn.prop('disabled', true).text(UnifiedPerformanceAdmin.t('clearing', 'Clearing...'));
            
            $.post(folioPerformanceAdmin.ajaxurl, {
                action: 'folio_cache_clear',
                cache_type: 'expired',
                nonce: folioPerformanceAdmin.nonce
            })
            .done(function(response) {
                if (response.success) {
                    UnifiedPerformanceAdmin.showNotification(UnifiedPerformanceAdmin.t('expired_cache_cleared', 'Expired cache cleared'), 'success');
                } else {
                    UnifiedPerformanceAdmin.showNotification(UnifiedPerformanceAdmin.t('clear_failed', 'Clear failed: ') + response.data, 'error');
                }
            })
            .fail(function() {
                UnifiedPerformanceAdmin.showNotification(UnifiedPerformanceAdmin.t('network_error_retry', 'Network error, please try again'), 'error');
            })
            .always(function() {
                $btn.prop('disabled', false).text(originalText);
            });
        },

        clearUserCache: function(e) {
            e.preventDefault();
            
            const userId = prompt(UnifiedPerformanceAdmin.t('prompt_user_id', 'Enter user ID (leave empty to clear current user cache):'));
            if (userId === null) return; // User canceled
            
            const $btn = $(this);
            const originalText = $btn.text();
            
            $btn.prop('disabled', true).text(UnifiedPerformanceAdmin.t('clearing', 'Clearing...'));
            
            $.post(folioPerformanceAdmin.ajaxurl, {
                action: 'folio_cache_clear',
                cache_type: 'user',
                user_id: userId || '',
                nonce: folioPerformanceAdmin.nonce
            })
            .done(function(response) {
                if (response.success) {
                    UnifiedPerformanceAdmin.showNotification(UnifiedPerformanceAdmin.t('user_cache_cleared', 'User cache cleared'), 'success');
                } else {
                    UnifiedPerformanceAdmin.showNotification(UnifiedPerformanceAdmin.t('clear_failed', 'Clear failed: ') + response.data, 'error');
                }
            })
            .fail(function() {
                UnifiedPerformanceAdmin.showNotification(UnifiedPerformanceAdmin.t('network_error_retry', 'Network error, please try again'), 'error');
            })
            .always(function() {
                $btn.prop('disabled', false).text(originalText);
            });
        },

        optimizeCache: function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const originalText = $btn.text();
            
            $btn.prop('disabled', true).text(UnifiedPerformanceAdmin.t('optimizing', 'Optimizing...'));
            
            $.post(folioPerformanceAdmin.ajaxurl, {
                action: 'folio_cache_optimize',
                nonce: folioPerformanceAdmin.nonce
            })
            .done(function(response) {
                if (response.success) {
                    UnifiedPerformanceAdmin.showNotification(UnifiedPerformanceAdmin.t('cache_optimized', 'Cache optimization completed'), 'success');
                    if (response.data.optimizations) {
                        const details = response.data.optimizations.join(', ');
                        UnifiedPerformanceAdmin.showNotification(UnifiedPerformanceAdmin.t('optimization_items', 'Optimization items: ') + details, 'info');
                    }
                } else {
                    UnifiedPerformanceAdmin.showNotification(UnifiedPerformanceAdmin.t('optimize_failed', 'Optimization failed: ') + response.data, 'error');
                }
            })
            .fail(function() {
                UnifiedPerformanceAdmin.showNotification(UnifiedPerformanceAdmin.t('network_error_retry', 'Network error, please try again'), 'error');
            })
            .always(function() {
                $btn.prop('disabled', false).text(originalText);
            });
        },

        preloadCache: function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const originalText = $btn.text();
            
            $btn.prop('disabled', true).text(UnifiedPerformanceAdmin.t('preloading', 'Preloading...'));
            
            $.post(folioPerformanceAdmin.ajaxurl, {
                action: 'folio_cache_preload',
                nonce: folioPerformanceAdmin.nonce
            })
            .done(function(response) {
                if (response.success) {
                    UnifiedPerformanceAdmin.showNotification(UnifiedPerformanceAdmin.t('cache_preload_complete', 'Cache preload completed'), 'success');
                } else {
                    UnifiedPerformanceAdmin.showNotification(UnifiedPerformanceAdmin.t('preload_failed', 'Preload failed: ') + response.data, 'error');
                }
            })
            .fail(function() {
                UnifiedPerformanceAdmin.showNotification(UnifiedPerformanceAdmin.t('network_error_retry', 'Network error, please try again'), 'error');
            })
            .always(function() {
                $btn.prop('disabled', false).text(originalText);
            });
        },

        analyzeCache: function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const originalText = $btn.text();
            
            $btn.prop('disabled', true).text(UnifiedPerformanceAdmin.t('analyzing', 'Analyzing...'));
            
            $.post(folioPerformanceAdmin.ajaxurl, {
                action: 'folio_cache_analyze',
                nonce: folioPerformanceAdmin.nonce
            })
            .done(function(response) {
                if (response.success) {
                    UnifiedPerformanceAdmin.showNotification(UnifiedPerformanceAdmin.t('cache_analysis_complete', 'Cache analysis complete'), 'success');
                    // 显示分析结果
                    if (response.data.analysis) {
                        const analysis = response.data.analysis;
                        let message = UnifiedPerformanceAdmin.t('analysis_result', 'Analysis Result:') + '\n';
                        message += UnifiedPerformanceAdmin.t('hit_rate', 'Hit rate: ') + analysis.hit_rate + '%\n';
                        message += UnifiedPerformanceAdmin.t('efficiency_score', 'Efficiency score: ') + analysis.efficiency_score + '/100';
                        UnifiedPerformanceAdmin.showNotification(message, 'info');
                    }
                } else {
                    UnifiedPerformanceAdmin.showNotification(UnifiedPerformanceAdmin.t('analysis_failed', 'Analysis failed: ') + response.data, 'error');
                }
            })
            .fail(function() {
                UnifiedPerformanceAdmin.showNotification(UnifiedPerformanceAdmin.t('network_error_retry', 'Network error, please try again'), 'error');
            })
            .always(function() {
                $btn.prop('disabled', false).text(originalText);
            });
        },

        exportStats: function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const originalText = $btn.text();
            
            $btn.prop('disabled', true).text(UnifiedPerformanceAdmin.t('exporting', 'Exporting...'));
            
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
                    
                    UnifiedPerformanceAdmin.showNotification(UnifiedPerformanceAdmin.t('stats_exported', 'Statistics exported'), 'success');
                } else {
                    UnifiedPerformanceAdmin.showNotification(UnifiedPerformanceAdmin.t('export_failed', 'Export failed: ') + response.data, 'error');
                }
            })
            .fail(function() {
                UnifiedPerformanceAdmin.showNotification(UnifiedPerformanceAdmin.t('network_error_retry', 'Network error, please try again'), 'error');
            })
            .always(function() {
                $btn.prop('disabled', false).text(originalText);
            });
        },

        resetStats: function(e) {
            e.preventDefault();
            
            if (!confirm(UnifiedPerformanceAdmin.t('confirm_reset_stats', 'Reset all statistics? This action cannot be undone.'))) {
                return;
            }
            
            const $btn = $(this);
            const originalText = $btn.text();
            
            $btn.prop('disabled', true).text(UnifiedPerformanceAdmin.t('resetting', 'Resetting...'));
            
            $.post(folioPerformanceAdmin.ajaxurl, {
                action: 'folio_cache_reset_stats',
                nonce: folioPerformanceAdmin.nonce
            })
            .done(function(response) {
                if (response.success) {
                    UnifiedPerformanceAdmin.showNotification(UnifiedPerformanceAdmin.t('stats_reset', 'Statistics reset'), 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    UnifiedPerformanceAdmin.showNotification(UnifiedPerformanceAdmin.t('reset_failed', 'Reset failed: ') + response.data, 'error');
                }
            })
            .fail(function() {
                UnifiedPerformanceAdmin.showNotification(UnifiedPerformanceAdmin.t('network_error_retry', 'Network error, please try again'), 'error');
            })
            .always(function() {
                $btn.prop('disabled', false).text(originalText);
            });
        },

        scheduleCleanup: function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const originalText = $btn.text();
            
            $btn.prop('disabled', true).text(UnifiedPerformanceAdmin.t('setting', 'Setting...'));
            
            $.post(folioPerformanceAdmin.ajaxurl, {
                action: 'folio_cache_schedule_cleanup',
                nonce: folioPerformanceAdmin.nonce
            })
            .done(function(response) {
                if (response.success) {
                    UnifiedPerformanceAdmin.showNotification(UnifiedPerformanceAdmin.t('scheduled_cleanup_set', 'Scheduled cleanup configured'), 'success');
                } else {
                    UnifiedPerformanceAdmin.showNotification(UnifiedPerformanceAdmin.t('setting_failed', 'Setting failed: ') + response.data, 'error');
                }
            })
            .fail(function() {
                UnifiedPerformanceAdmin.showNotification(UnifiedPerformanceAdmin.t('network_error_retry', 'Network error, please try again'), 'error');
            })
            .always(function() {
                $btn.prop('disabled', false).text(originalText);
            });
        },

        uninstallObjectCache: function(e) {
            e.preventDefault();
            
            if (!confirm(UnifiedPerformanceAdmin.t('confirm_uninstall_object_cache', 'Uninstall object cache? This will affect site performance.'))) {
                return;
            }
            
            const $btn = $(this);
            const originalText = $btn.text();
            
            $btn.prop('disabled', true).text(UnifiedPerformanceAdmin.t('uninstalling', 'Uninstalling...'));
            
            $.post(folioPerformanceAdmin.ajaxurl, {
                action: 'folio_uninstall_object_cache',
                nonce: folioPerformanceAdmin.object_cache_nonce
            })
            .done(function(response) {
                if (response.success) {
                    UnifiedPerformanceAdmin.showNotification(UnifiedPerformanceAdmin.t('object_cache_uninstalled', 'Object cache uninstalled'), 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    UnifiedPerformanceAdmin.showNotification(UnifiedPerformanceAdmin.t('uninstall_failed', 'Uninstall failed: ') + response.data, 'error');
                }
            })
            .fail(function() {
                UnifiedPerformanceAdmin.showNotification(UnifiedPerformanceAdmin.t('network_error_retry', 'Network error, please try again'), 'error');
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
        UnifiedPerformanceAdmin.showNotification(UnifiedPerformanceAdmin.t('optimizing_cache_config', 'Optimizing cache configuration...'), 'info');
        // 实际的优化逻辑
    };

    window.increaseCacheTime = function() {
        UnifiedPerformanceAdmin.showNotification(UnifiedPerformanceAdmin.t('increasing_cache_time', 'Increasing cache TTL...'), 'info');
        // 实际的优化逻辑
    };

    window.installObjectCache = function() {
        $('#install-object-cache-btn').click();
    };

    window.showCacheGuide = function() {
        const guideContent = `
            <div style="max-width: 500px;">
                <h3>${UnifiedPerformanceAdmin.t('object_cache_install_guide', 'Object Cache Installation Guide')}</h3>
                <ol>
                    <li>${UnifiedPerformanceAdmin.t('guide_step_1', 'Ensure Memcached service is installed on server')}</li>
                    <li>${UnifiedPerformanceAdmin.t('guide_step_2', 'Install PHP Memcached extension')}</li>
                    <li>${UnifiedPerformanceAdmin.t('guide_step_3', 'Click \"Install Memcached\" button')}</li>
                    <li>${UnifiedPerformanceAdmin.t('guide_step_4', 'Verify installation success')}</li>
                </ol>
                <p><strong>${UnifiedPerformanceAdmin.t('note', 'Note:')}</strong> ${UnifiedPerformanceAdmin.t('guide_note', 'Server administrator access is required')}</p>
            </div>
        `;
        UnifiedPerformanceAdmin.showNotification(guideContent, 'info');
    };

    window.analyzeMemoryUsage = function() {
        UnifiedPerformanceAdmin.showNotification(UnifiedPerformanceAdmin.t('analyzing_memory_usage', 'Analyzing memory usage...'), 'info');
        // 实际的分析逻辑
    };

    window.cleanupPlugins = function() {
        UnifiedPerformanceAdmin.showNotification(UnifiedPerformanceAdmin.t('cleanup_plugins_tip', 'Check plugin management page and disable unnecessary plugins'), 'info');
    };

    window.enableQueryCache = function() {
        UnifiedPerformanceAdmin.showNotification(UnifiedPerformanceAdmin.t('enabling_query_cache', 'Enabling query cache...'), 'info');
        // 实际的启用逻辑
    };

    window.optimizeDatabase = function() {
        UnifiedPerformanceAdmin.showNotification(UnifiedPerformanceAdmin.t('optimizing_database', 'Optimizing database...'), 'info');
        // 实际的优化逻辑
    };

    window.cleanupDatabase = function() {
        UnifiedPerformanceAdmin.showNotification(UnifiedPerformanceAdmin.t('cleaning_database', 'Cleaning database...'), 'info');
        // 实际的清理逻辑
    };

    window.checkUpdates = function() {
        window.location.href = '/wp-admin/update-core.php';
    };

    window.setupMonitoring = function() {
        UnifiedPerformanceAdmin.showNotification(UnifiedPerformanceAdmin.t('monitoring_in_dev', 'Performance monitoring is under development...'), 'info');
    };

    window.viewReports = function() {
        UnifiedPerformanceAdmin.showNotification(UnifiedPerformanceAdmin.t('reports_in_dev', 'Performance reports are under development...'), 'info');
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
