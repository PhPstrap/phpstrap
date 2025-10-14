<?php
if (!isset($this)) {
    exit('Direct access not allowed');
}

$settings = $this->getSettings();
$unprocessed_count = $this->getUnprocessedUsersCount();
$api_logs = $this->getRecentApiLogs(10);
$debug_logs = $settings['debug_mode'] ? $this->getRecentDebugLogs(20) : [];

// Generate check URL
$check_url = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . 
             $_SERVER['HTTP_HOST'] . 
             str_replace('/views/admin-settings.php', '/check.php', $_SERVER['PHP_SELF']);

// Count active lists
$active_lists = 0;
for ($i = 1; $i <= 10; $i++) {
    if ($settings['list_' . $i . '_enabled'] && 
        $settings['list_' . $i . '_auto_subscribe'] && 
        !empty($settings['list_' . $i . '_id'])) {
        $active_lists++;
    }
}
?>

<style>
    .sendy-admin-settings { max-width: 1200px; margin: 0 auto; padding: 20px; }
    .status-card { text-align: center; height: 100%; transition: transform 0.2s; }
    .status-card:hover { transform: translateY(-5px); }
    .status-card .card-body { padding: 1.5rem; }
    .status-card h5 { font-size: 0.9rem; color: #6c757d; margin-bottom: 1rem; }
    .status-card .status-value { font-size: 1.8rem; font-weight: bold; }
    .status-card .badge { font-size: 1.2rem; padding: 0.5rem 1rem; }
    
    .integration-code { 
        background-color: #f8f9fa; 
        padding: 15px; 
        border-radius: 5px; 
        font-family: 'Courier New', monospace; 
        font-size: 0.9rem;
        border: 1px solid #dee2e6;
        overflow-x: auto;
    }
    
    .list-config-table { background: white; }
    .list-config-table th { 
        background-color: #f8f9fa; 
        font-weight: 600;
        border-bottom: 2px solid #dee2e6;
    }
    .list-config-table td { vertical-align: middle; }
    .list-config-table .form-control-sm { border: 1px solid #ced4da; }
    .list-config-table .form-control-sm:focus { 
        border-color: #80bdff;
        box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
    }
    
    .btn-group-action { margin-bottom: 20px; }
    .btn-group-action .btn { margin-right: 10px; }
    
    .debug-log-entry {
        padding: 5px 10px;
        border-bottom: 1px solid #f0f0f0;
        font-size: 0.85rem;
    }
    .debug-log-entry:hover { background-color: #f8f9fa; }
    
    .api-key-group { position: relative; }
    .api-key-toggle { 
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        color: #6c757d;
    }
    
    @media (max-width: 768px) {
        .status-card .status-value { font-size: 1.2rem; }
        .btn-group-action .btn { 
            display: block; 
            width: 100%; 
            margin-bottom: 10px; 
        }
    }
</style>

<div class="sendy-admin-settings">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-envelope"></i> Sendy Auto-Subscribe Module</h1>
        <span class="badge badge-info">v<?php echo \PhPstrap\Modules\Sendy\SendyModule::VERSION; ?></span>
    </div>
    
    <!-- Status Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card status-card h-100 <?php echo $settings['enabled'] ? 'border-success' : 'border-danger'; ?>">
                <div class="card-body">
                    <h5>Module Status</h5>
                    <div class="status-value">
                        <?php if ($settings['enabled']): ?>
                            <span class="text-success"><i class="fas fa-check-circle"></i></span>
                            <div class="badge badge-success">Active</div>
                        <?php else: ?>
                            <span class="text-danger"><i class="fas fa-times-circle"></i></span>
                            <div class="badge badge-danger">Inactive</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card status-card h-100 <?php echo $settings['auto_subscribe_new_users'] ? 'border-success' : 'border-warning'; ?>">
                <div class="card-body">
                    <h5>Auto-Subscribe</h5>
                    <div class="status-value">
                        <?php if ($settings['auto_subscribe_new_users']): ?>
                            <span class="text-success"><i class="fas fa-user-plus"></i></span>
                            <div class="badge badge-success">Enabled</div>
                        <?php else: ?>
                            <span class="text-warning"><i class="fas fa-user-slash"></i></span>
                            <div class="badge badge-warning">Disabled</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card status-card h-100 <?php echo $active_lists > 0 ? 'border-success' : 'border-danger'; ?>">
                <div class="card-body">
                    <h5>Active Lists</h5>
                    <div class="status-value">
                        <span class="<?php echo $active_lists > 0 ? 'text-success' : 'text-danger'; ?>">
                            <i class="fas fa-list"></i>
                        </span>
                        <div class="badge badge-<?php echo $active_lists > 0 ? 'success' : 'danger'; ?>">
                            <?php echo $active_lists; ?> / 10
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card status-card h-100 <?php echo $unprocessed_count > 0 ? 'border-warning' : 'border-success'; ?>">
                <div class="card-body">
                    <h5>Unprocessed Users</h5>
                    <div class="status-value">
                        <span class="<?php echo $unprocessed_count > 0 ? 'text-warning' : 'text-success'; ?>">
                            <i class="fas fa-users"></i>
                        </span>
                        <div class="badge badge-<?php echo $unprocessed_count > 0 ? 'warning' : 'success'; ?>">
                            <?php echo $unprocessed_count; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions & Alert -->
    <?php if ($unprocessed_count > 0 && $settings['auto_subscribe_new_users']): ?>
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle"></i> 
        <strong>Action Required:</strong> There are <?php echo $unprocessed_count; ?> verified users waiting to be subscribed to Sendy.
        <button type="button" class="btn btn-warning btn-sm ml-3" onclick="processNow()">
            <i class="fas fa-sync"></i> Process Now
        </button>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
    <?php endif; ?>

    <!-- Quick Actions -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-bolt"></i> Quick Actions</h5>
        </div>
        <div class="card-body">
            <div class="btn-group-action">
                <button type="button" class="btn btn-success" onclick="processNow()">
                    <i class="fas fa-play-circle"></i> Process New Users
                </button>
                <button type="button" class="btn btn-info" onclick="testSubscription()">
                    <i class="fas fa-vial"></i> Test Subscription
                </button>
                <button type="button" class="btn btn-warning" onclick="resetUsers()">
                    <i class="fas fa-redo"></i> Reset All Users
                </button>
                <?php if ($settings['debug_mode']): ?>
                <button type="button" class="btn btn-danger" onclick="clearLogs()">
                    <i class="fas fa-trash"></i> Clear Logs
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Settings Form -->
    <form method="post" id="settings-form" class="settings-form">
        
        <!-- Basic Settings -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-cog"></i> Basic Settings</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-check form-switch mb-3">
                            <input type="checkbox" class="form-check-input" id="enabled" 
                                   name="enabled" value="1" <?php echo $settings['enabled'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="enabled">
                                <strong>Enable Module</strong>
                                <small class="d-block text-muted">Master switch for the entire module</small>
                            </label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check form-switch mb-3">
                            <input type="checkbox" class="form-check-input" id="auto_subscribe_new_users" 
                                   name="auto_subscribe_new_users" value="1" 
                                   <?php echo $settings['auto_subscribe_new_users'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="auto_subscribe_new_users">
                                <strong>Auto-Subscribe New Users</strong>
                                <small class="d-block text-muted">Automatically add verified users to lists</small>
                            </label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check form-switch mb-3">
                            <input type="checkbox" class="form-check-input" id="debug_mode" 
                                   name="debug_mode" value="1" 
                                   <?php echo $settings['debug_mode'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="debug_mode">
                                <strong>Debug Mode</strong>
                                <small class="d-block text-muted">Enable detailed logging</small>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sendy Configuration -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-plug"></i> Sendy API Configuration</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="sendy_url">
                                <strong>Sendy URL</strong>
                                <i class="fas fa-question-circle text-muted ml-1" data-toggle="tooltip" 
                                   title="Your Sendy installation URL (without trailing slash)"></i>
                            </label>
                            <input type="url" class="form-control" id="sendy_url" 
                                   name="sendy_url" 
                                   value="<?php echo htmlspecialchars($settings['sendy_url'] ?? ''); ?>"
                                   placeholder="https://your-sendy-domain.com">
                            <small class="form-text text-muted">Enter your Sendy installation URL without trailing slash</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="sendy_api_key">
                                <strong>API Key</strong>
                                <i class="fas fa-question-circle text-muted ml-1" data-toggle="tooltip" 
                                   title="Found in Sendy Settings → Your API key"></i>
                            </label>
                            <div class="api-key-group">
                                <input type="password" class="form-control pr-5" id="sendy_api_key" 
                                       name="sendy_api_key" 
                                       value="<?php echo htmlspecialchars($settings['sendy_api_key'] ?? ''); ?>"
                                       placeholder="Enter your Sendy API key">
                                <i class="fas fa-eye api-key-toggle" onclick="toggleApiKey()"></i>
                            </div>
                            <small class="form-text text-muted">Find this in Sendy → Settings → Your API key</small>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> 
                    <strong>Need help?</strong> Log into your Sendy installation → Settings → Your API key
                </div>
            </div>
        </div>
        
        <!-- Lists Configuration -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-list"></i> Mailing Lists Configuration</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-light border mb-4">
                    <i class="fas fa-lightbulb text-warning"></i> 
                    Configure up to 10 Sendy lists below. Users will only be subscribed to lists that have both:
                    <span class="badge badge-primary">Enabled</span> AND 
                    <span class="badge badge-success">Auto-Subscribe</span> checked.
                </div>
                
                <div class="table-responsive">
                    <table class="table table-bordered list-config-table">
                        <thead>
                            <tr>
                                <th width="5%" class="text-center">#</th>
                                <th width="25%">List ID <span class="text-danger">*</span></th>
                                <th width="30%">Display Name</th>
                                <th width="15%" class="text-center">
                                    <i class="fas fa-power-off"></i> Enabled
                                </th>
                                <th width="25%" class="text-center">
                                    <i class="fas fa-user-plus"></i> Auto-Subscribe
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php for ($i = 1; $i <= 10; $i++): ?>
                            <?php 
                            $list_enabled = $settings['list_' . $i . '_enabled'] ?? false;
                            $list_auto = $settings['list_' . $i . '_auto_subscribe'] ?? false;
                            $list_id = $settings['list_' . $i . '_id'] ?? '';
                            $row_class = ($list_enabled && $list_auto && !empty($list_id)) ? 'table-success' : '';
                            ?>
                            <tr class="<?php echo $row_class; ?>">
                                <td class="text-center font-weight-bold"><?php echo $i; ?></td>
                                <td>
                                    <input type="text" class="form-control form-control-sm" 
                                           name="list_<?php echo $i; ?>_id" 
                                           value="<?php echo htmlspecialchars($list_id); ?>"
                                           placeholder="e.g., ABC123xyz">
                                </td>
                                <td>
                                    <input type="text" class="form-control form-control-sm" 
                                           name="list_<?php echo $i; ?>_name" 
                                           value="<?php echo htmlspecialchars($settings['list_' . $i . '_name'] ?? "List {$i}"); ?>"
                                           placeholder="Newsletter Name">
                                </td>
                                <td class="text-center">
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input" 
                                               name="list_<?php echo $i; ?>_enabled" value="1"
                                               id="list_<?php echo $i; ?>_enabled"
                                               <?php echo $list_enabled ? 'checked' : ''; ?>>
                                        <label class="custom-control-label" for="list_<?php echo $i; ?>_enabled"></label>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input" 
                                               name="list_<?php echo $i; ?>_auto_subscribe" value="1"
                                               id="list_<?php echo $i; ?>_auto_subscribe"
                                               <?php echo $list_auto ? 'checked' : ''; ?>>
                                        <label class="custom-control-label" for="list_<?php echo $i; ?>_auto_subscribe"></label>
                                    </div>
                                </td>
                            </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="alert alert-warning">
                    <i class="fas fa-question-circle"></i> 
                    <strong>How to find List IDs:</strong> 
                    In Sendy → View all lists → Click on a list → The ID is in the URL: 
                    <code>/subscribers?i=1&l=<strong>YOUR_LIST_ID</strong></code>
                </div>
            </div>
        </div>

        <!-- Integration Guide -->
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-code"></i> Integration Guide</h5>
            </div>
            <div class="card-body">
                <p class="mb-3">After creating users via API or Admin, trigger the subscription process using one of these methods:</p>
                
                <ul class="nav nav-tabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" data-toggle="tab" href="#php-method">PHP</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#http-method">HTTP/cURL</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#js-method">JavaScript</a>
                    </li>
                </ul>
                
                <div class="tab-content mt-3">
                    <div id="php-method" class="tab-pane active">
                        <div class="integration-code">
// After creating user<br>
include 'modules/sendy_subscriber/check.php';
                        </div>
                    </div>
                    <div id="http-method" class="tab-pane">
                        <div class="integration-code">
# Via cURL<br>
curl "<?php echo htmlspecialchars($check_url); ?>?key=<?php echo htmlspecialchars($settings['check_key']); ?>"<br><br>

# Or in PHP<br>
file_get_contents('<?php echo htmlspecialchars($check_url); ?>?key=<?php echo htmlspecialchars($settings['check_key']); ?>');
                        </div>
                    </div>
                    <div id="js-method" class="tab-pane">
                        <div class="integration-code">
// Via JavaScript/AJAX<br>
fetch('<?php echo htmlspecialchars($check_url); ?>?key=<?php echo htmlspecialchars($settings['check_key']); ?>')<br>
&nbsp;&nbsp;.then(response => response.json())<br>
&nbsp;&nbsp;.then(data => console.log(data.message));
                        </div>
                    </div>
                </div>
                
                <div class="row mt-4">
                    <div class="col-md-8">
                        <label><strong>Your Check URL:</strong></label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="check-url-input" readonly 
                                   value="<?php echo htmlspecialchars($check_url); ?>?key=<?php echo htmlspecialchars($settings['check_key']); ?>">
                            <div class="input-group-append">
                                <button class="btn btn-outline-secondary" type="button" onclick="copyUrl()">
                                    <i class="fas fa-copy"></i> Copy
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label><strong>Security Key:</strong></label>
                        <div class="input-group">
                            <input type="text" class="form-control" name="check_key" readonly 
                                   value="<?php echo htmlspecialchars($settings['check_key']); ?>">
                            <div class="input-group-append">
                                <button class="btn btn-outline-warning" type="button" onclick="regenerateKey()">
                                    <i class="fas fa-sync"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="text-center mb-4">
            <button type="submit" class="btn btn-primary btn-lg px-5">
                <i class="fas fa-save"></i> Save Settings
            </button>
        </div>
    </form>

    <!-- Test Subscription Modal -->
    <div class="modal fade" id="testModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-vial"></i> Test Subscription</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Email Address <span class="text-danger">*</span></label>
                        <input type="email" id="test_email" class="form-control" placeholder="test@example.com">
                    </div>
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" id="test_name" class="form-control" placeholder="Test User">
                    </div>
                    <div id="test_results"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" onclick="runTest()">
                        <i class="fas fa-play"></i> Run Test
                    </button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Logs Section (if debug mode) -->
    <?php if ($settings['debug_mode']): ?>
    
    <!-- API Logs -->
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-satellite-dish"></i> Recent API Calls</h5>
        </div>
        <div class="card-body">
            <?php if (empty($api_logs)): ?>
                <p class="text-muted text-center py-3">No API calls logged yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Endpoint</th>
                                <th>Response</th>
                                <th width="80">Code</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($api_logs as $log): ?>
                            <tr>
                                <td><small><?php echo $log['created_at']; ?></small></td>
                                <td><small><?php echo htmlspecialchars($log['endpoint']); ?></small></td>
                                <td><small><?php echo htmlspecialchars(substr($log['response_data'], 0, 50)); ?>...</small></td>
                                <td class="text-center">
                                    <span class="badge badge-<?php echo $log['response_code'] == 200 ? 'success' : 'danger'; ?>">
                                        <?php echo $log['response_code']; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Debug Logs -->
    <div class="card mt-4 mb-4">
        <div class="card-header bg-warning">
            <h5 class="mb-0 text-dark"><i class="fas fa-bug"></i> Debug Logs</h5>
        </div>
        <div class="card-body p-0">
            <div style="max-height: 400px; overflow-y: auto;">
                <?php if (empty($debug_logs)): ?>
                    <p class="text-muted text-center py-3">No debug logs available.</p>
                <?php else: ?>
                    <?php foreach ($debug_logs as $log): ?>
                    <div class="debug-log-entry">
                        <span class="text-muted"><?php echo $log['created_at']; ?></span>
                        <span class="badge badge-<?php echo $log['level'] === 'error' ? 'danger' : ($log['level'] === 'warning' ? 'warning' : 'info'); ?>">
                            <?php echo strtoupper($log['level']); ?>
                        </span>
                        <span class="ml-2"><?php echo htmlspecialchars($log['message']); ?></span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php endif; ?>
</div>

<script>
const AJAX_URL = '/modules/sendy_subscriber/ajax/sendy-ajax.php';
const CHECK_URL = '<?php echo $check_url; ?>?key=<?php echo $settings['check_key']; ?>';

// Initialize tooltips
$(function () {
    $('[data-toggle="tooltip"]').tooltip();
});

// Auto-check on page load
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($unprocessed_count > 0 && $settings['auto_subscribe_new_users']): ?>
    // Auto-process after 3 seconds if there are unprocessed users
    setTimeout(function() {
        console.log('Auto-processing new users...');
        processNow();
    }, 3000);
    <?php endif; ?>
});

function processNow() {
    const btn = event ? event.target : null;
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    }
    
    fetch(CHECK_URL)
        .then(response => response.json())
        .then(data => {
            showAlert(data.message, data.success ? 'success' : 'warning');
            setTimeout(() => location.reload(), 2000);
        })
        .catch(error => {
            showAlert('Error: ' + error.message, 'danger');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-sync"></i> Process Now';
            }
        });
}

function testSubscription() {
    $('#testModal').modal('show');
}

function runTest() {
    const email = document.getElementById('test_email').value;
    const name = document.getElementById('test_name').value || 'Test User';
    
    if (!email) {
        showAlert('Please enter an email address', 'warning');
        return;
    }
    
    const btn = event.target;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing...';
    
    fetch(AJAX_URL, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=test_subscribe&email=' + encodeURIComponent(email) + '&name=' + encodeURIComponent(name)
    })
    .then(response => response.json())
    .then(data => {
        const resultsDiv = document.getElementById('test_results');
        if (data.success) {
            let html = '<div class="alert alert-success mt-3"><strong>' + data.message + '</strong>';
            if (data.details) {
                html += '<ul class="mb-0 mt-2">';
                data.details.forEach(detail => {
                    html += '<li>' + detail + '</li>';
                });
                html += '</ul>';
            }
            html += '</div>';
            resultsDiv.innerHTML = html;
        } else {
            resultsDiv.innerHTML = '<div class="alert alert-danger mt-3"><strong>Error:</strong> ' + data.message + '</div>';
        }
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-play"></i> Run Test';
    });
}

function resetUsers() {
    if (!confirm('This will mark ALL users as unprocessed and resubscribe them. Continue?')) return;
    
    showLoading();
    fetch(AJAX_URL, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=reset_processed'
    })
    .then(response => response.json())
    .then(data => {
        showAlert(data.success ? 'Reset complete!' : 'Reset failed', data.success ? 'success' : 'danger');
        if (data.success) setTimeout(() => location.reload(), 2000);
    })
    .finally(hideLoading);
}

function clearLogs() {
    if (!confirm('Clear all debug and API logs?')) return;
    
    showLoading();
    fetch(AJAX_URL, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=clear_logs'
    })
    .then(response => response.json())
    .then(data => {
        showAlert(data.success ? 'Logs cleared!' : 'Failed to clear logs', data.success ? 'success' : 'danger');
        if (data.success) setTimeout(() => location.reload(), 2000);
    })
    .finally(hideLoading);
}

function toggleApiKey() {
    const input = document.getElementById('sendy_api_key');
    const icon = document.querySelector('.api-key-toggle');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

function copyUrl() {
    const input = document.getElementById('check-url-input');
    input.select();
    document.execCommand('copy');
    
    showAlert('URL copied to clipboard!', 'success');
}

function regenerateKey() {
    if (!confirm('Generate a new security key? The old key will stop working.')) return;
    
    const newKey = Array.from(crypto.getRandomValues(new Uint8Array(16)))
        .map(b => b.toString(16).padStart(2, '0'))
        .join('');
    
    document.querySelector('input[name="check_key"]').value = newKey;
    showAlert('New key generated. Save settings to apply.', 'warning');
}

// Save settings via AJAX
document.getElementById('settings-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    showLoading();
    const formData = new FormData(this);
    formData.append('action', 'save_settings');
    
    const params = new URLSearchParams();
    for (const pair of formData) {
        params.append(pair[0], pair[1]);
    }
    
    fetch(AJAX_URL, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: params
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Settings saved successfully!', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert('Error saving settings: ' + (data.message || 'Unknown error'), 'danger');
        }
    })
    .finally(hideLoading);
});

// Helper functions
function showAlert(message, type = 'info') {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999;">
            ${message}
            <button type="button" class="close" data-dismiss="alert">
                <span>&times;</span>
            </button>
        </div>
    `;
    
    const alertEl = $(alertHtml);
    $('body').append(alertEl);
    
    setTimeout(() => {
        alertEl.alert('close');
    }, 5000);
}

function showLoading() {
    $('body').append('<div class="loading-overlay"><div class="spinner-border text-primary"></div></div>');
}

function hideLoading() {
    $('.loading-overlay').remove();
}
</script>

<style>
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}
</style>

<?php
// Helper function
function htmlspecialchars($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}
?>