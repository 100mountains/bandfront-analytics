jQuery(document).ready(function($) {
    // Initialize mini chart in admin bar
    function initAdminBarChart() {
        const container = $('#bfa-mini-chart');
        if (!container.length) return;
        
        // Create canvas element
        const canvas = $('<canvas></canvas>').attr({
            width: 200,
            height: 50
        });
        container.append(canvas);
        
        // Fetch chart data - always use 7 days for admin bar
        $.get({
            url: bfaAdminBar.apiUrl + 'chart',
            data: { days: 7 },
            headers: {
                'X-WP-Nonce': bfaAdminBar.nonce
            },
            success: function(data) {
                new Chart(canvas[0], {
                    type: 'line',
                    data: {
                        ...data,
                        datasets: [{
                            ...data.datasets[0],
                            borderColor: '#fff',
                            backgroundColor: 'rgba(255, 255, 255, 0.1)',
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: false,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                enabled: false
                            }
                        },
                        scales: {
                            x: {
                                display: false
                            },
                            y: {
                                display: false
                            }
                        },
                        elements: {
                            point: {
                                radius: 0
                            }
                        }
                    }
                });
            }
        });
        
        // Load stats summary
        $.get({
            url: bfaAdminBar.apiUrl + 'quick-stats',
            headers: {
                'X-WP-Nonce': bfaAdminBar.nonce
            },
            success: function(stats) {
                const statsHtml = `
                    <div class="bfa-admin-bar-stats">
                        <div class="bfa-stat-row">
                            <span>Active Now:</span>
                            <strong>${stats.active_users}</strong>
                        </div>
                        <div class="bfa-stat-row">
                            <span>Today:</span>
                            <strong>${stats.today_views.toLocaleString()}</strong>
                        </div>
                        <div class="bfa-stat-row">
                            <span>Plays:</span>
                            <strong>${(stats.today_plays || 0).toLocaleString()}</strong>
                        </div>
                    </div>
                `;
                container.after(statsHtml);
            }
        });
    }
    
    // Initialize on hover
    let initialized = false;
    $('#wp-admin-bar-bfa-stats').on('mouseenter', function() {
        if (!initialized) {
            initialized = true;
            initAdminBarChart();
        }
    });
});
