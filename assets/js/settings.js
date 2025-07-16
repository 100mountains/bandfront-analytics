jQuery(document).ready(function($) {
    'use strict';
    
    // API Traffic Monitor
    let trafficInterval;
    const $trafficLog = $('#bfa-traffic-log');
    const $clearButton = $('#bfa-clear-traffic');
    
    function loadApiTraffic() {
        if (!$trafficLog.length) return;
        
        $.ajax({
            url: bfaAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'bfa_get_api_traffic',
                nonce: bfaAdmin.ajaxNonce
            },
            success: function(response) {
                if (response.success && response.data.traffic) {
                    renderTraffic(response.data.traffic);
                }
            }
        });
    }
    
    function renderTraffic(traffic) {
        $trafficLog.empty();
        
        if (traffic.length === 0) {
            $trafficLog.html('<div class="bfa-no-traffic">No API traffic recorded yet.</div>');
            return;
        }
        
        // Reverse to show newest first
        traffic.reverse().forEach(function(request) {
            const time = new Date(request.timestamp).toLocaleTimeString();
            const methodClass = 'bfa-method-' + request.method.toLowerCase();
            
            const $entry = $('<div class="bfa-traffic-entry">');
            $entry.html(`
                <div class="bfa-traffic-time">${time}</div>
                <div class="bfa-traffic-method ${methodClass}">${request.method}</div>
                <div class="bfa-traffic-route">${request.route}</div>
                <div class="bfa-traffic-user">User: ${request.user_id || 'Guest'}</div>
            `);
            
            $trafficLog.append($entry);
        });
    }
    
    // Auto-refresh traffic if on REST API tab
    const currentTab = new URLSearchParams(window.location.search).get('tab');
    if (currentTab === 'rest-api' && $('#bfa-enable-api').is(':checked')) {
        loadApiTraffic();
        trafficInterval = setInterval(loadApiTraffic, 5000); // Refresh every 5 seconds
    }
    
    // Clear traffic
    $clearButton.on('click', function() {
        $trafficLog.html('<div class="bfa-no-traffic">Traffic log cleared.</div>');
    });
    
    // Stop interval when leaving page
    $(window).on('beforeunload', function() {
        if (trafficInterval) {
            clearInterval(trafficInterval);
        }
    });
});
