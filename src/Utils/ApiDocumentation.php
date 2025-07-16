<?php
namespace bfp\Utils;

use bfp\Plugin;

class ApiDocumentation {
    
    public function getRegisteredEndpoints(): array {
        $routes = rest_get_server()->get_routes();
        $analytics_routes = [];
        
        foreach ($routes as $route => $endpoints) {
            // Filter to just your namespace
            if (strpos($route, '/bandfront-analytics/') !== 0) {
                continue;
            }
            
            foreach ($endpoints as $endpoint) {
                $analytics_routes[] = [
                    'route' => $route,
                    'methods' => $endpoint['methods'],
                    'callback' => $this->getCallbackName($endpoint['callback']),
                    'args' => $endpoint['args'] ?? [],
                    'permission' => $this->getPermissionType($endpoint['permission_callback']),
                ];
            }
        }
        
        return $analytics_routes;
    }
    
    private function getCallbackName($callback): string {
        if (is_array($callback) && count($callback) === 2) {
            return get_class($callback[0]) . '::' . $callback[1];
        }
        return 'Unknown';
    }
    
    private function getPermissionType($callback): string {
        if ($callback === '__return_true') {
            return 'Public';
        } elseif (is_array($callback)) {
            return 'Authenticated';
        }
        return 'Unknown';
    }
    
    public function renderEndpointsTable(): void {
        $endpoints = $this->getRegisteredEndpoints();
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Endpoint</th>
                    <th>Methods</th>
                    <th>Permission</th>
                    <th>Parameters</th>
                    <th>Handler</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($endpoints as $endpoint): ?>
                <tr>
                    <td><code><?php echo esc_html($endpoint['route']); ?></code></td>
                    <td><?php echo implode(', ', array_keys($endpoint['methods'])); ?></td>
                    <td>
                        <span class="badge badge-<?php echo $endpoint['permission'] === 'Public' ? 'warning' : 'success'; ?>">
                            <?php echo esc_html($endpoint['permission']); ?>
                        </span>
                    </td>
                    <td>
                        <?php if (!empty($endpoint['args'])): ?>
                            <ul>
                            <?php foreach ($endpoint['args'] as $arg => $config): ?>
                                <li>
                                    <code><?php echo esc_html($arg); ?></code>
                                    <?php if (!empty($config['required'])): ?>
                                        <span class="required">*</span>
                                    <?php endif; ?>
                                    <?php if (!empty($config['type'])): ?>
                                        <em>(<?php echo esc_html($config['type']); ?>)</em>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <em>None</em>
                        <?php endif; ?>
                    </td>
                    <td><small><?php echo esc_html($endpoint['callback']); ?></small></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
}