<div class="wrap bfa-admin-page">
    <div class="bfa-header">
        <h1 class="bfa-page-title">
            <span class="bfa-icon-wrapper">
                <span class="dashicons dashicons-database"></span>
            </span>
            Database Activity Monitor
            <?php 
            $is_monitoring_enabled = get_option('bfa_enable_database_monitoring', false);
            if ($is_monitoring_enabled): 
            ?>
                <span class="bfa-live-indicator">
                    <span class="bfa-pulse-dot"></span>
                    Live
                </span>
            <?php else: ?>
                <span class="bfa-live-indicator bfa-inactive">
                    <span class="bfa-pulse-dot bfa-inactive"></span>
                    Disabled
                </span>
            <?php endif; ?>
        </h1>
    </div>

    <div class="bfa-content">
        <!-- Existing content for the database monitor page -->
    </div>
</div>