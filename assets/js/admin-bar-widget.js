jQuery(document).ready(function($) {
    'use strict';
    
    let miniChart = null;
    let expandedChart = null;
    let expandedWidget = null;
    let currentSection = 'overview';
    
    // Initialize mini chart in admin bar
    function initMiniChart() {
        const ctx = document.getElementById('bfa-admin-bar-chart');
        if (!ctx || !window.Chart) return;
        
        const chartData = window.bfaAdminBarData ? window.bfaAdminBarData.chartData : null;
        if (!chartData) return;
        
        miniChart = new Chart(ctx, {
            type: 'line',
            data: chartData,
            options: {
                responsive: false,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { enabled: false }
                },
                scales: {
                    x: { display: false },
                    y: { display: false }
                },
                elements: {
                    point: { radius: 0 }
                },
                interaction: {
                    intersect: false
                }
            }
        });
    }
    
    // Create the expanded widget if it doesn't exist
    function ensureExpandedWidget() {
        if (!$('#bfa-expanded-widget').length) {
            $('body').append('<div id="bfa-expanded-widget" class="bfa-expanded-widget"></div>');
        }
    }
    
    // Show expanded widget with specific section
    function showExpandedWidget(section = 'overview') {
        currentSection = section;
        ensureExpandedWidget();
        expandedWidget = $('#bfa-expanded-widget');
        
        // Update content based on section
        updateExpandedContent(section);
        
        // Show widget with animation
        expandedWidget.show();
        setTimeout(() => {
            expandedWidget.addClass('bfa-show');
        }, 10);
        
        // Initialize chart if needed
        if (!expandedChart && section === 'overview') {
            setTimeout(initExpandedChart, 100);
        }
    }
    
    // Update expanded widget content based on section
    function updateExpandedContent(section) {
        let content = '';
        const stats = getLatestStats();
        
        switch (section) {
            case 'views':
                content = getViewsContent(stats);
                break;
            case 'visitors':
                content = getVisitorsContent(stats);
                break;
            case 'plays':
                content = getPlaysContent(stats);
                break;
            case 'change':
                content = getChangeContent(stats); // Back to growth/change content
                break;
            case 'live':
                content = getLiveTrafficContent(stats); // Live traffic on separate case
                break;
            default:
                content = getOverviewContent(stats);
        }
        
        expandedWidget.html(content);
        
        // If showing live traffic, load the data
        if (section === 'live') {
            loadLiveTraffic();
        }
    }
    
    // Get latest stats from page or default values
    function getLatestStats() {
        // Try to get stats from the admin bar itself
        const viewsText = $('.bfa-views-box .bfa-stat-number').text();
        const visitorsText = $('.bfa-visitors-box .bfa-stat-number').text();
        const playsText = $('.bfa-plays-box .bfa-stat-number').text();
        const changeText = $('.bfa-change-box .bfa-stat-number').text();
        
        return {
            views: viewsText || '0',
            visitors: visitorsText || '0',
            plays: playsText || '0',
            change: changeText || '0%'
        };
    }
    
    // Content generators for different sections
    function getOverviewContent(stats) {
        return `
            <div class="bfa-expanded-header">
                <h3>📊 Analytics Overview</h3>
                <div class="bfa-live-indicator">
                    <span class="bfa-pulse-dot"></span>
                    <span>Live Dashboard</span>
                </div>
            </div>
            <div class="bfa-expanded-stats">
                <div class="bfa-expanded-stat">
                    <div class="bfa-expanded-stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">👁️</div>
                    <div class="bfa-expanded-stat-content">
                        <div class="bfa-expanded-stat-number">${stats.views}</div>
                        <div class="bfa-expanded-stat-label">Page Views Today</div>
                        <div class="bfa-expanded-stat-sublabel">${stats.visitors} unique visitors</div>
                    </div>
                </div>
                <div class="bfa-expanded-stat">
                    <div class="bfa-expanded-stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">🎵</div>
                    <div class="bfa-expanded-stat-content">
                        <div class="bfa-expanded-stat-number">${stats.plays}</div>
                        <div class="bfa-expanded-stat-label">Music Plays Today</div>
                        <div class="bfa-expanded-stat-sublabel">Tracks listened to</div>
                    </div>
                </div>
            </div>
            <div class="bfa-expanded-chart">
                <h4>Last 7 Days</h4>
                <canvas id="bfa-expanded-chart" width="340" height="100"></canvas>
            </div>
            <div class="bfa-expanded-footer">
                <a href="${getAnalyticsUrl()}" class="bfa-expanded-button">
                    View Full Dashboard →
                </a>
            </div>
        `;
    }
    
    function getViewsContent(stats) {
        return `
            <div class="bfa-expanded-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <h3>👁️ Page Views</h3>
                <div class="bfa-live-indicator">
                    <span class="bfa-pulse-dot"></span>
                    <span>Real-time tracking</span>
                </div>
            </div>
            <div class="bfa-expanded-stats">
                <div class="bfa-expanded-stat">
                    <div class="bfa-expanded-stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">👁️</div>
                    <div class="bfa-expanded-stat-content">
                        <div class="bfa-expanded-stat-number">${stats.views}</div>
                        <div class="bfa-expanded-stat-label">Total Views Today</div>
                        <div class="bfa-expanded-stat-sublabel">Across all content</div>
                    </div>
                </div>
            </div>
            <div class="bfa-expanded-chart">
                <h4>Page Views Trend</h4>
                <canvas id="bfa-expanded-chart" width="340" height="100"></canvas>
            </div>
            <div class="bfa-expanded-footer">
                <a href="${getAnalyticsUrl()}" class="bfa-expanded-button">
                    View Detailed Analytics →
                </a>
            </div>
        `;
    }
    
    function getVisitorsContent(stats) {
        return `
            <div class="bfa-expanded-header" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <h3>👥 Visitors</h3>
                <div class="bfa-live-indicator">
                    <span class="bfa-pulse-dot"></span>
                    <span>Unique tracking</span>
                </div>
            </div>
            <div class="bfa-expanded-stats">
                <div class="bfa-expanded-stat">
                    <div class="bfa-expanded-stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">👥</div>
                    <div class="bfa-expanded-stat-content">
                        <div class="bfa-expanded-stat-number">${stats.visitors}</div>
                        <div class="bfa-expanded-stat-label">Unique Visitors Today</div>
                        <div class="bfa-expanded-stat-sublabel">First-time and returning</div>
                    </div>
                </div>
            </div>
            <div class="bfa-expanded-footer">
                <a href="${getAnalyticsUrl()}" class="bfa-expanded-button">
                    View Visitor Analytics →
                </a>
            </div>
        `;
    }
    
    function getPlaysContent(stats) {
        return `
            <div class="bfa-expanded-header" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                <h3>🎵 Music Plays</h3>
                <div class="bfa-live-indicator">
                    <span class="bfa-pulse-dot"></span>
                    <span>Track engagement</span>
                </div>
            </div>
            <div class="bfa-expanded-stats">
                <div class="bfa-expanded-stat">
                    <div class="bfa-expanded-stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">🎵</div>
                    <div class="bfa-expanded-stat-content">
                        <div class="bfa-expanded-stat-number">${stats.plays}</div>
                        <div class="bfa-expanded-stat-label">Music Plays Today</div>
                        <div class="bfa-expanded-stat-sublabel">All tracks combined</div>
                    </div>
                </div>
            </div>
            <div class="bfa-expanded-footer">
                <a href="${getPlayAnalyticsUrl()}" class="bfa-expanded-button">
                    View Play Analytics →
                </a>
            </div>
        `;
    }
    
    function getChangeContent(stats) {
        return `
            <div class="bfa-expanded-header" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                <h3>📈 Growth Trend</h3>
                <div class="bfa-live-indicator">
                    <span class="bfa-pulse-dot"></span>
                    <span>Performance tracking</span>
                </div>
            </div>
            <div class="bfa-expanded-stats">
                <div class="bfa-expanded-stat">
                    <div class="bfa-expanded-stat-icon" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">📈</div>
                    <div class="bfa-expanded-stat-content">
                        <div class="bfa-expanded-stat-number">${stats.change}</div>
                        <div class="bfa-expanded-stat-label">Growth vs Yesterday</div>
                        <div class="bfa-expanded-stat-sublabel">Based on page views</div>
                    </div>
                </div>
            </div>
            <div class="bfa-expanded-chart">
                <h4>7-Day Comparison</h4>
                <canvas id="bfa-expanded-chart" width="340" height="100"></canvas>
            </div>
            <div class="bfa-expanded-footer">
                <a href="${getAnalyticsUrl()}" class="bfa-expanded-button">
                    View Growth Reports →
                </a>
            </div>
        `;
    }
    
    // New function for live traffic content
    function getLiveTrafficContent(stats) {
        return `
            <div class="bfa-expanded-header" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                <h3>🔴 Live Traffic</h3>
                <div class="bfa-live-indicator">
                    <span class="bfa-pulse-dot"></span>
                    <span>Real-time activity</span>
                </div>
            </div>
            <div class="bfa-live-traffic-container">
                <div class="bfa-live-stats">
                    <div class="bfa-live-stat">
                        <span class="bfa-live-number" id="bfa-active-users">0</span>
                        <span class="bfa-live-label">Active Now</span>
                    </div>
                    <div class="bfa-live-stat">
                        <span class="bfa-live-number">${stats.views}</span>
                        <span class="bfa-live-label">Views Today</span>
                    </div>
                </div>
                <div class="bfa-live-traffic-list">
                    <h4>Recent Activity</h4>
                    <div id="bfa-live-traffic-items">
                        <div class="bfa-loading">Loading live data...</div>
                    </div>
                </div>
            </div>
            <div class="bfa-expanded-footer">
                <a href="${getAnalyticsUrl()}" class="bfa-expanded-button">
                    View Full Analytics →
                </a>
            </div>
        `;
    }
    
    // Load live traffic data
    function loadLiveTraffic() {
        if (window.bfaAdminBarData && window.bfaAdminBarData.apiUrl) {
            // Get active users count
            $.get({
                url: window.bfaAdminBarData.apiUrl + 'admin/quick-stats',
                headers: {
                    'X-WP-Nonce': window.bfaAdminBarData.nonce
                },
                success: function(data) {
                    $('#bfa-active-users').text(data.active_users || 0);
                }
            });
            
            // Get recent activity
            $.get({
                url: window.bfaAdminBarData.apiUrl + 'admin/recent-activity',
                headers: {
                    'X-WP-Nonce': window.bfaAdminBarData.nonce
                },
                success: function(data) {
                    let html = '';
                    if (data && data.length > 0) {
                        data.forEach(function(item) {
                            const timeAgo = getTimeAgo(new Date(item.timestamp));
                            html += `
                                <div class="bfa-traffic-item">
                                    <div class="bfa-traffic-time">${timeAgo}</div>
                                    <div class="bfa-traffic-info">
                                        <div class="bfa-traffic-page">${item.page_title || 'Unknown Page'}</div>
                                        <div class="bfa-traffic-meta">${item.visitor_id ? '👤 ' + item.visitor_id.substr(0, 8) : ''}</div>
                                    </div>
                                </div>
                            `;
                        });
                    } else {
                        html = '<div class="bfa-no-activity">No recent activity</div>';
                    }
                    $('#bfa-live-traffic-items').html(html);
                },
                error: function() {
                    $('#bfa-live-traffic-items').html('<div class="bfa-no-activity">Unable to load live data</div>');
                }
            });
        }
    }
    
    // Helper function to get relative time
    function getTimeAgo(date) {
        const seconds = Math.floor((new Date() - date) / 1000);
        if (seconds < 60) return 'Just now';
        const minutes = Math.floor(seconds / 60);
        if (minutes < 60) return minutes + 'm ago';
        const hours = Math.floor(minutes / 60);
        if (hours < 24) return hours + 'h ago';
        return Math.floor(hours / 24) + 'd ago';
    }
    
    // Helper functions
    function getAnalyticsUrl() {
        return window.bfaAdminBarData && window.bfaAdminBarData.adminUrl ? 
               window.bfaAdminBarData.adminUrl + 'admin.php?page=bandfront-analytics' : 
               '/wp-admin/admin.php?page=bandfront-analytics';
    }
    
    function getPlayAnalyticsUrl() {
        return window.bfaAdminBarData && window.bfaAdminBarData.adminUrl ? 
               window.bfaAdminBarData.adminUrl + 'admin.php?page=bandfront-play-analytics' : 
               '/wp-admin/admin.php?page=bandfront-play-analytics';
    }
    
    // Hide expanded widget
    function hideExpandedWidget() {
        if (expandedWidget && expandedWidget.hasClass('bfa-show')) {
            expandedWidget.removeClass('bfa-show');
            setTimeout(() => {
                expandedWidget.hide();
            }, 300);
        }
    }
    
    // Initialize expanded chart
    function initExpandedChart() {
        const ctx = document.getElementById('bfa-expanded-chart');
        if (!ctx || !window.Chart) return;
        
        const chartData = window.bfaAdminBarData ? window.bfaAdminBarData.chartData : null;
        if (!chartData) return;
        
        if (expandedChart) {
            expandedChart.destroy();
        }
        
        expandedChart = new Chart(ctx, {
            type: 'line',
            data: chartData,
            options: {
                responsive: false,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(0,0,0,0.8)',
                        titleColor: 'white',
                        bodyColor: 'white',
                        cornerRadius: 6,
                        displayColors: false
                    }
                },
                scales: {
                    x: {
                        display: true,
                        grid: { display: false },
                        ticks: { 
                            color: '#666',
                            font: { size: 11 }
                        }
                    },
                    y: {
                        display: true,
                        grid: { 
                            color: 'rgba(0,0,0,0.05)',
                            borderDash: [2, 2]
                        },
                        ticks: { 
                            color: '#666',
                            font: { size: 11 }
                        }
                    }
                },
                elements: {
                    point: { 
                        radius: 3,
                        hoverRadius: 6,
                        backgroundColor: '#667eea',
                        borderColor: 'white',
                        borderWidth: 2
                    }
                }
            }
        });
    }
    
    // Event handlers - make clicking more reliable
    
    // Use direct click handler on stat boxes for better reliability
    $(document).on('click', '.bfa-stat-box', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const $this = $(this);
        const section = $this.hasClass('bfa-views-box') ? 'views' :
                       $this.hasClass('bfa-visitors-box') ? 'visitors' :
                       $this.hasClass('bfa-plays-box') ? 'plays' :
                       $this.hasClass('bfa-change-box') ? 'change' : 'overview';
        
        showExpandedWidget(section);
    });
    
    // Click on brand or chart areas shows overview
    $(document).on('click', '.bfa-bar-brand', function(e) {
        e.preventDefault();
        e.stopPropagation();
        showExpandedWidget('overview');
    });
    
    // Click on mini chart shows live traffic
    $(document).on('click', '.bfa-mini-chart-container', function(e) {
        e.preventDefault();
        e.stopPropagation();
        showExpandedWidget('live'); // Show live traffic when clicking the chart
    });
    
    // Prevent bubbling on the main analytics bar
    $(document).on('click', '.bfa-admin-bar-analytics', function(e) {
        // Only process if not clicking on a child element
        if (e.target === this) {
            e.preventDefault();
            e.stopPropagation();
            showExpandedWidget('overview');
        }
    });
    
    // Prevent the default WordPress admin bar behavior
    $(document).on('click', '#wp-admin-bar-bfa-analytics-bar > a', function(e) {
        e.preventDefault();
        e.stopPropagation();
        return false;
    });
    
    // Close expanded widget when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.bfa-analytics-main-bar, .bfa-expanded-widget').length) {
            hideExpandedWidget();
        }
    });
    
    // Close on escape key
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            hideExpandedWidget();
        }
    });
    
    // Update stats periodically
    function updateStats() {
        if (window.bfaAdminBarData && window.bfaAdminBarData.apiUrl) {
            $.get({
                url: window.bfaAdminBarData.apiUrl + 'admin/quick-stats',
                headers: {
                    'X-WP-Nonce': window.bfaAdminBarData.nonce
                },
                success: function(data) {
                    // Update admin bar numbers
                    $('.bfa-views-box .bfa-stat-number').text(formatNumber(data.today_views));
                    $('.bfa-visitors-box .bfa-stat-number').text(formatNumber(data.today_visitors));
                    $('.bfa-plays-box .bfa-stat-number').text(formatNumber(data.today_plays || 0));
                    
                    // Update change indicator
                    if (data.yesterday_views > 0) {
                        const change = Math.round(((data.today_views - data.yesterday_views) / data.yesterday_views) * 100 * 10) / 10;
                        const changeText = (change >= 0 ? '+' : '') + change + '%';
                        $('.bfa-change-box .bfa-stat-number').text(changeText);
                        
                        // Update change box styling
                        const changeBox = $('.bfa-change-box');
                        changeBox.removeClass('bfa-change-positive bfa-change-negative');
                        changeBox.addClass(change >= 0 ? 'bfa-change-positive' : 'bfa-change-negative');
                        
                        // Update icon
                        changeBox.find('.bfa-stat-icon').text(change >= 0 ? '📈' : '📉');
                    }
                }
            });
        }
    }
    
    // Format numbers for display
    function formatNumber(number) {
        if (number >= 1000000) {
            return Math.round(number / 100000) / 10 + 'M';
        } else if (number >= 1000) {
            return Math.round(number / 100) / 10 + 'K';
        }
        return number.toString();
    }
    
    // Initialize everything when document is ready
    setTimeout(function() {
        initMiniChart();
        ensureExpandedWidget();
        
        // Update stats every 30 seconds
        setInterval(updateStats, 30000);
    }, 500);
});
