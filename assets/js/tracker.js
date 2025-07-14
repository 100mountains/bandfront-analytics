(function() {
    'use strict';
    
    // Check if tracking is enabled
    if (!window.bfaTracker || !window.bfaTracker.trackingEnabled) {
        return;
    }
    
    // Check sampling
    if (window.bfaTracker.sampling < 1 && Math.random() > window.bfaTracker.sampling) {
        return;
    }
    
    // Track pageview on load
    document.addEventListener('DOMContentLoaded', function() {
        trackEvent('pageview', {
            object_id: window.bfaPageData?.objectId || 0,
            meta: {
                page_type: window.bfaPageData?.pageType || 'unknown',
                referrer: document.referrer,
                viewport: window.innerWidth + 'x' + window.innerHeight,
            }
        });
        
        // Track scroll depth
        let maxScroll = 0;
        let scrollTimer = null;
        
        window.addEventListener('scroll', function() {
            const scrollPercent = Math.round((window.scrollY + window.innerHeight) / document.body.scrollHeight * 100);
            if (scrollPercent > maxScroll) {
                maxScroll = scrollPercent;
                
                clearTimeout(scrollTimer);
                scrollTimer = setTimeout(function() {
                    trackEvent('scroll', {
                        value: maxScroll,
                        object_id: window.bfaPageData?.objectId || 0
                    });
                }, 1000);
            }
        });
        
        // Track time on page
        let startTime = Date.now();
        window.addEventListener('beforeunload', function() {
            const timeOnPage = Math.round((Date.now() - startTime) / 1000);
            if (timeOnPage > 3) { // Only track if more than 3 seconds
                navigator.sendBeacon(
                    window.bfaTracker.apiUrl + 'track',
                    JSON.stringify({
                        event_type: 'time_on_page',
                        value: timeOnPage,
                        object_id: window.bfaPageData?.objectId || 0
                    })
                );
            }
        });
        
        // Track music plays
        trackMusicPlays();
    });
    
    /**
     * Track an event
     */
    function trackEvent(eventType, data) {
        const eventData = {
            event_type: eventType,
            ...data
        };
        
        fetch(window.bfaTracker.apiUrl + 'track', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': window.bfaTracker.nonce
            },
            body: JSON.stringify(eventData)
        }).catch(function(error) {
            console.error('BFA tracking error:', error);
        });
    }
    
    /**
     * Track music player events
     */
    function trackMusicPlays() {
        // Track HTML5 audio elements
        const audioElements = document.querySelectorAll('audio');
        
        audioElements.forEach(function(audio, index) {
            let playStartTime = null;
            let totalPlayTime = 0;
            let hasTrackedPlay = false;
            
            // Track play event
            audio.addEventListener('play', function() {
                playStartTime = Date.now();
                
                if (!hasTrackedPlay) {
                    hasTrackedPlay = true;
                    trackEvent('music_play', {
                        object_id: window.bfaPageData?.objectId || 0,
                        meta: {
                            track_index: index,
                            track_src: audio.src,
                            duration: audio.duration
                        }
                    });
                }
            });
            
            // Track pause/end to calculate play duration
            const trackPlayDuration = function() {
                if (playStartTime) {
                    totalPlayTime += (Date.now() - playStartTime) / 1000;
                    playStartTime = null;
                    
                    // Track completion percentage
                    const completionPercent = audio.duration > 0 ? 
                        Math.round((audio.currentTime / audio.duration) * 100) : 0;
                    
                    trackEvent('music_duration', {
                        value: totalPlayTime,
                        object_id: window.bfaPageData?.objectId || 0,
                        meta: {
                            track_index: index,
                            completion: completionPercent
                        }
                    });
                }
            };
            
            audio.addEventListener('pause', trackPlayDuration);
            audio.addEventListener('ended', trackPlayDuration);
        });
        
        // Track MediaElement.js players
        if (window.mejs && window.mejs.players) {
            Object.values(window.mejs.players).forEach(function(player, index) {
                let playStartTime = null;
                let hasTrackedPlay = false;
                
                player.media.addEventListener('play', function() {
                    playStartTime = Date.now();
                    
                    if (!hasTrackedPlay) {
                        hasTrackedPlay = true;
                        trackEvent('music_play', {
                            object_id: window.bfaPageData?.objectId || 0,
                            meta: {
                                player_type: 'mediaelement',
                                track_index: index
                            }
                        });
                    }
                });
            });
        }
    }
    
    // Expose API for manual tracking
    window.bfaTrack = trackEvent;
})();
