jQuery(document).ready(function($) {
    // Main chart instance
    let mainChart = null;
    
    // Initialize main analytics chart
    function initMainChart() {
        const ctx = document.getElementById('bfa-main-chart');
        if (!ctx) return;
        
        const dateRange = $('#bfa-date-range').val() || 7;
        
        $.get({
            url: bfaAdmin.apiUrl + 'chart',
            data: { days: dateRange },
            headers: {
                'X-WP-Nonce': bfaAdmin.nonce
            },
            success: function(data) {
                if (mainChart) {
                    mainChart.destroy();
                }
                
                mainChart = new Chart(ctx, {
                    type: 'line',
                    data: data,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                mode: 'index',
                                intersect: false,
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    precision: 0
                                }
                            }
                        }
                    }
                });
            }
        });
    }
    
    // Load top posts
    function loadTopPosts() {
        const container = $('#bfa-top-posts');
        if (!container.length) return;
        
        const dateRange = $('#bfa-date-range').val() || 7;
        
        container.html('<p>Loading...</p>');
        
        $.get({
            url: bfaAdmin.apiUrl + 'top-posts',
            data: { 
                days: dateRange,
                limit: 10
            },
            headers: {
                'X-WP-Nonce': bfaAdmin.nonce
            },
            success: function(data) {
                let html = '<table class="widefat striped">';
                html += '<thead><tr><th>Page</th><th>Views</th></tr></thead>';
                html += '<tbody>';
                
                data.forEach(function(post) {
                    html += '<tr>';
                    html += '<td><a href="' + post.url + '">' + post.title + '</a></td>';
                    html += '<td>' + post.views.toLocaleString() + '</td>';
                    html += '</tr>';
                });
                
                html += '</tbody></table>';
                container.html(html);
            }
        });
    }
    
    // Date range change handler
    $('#bfa-date-range').on('change', function() {
        initMainChart();
        loadTopPosts();
    });
    
    // Initialize dashboard widget chart
    function initDashboardWidget() {
        const ctx = document.getElementById('bfa-dashboard-chart');
        if (!ctx) return;
        
        $.get({
            url: bfaAdmin.apiUrl + 'chart',
            data: { days: 7 },
            headers: {
                'X-WP-Nonce': bfaAdmin.nonce
            },
            success: function(data) {
                new Chart(ctx, {
                    type: 'line',
                    data: data,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
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
    }
    
    // Initialize on page load
    initMainChart();
    loadTopPosts();
    initDashboardWidget();
    
    // Auto-refresh every 60 seconds
    setInterval(function() {
        if ($('#bfa-main-chart').length) {
            initMainChart();
            loadTopPosts();
        }
    }, 60000);
});
