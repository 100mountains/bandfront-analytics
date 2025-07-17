jQuery(document).ready(function($) {
    'use strict';
    
    let miniChart = null;
    let expandedChart = null;
    let expandedWidget = null;
    
    // Initialize mini chart in admin bar
    function initMiniChart() {
        const ctx = document.getElementById('bfa-admin-bar-chart');
        if (!ctx || !window.Chart) return;
        
        miniChart = new Chart(ctx, {
            type: 'line',
            data: bfaAdminBarData.chartData,
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
    
    // Initialize expanded chart
    function initExpandedChart() {
        const ctx = document.getElementById('bfa-expanded-chart');
        if (!ctx || !window.Chart) return;
        
        expandedChart = new Chart(ctx, {
            type: 'line',
            data: bfaAdminBarData.chartData,
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
    
    // Show/hide expanded widget
    function toggleExpandedWidget() {
        expandedWidget = $('#bfa-expanded-widget');
        
        if (expandedWidget.is(':visible')) {
            expandedWidget.fadeOut(200);
        } else {
            expandedWidget.fadeIn(200);
            if (!expandedChart) {
                setTimeout(initExpandedChart, 100);
            }
        }
    }
    
    // Update stats periodically
    function updateStats() {
        $.get({
            url: bfaAdminBarData.apiUrl + 'admin/quick-stats',
            headers: {
                'X-WP-Nonce': bfaAdminBarData.nonce
            },
            success: function(data) {
                // Update admin bar numbers
                $('.bfa-views-box .bfa-stat-number').text(formatNumber(data.today_views));
                $('.bfa-visitors-box .bfa-stat-number').text(formatNumber(data.today_visitors));
                $('.bfa-plays-box .bfa-stat-number').text(formatNumber(data.today_plays || 0));
                
                // Update expanded widget
                $('.bfa-expanded-stat').eq(0).find('.bfa-expanded-stat-number').text(formatNumber(data.today_views));
                $('.bfa-expanded-stat').eq(0).find('.bfa-expanded-stat-sublabel').text(formatNumber(data.today_visitors) + ' unique visitors');
                $('.bfa-expanded-stat').eq(1).find('.bfa-expanded-stat-number').text(formatNumber(data.today_plays || 0));
                
                // Update live indicator
                $('.bfa-live-indicator span:last-child').text(formatNumber(data.active_users) + ' active now');
                
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
    
    // Format numbers for display
    function formatNumber(number) {
        if (number >= 1000000) {
            return Math.round(number / 100000) / 10 + 'M';
        } else if (number >= 1000) {
            return Math.round(number / 100) / 10 + 'K';
        }
        return number.toString();
    }
    
    // Add click handlers
    $(document).on('click', '.bfa-analytics-main-bar', function(e) {
        e.preventDefault();
        e.stopPropagation();
        toggleExpandedWidget();
    });
    
    // Close expanded widget when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.bfa-analytics-main-bar, .bfa-expanded-widget').length) {
            if (expandedWidget && expandedWidget.is(':visible')) {
                expandedWidget.fadeOut(200);
            }
        }
    });
    
    // Add hover effects for stat boxes
    $('.bfa-stat-box').hover(
        function() {
            $(this).css('transform', 'translateY(-1px) scale(1.05)');
        },
        function() {
            $(this).css('transform', 'translateY(0) scale(1)');
        }
    );
    
    // Initialize everything when document is ready
    setTimeout(function() {
        initMiniChart();
        
        // Update stats every 30 seconds
        setInterval(updateStats, 30000);
    }, 500);
    
    // Add pulsing animation to stat boxes
    function pulseStatBoxes() {
        $('.bfa-stat-box').each(function(index) {
            setTimeout(() => {
                $(this).addClass('pulse');
                setTimeout(() => {
                    $(this).removeClass('pulse');
                }, 300);
            }, index * 100);
        });
    }
    
    // Pulse stat boxes every 60 seconds
    setInterval(pulseStatBoxes, 60000);
    
    // Add CSS for pulse animation
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            .bfa-stat-box.pulse {
                animation: bfaStatPulse 0.3s ease-in-out;
            }
            @keyframes bfaStatPulse {
                0% { transform: scale(1); }
                50% { transform: scale(1.1); }
                100% { transform: scale(1); }
            }
        `)
        .appendTo('head');
});
