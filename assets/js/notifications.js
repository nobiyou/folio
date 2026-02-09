/**
 * 会员通知系统前端脚本
 */
(function($) {
    'use strict';

    var FolioNotifications = {
        // 防止重复请求的标志
        isLoading: false,
        cachedNotifications: null,
        
        init: function() {
            // 检查必要的配置
            if (typeof folioNotifications === 'undefined') {
                console.error('通知系统配置未加载');
                return;
            }
            
            this.bindEvents();
            
            // 如果在通知中心页面，只更新角标，不自动显示弹窗
            if ($('.notifications-page').length > 0) {
                // 仍然需要检查通知以更新角标（因为用户可能在其他页面操作了通知）
                this.checkForNotifications(true);
                return;
            }
            
            // 页面加载时强制刷新，不使用缓存
            this.checkForNotifications(true);
        },

        bindEvents: function() {
            // 关闭通知弹窗
            $(document).on('click', '.notification-close, .notification-overlay', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $('#folio-notification-popup').fadeOut();
            });

            // 标记通知为已读
            $(document).on('click', '.notification-item.unread', function(e) {
                e.preventDefault();
                var notificationId = $(this).data('id');
                if (notificationId) {
                FolioNotifications.markAsRead(notificationId, $(this));
                }
            });

            // 通知铃铛点击
            $(document).on('click', '.notification-bell', function(e) {
                e.preventDefault();
                e.stopPropagation();
                FolioNotifications.showNotifications(false, true); // 强制刷新，不使用缓存
            });
            
            // ESC 键关闭弹窗
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && $('#folio-notification-popup').is(':visible')) {
                    $('#folio-notification-popup').fadeOut();
                }
            });
            
            // 全部标记为已读
            $(document).on('click', '#mark-all-read-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                FolioNotifications.markAllAsRead();
            });
        },

        checkForNotifications: function(forceRefresh) {
            // 检查配置
            if (typeof folioNotifications === 'undefined') {
                return;
            }
            
            // 如果强制刷新，清除缓存
            if (forceRefresh) {
                this.cachedNotifications = null;
            }
            
            // 如果正在加载，避免重复请求
            if (this.isLoading) {
                return;
            }
            
            this.isLoading = true;
            
            // 检查是否有未读通知（支持未登录用户）
            $.ajax({
                url: folioNotifications.ajaxurl,
                type: 'POST',
                data: {
                    action: 'folio_get_notifications',
                    nonce: folioNotifications.nonce,
                    limit: 10
                },
                success: function(response) {
                    FolioNotifications.isLoading = false;
                    
                    if (response.success) {
                        // 缓存通知数据
                        FolioNotifications.cachedNotifications = response.data;
                        
                        if (response.data && response.data.length > 0) {
                        var unreadCount = 0;
                        response.data.forEach(function(notification) {
                            if (notification.is_read == 0) {
                                unreadCount++;
                            }
                        });

                        if (unreadCount > 0) {
                            FolioNotifications.updateNotificationBadge(unreadCount);
                            
                                // 如果有紧急通知（会员到期），自动显示（但不在通知中心页面显示）
                                if ($('.notifications-page').length === 0) {
                            var hasUrgent = response.data.some(function(notification) {
                                return notification.type === 'membership_expiry' && notification.is_read == 0;
                            });
                            
                            if (hasUrgent) {
                                setTimeout(function() {
                                            FolioNotifications.showNotifications(true); // 传入true表示使用缓存
                                }, 2000);
                            }
                        }
                            } else {
                                FolioNotifications.updateNotificationBadge(0);
                            }
                        } else {
                            FolioNotifications.updateNotificationBadge(0);
                        }
                    }
                },
                error: function(xhr, status, error) {
                    FolioNotifications.isLoading = false;
                    console.error('检查通知失败:', error);
                    // 即使 AJAX 失败，也尝试显示通知铃铛（未登录用户）
                    FolioNotifications.updateNotificationBadge(0);
                }
            });
        },

        showNotifications: function(useCache, forceRefresh) {
            // 检查弹窗是否存在
            var popup = $('#folio-notification-popup');
            if (popup.length === 0) {
                console.error('通知弹窗不存在');
                return;
            }
            
            // 如果强制刷新，清除缓存
            if (forceRefresh) {
                this.cachedNotifications = null;
                useCache = false;
            }
            
            // 如果使用缓存且缓存存在，直接使用缓存数据
            if (useCache && this.cachedNotifications !== null) {
                this.renderNotifications(this.cachedNotifications);
                popup.fadeIn();
                return;
            }
            
            // 如果正在加载，避免重复请求
            if (this.isLoading) {
                return;
            }
            
            this.isLoading = true;
            
            $.ajax({
                url: folioNotifications.ajaxurl,
                type: 'POST',
                data: {
                    action: 'folio_get_notifications',
                    nonce: folioNotifications.nonce,
                    limit: 20  // 显示弹窗时获取更多通知
                },
                success: function(response) {
                    FolioNotifications.isLoading = false;
                    
                    if (response.success) {
                        // 更新缓存
                        FolioNotifications.cachedNotifications = response.data;
                        FolioNotifications.renderNotifications(response.data);
                        popup.fadeIn();
                    } else {
                        console.error('获取通知失败:', response.data);
                    }
                },
                error: function(xhr, status, error) {
                    FolioNotifications.isLoading = false;
                    console.error('AJAX 错误:', error);
                }
            });
        },

        renderNotifications: function(notifications) {
            var html = '';
            var hasUnread = false;
            
            if (notifications.length === 0) {
                html = '<div class="no-notifications">暂无通知</div>';
                $('#folio-notification-popup #mark-all-read-btn').hide();
            } else {
                notifications.forEach(function(notification) {
                    var readClass = notification.is_read == 0 ? 'unread' : 'read';
                    if (notification.is_read == 0) {
                        hasUnread = true;
                    }
                    var typeClass = 'notification-' + notification.type;
                    var timeAgo = FolioNotifications.timeAgo(notification.created_at);
                    
                    html += '<div class="notification-item ' + readClass + ' ' + typeClass + '" data-id="' + notification.id + '">';
                    html += '<div class="notification-title">' + FolioNotifications.escapeHtml(notification.title) + '</div>';
                    html += '<div class="notification-message">' + FolioNotifications.escapeHtml(notification.message) + '</div>';
                    html += '<div class="notification-time">' + timeAgo + '</div>';
                    html += '</div>';
                });
                
                // 显示/隐藏"全部已读"按钮（只更新弹窗内的按钮）
                if (hasUnread) {
                    $('#folio-notification-popup #mark-all-read-btn').show();
                } else {
                    $('#folio-notification-popup #mark-all-read-btn').hide();
                }
            }
            
            // 只更新弹窗内的通知列表，避免与页面上的通知列表冲突
            $('#folio-notification-popup #notification-list').html(html);
        },
        
        markAllAsRead: function() {
            var self = this;
            $.ajax({
                url: folioNotifications.ajaxurl,
                type: 'POST',
                data: {
                    action: 'folio_mark_all_read',
                    nonce: folioNotifications.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // 更新所有通知为已读样式
                        $('.notification-item').removeClass('unread').addClass('read');
                        FolioNotifications.updateNotificationBadge(0);
                        $('#mark-all-read-btn').hide();
                        
                        // 更新缓存中的通知状态（如果缓存存在）
                        if (self.cachedNotifications) {
                            self.cachedNotifications.forEach(function(notification) {
                                notification.is_read = 1;
                            });
                        }
                        
                        // 强制刷新，重新获取最新数据
                        self.cachedNotifications = null;
                        self.checkForNotifications(true);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('标记全部已读失败:', error);
                }
            });
        },
        
        escapeHtml: function(text) {
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        },

        markAsRead: function(notificationId, element) {
            var self = this;
            $.ajax({
                url: folioNotifications.ajaxurl,
                type: 'POST',
                data: {
                    action: 'folio_mark_notification_read',
                    notification_id: notificationId,
                    nonce: folioNotifications.nonce
                },
                success: function(response) {
                    if (response.success) {
                        element.removeClass('unread').addClass('read');
                        FolioNotifications.updateNotificationBadge();
                        
                        // 更新缓存中对应通知的状态（如果缓存存在）
                        if (self.cachedNotifications) {
                            self.cachedNotifications.forEach(function(notification) {
                                if (notification.id == notificationId) {
                                    notification.is_read = 1;
                                }
                            });
                        }
                        
                        // 强制刷新，重新获取最新数据
                        self.cachedNotifications = null;
                        self.checkForNotifications(true);
                    }
                }
            });
        },

        updateNotificationBadge: function(count) {
            var badge = $('.notification-badge');
            
            if (count === undefined) {
                // 重新计算未读数量
                count = $('.notification-item.unread').length;
            }
            
            if (count > 0) {
                if (badge.length === 0) {
                    $('.notification-bell').append('<span class="notification-badge">' + count + '</span>');
                } else {
                    badge.text(count);
                }
                badge.show();
            } else {
                badge.hide();
            }
        },

        timeAgo: function(dateString) {
            var date = new Date(dateString);
            var now = new Date();
            var diff = now - date;
            var seconds = Math.floor(diff / 1000);
            var minutes = Math.floor(seconds / 60);
            var hours = Math.floor(minutes / 60);
            var days = Math.floor(hours / 24);

            if (days > 0) {
                return days + '天前';
            } else if (hours > 0) {
                return hours + '小时前';
            } else if (minutes > 0) {
                return minutes + '分钟前';
            } else {
                return '刚刚';
            }
        }
    };

    // 初始化
    $(document).ready(function() {
        FolioNotifications.init();
    });

    // 全局访问
    window.FolioNotifications = FolioNotifications;

})(jQuery);