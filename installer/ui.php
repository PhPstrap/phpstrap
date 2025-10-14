<?php
/**
 * PhPstrap Installer - User Interface
 * Save as: installer/ui.php
 *
 * Notes:
 * - Footer + header version come from /config/version.php (PHPSTRAP_VERSION / PHPSTRAP_NAME / PHPSTRAP_BUILD)
 * - Module cards correctly toggle checkboxes and preserve selection on validation errors
 */

function renderInstallerPage($steps, $current_step, $errors, $success) {
    // Try to load version constants for header/footer
    if (!defined('PHPSTRAP_VERSION')) {
        $verPaths = [
            __DIR__ . '/../config/version.php',   // typical production (root/config/version.php)
            __DIR__ . '/../../config/version.php' // fallback if installer is nested differently
        ];
        foreach ($verPaths as $vp) {
            if (is_file($vp)) { require_once $vp; break; }
        }
    }

    $appName    = defined('PHPSTRAP_NAME')   ? PHPSTRAP_NAME   : 'PhPstrap';
    $appVersion = defined('PHPSTRAP_VERSION')? PHPSTRAP_VERSION: '0.0.0';
    $appBuild   = defined('PHPSTRAP_BUILD')  ? PHPSTRAP_BUILD  : '';

    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($appName); ?> Installer</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
        <style>
            :root {
                --primary-color: #667eea;
                --secondary-color: #764ba2;
                --success-color: #28a745;
                --warning-color: #ffc107;
                --danger-color: #dc3545;
                --info-color: #17a2b8;
            }
            body {
                background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
                min-height: 100vh;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            }
            .installer-card {
                box-shadow: 0 15px 35px rgba(0,0,0,0.1);
                border: none;
                border-radius: 15px;
                backdrop-filter: blur(10px);
            }
            .step-indicator { background: #f8f9fa; border-radius: 10px; padding: 20px; margin-bottom: 30px; }
            .step {
                display: inline-block; width: 40px; height: 40px; border-radius: 50%;
                background: #dee2e6; color: #6c757d; text-align: center; line-height: 40px;
                margin: 0 10px; font-weight: bold; transition: all 0.3s ease;
            }
            .step.active { background: var(--primary-color); color: #fff; transform: scale(1.1); }
            .step.completed { background: var(--success-color); color: #fff; }
            .requirement { padding: 15px; margin: 8px 0; border-radius: 8px; transition: all 0.2s ease; border-left: 4px solid transparent; }
            .requirement.pass { background: #d1edff; border-left-color: var(--info-color); }
            .requirement.fail { background: #f8d7da; border-left-color: var(--danger-color); }
            .requirement.optional { background: #fff3cd; border-left-color: var(--warning-color); }
            .module-card { transition: all 0.3s ease; cursor: pointer; border: 2px solid #e9ecef; background: #fff; }
            .module-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); border-color: var(--primary-color); }
            .module-card.selected { border-color: var(--primary-color) !important; background: linear-gradient(45deg, #f8f9ff, #fff); box-shadow: 0 8px 25px rgba(102, 126, 234, 0.2); }
            .module-card .module-icon {
                width: 50px; height: 50px; border-radius: 50%;
                display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; font-size: 1.5rem;
            }
            .progress-bar-animated { animation: progress-bar-stripes 1s linear infinite; }
            @keyframes progress-bar-stripes { 0% { background-position: 0 0; } 100% { background-position: 40px 0; } }
            .fade-in { animation: fadeIn 0.5s ease-in; }
            @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
            .install-complete { text-align: center; padding: 2rem; }
            .install-complete .success-icon { font-size: 5rem; color: var(--success-color); margin-bottom: 1rem; animation: pulse 2s infinite; }
            @keyframes pulse { 0% { transform: scale(1); } 50% { transform: scale(1.05); } 100% { transform: scale(1); } }
        </style>
    </head>
    <body>
        <div class="container py-4">
            <div class="row justify-content-center">
                <div class="col-lg-10 col-xl-8">
                    <div class="card installer-card fade-in">
                        <div class="card-header bg-primary text-white text-center py-3">
                            <h2 class="mb-1"><i class="fas fa-cog me-2"></i><?php echo htmlspecialchars($appName); ?> Installer</h2>
                            <small class="opacity-75">
                                Modular PHP Members Database System
                                <span class="ms-2">•</span>
                                <span class="ms-2">v<?php echo htmlspecialchars($appVersion); ?><?php echo $appBuild ? ' (build '.htmlspecialchars($appBuild).')' : ''; ?></span>
                            </small>
                        </div>
                        <div class="card-body p-4">
                            <?php renderStepIndicator($steps, $current_step); ?>
                            <?php renderMessages($errors, $success); ?>
                            <?php renderStepContent($current_step, $steps); ?>
                        </div>
                        <div class="card-footer text-center text-muted py-3">
                            <small>
                                <i class="fas fa-shield-alt me-1"></i>
                                <?php echo htmlspecialchars($appName); ?>
                                v<?php echo htmlspecialchars($appVersion); ?>
                                <?php if ($appBuild): ?>&nbsp;•&nbsp;build <?php echo htmlspecialchars($appBuild); ?><?php endif; ?>
                                &nbsp;&copy; <?php echo date('Y'); ?> |
                                <a href="https://github.com/PhPstrap/PhPstrap" target="_blank" class="text-decoration-none">
                                    <i class="fab fa-github me-1"></i>GitHub
                                </a>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            // Module selection toggle: click card to toggle the internal checkbox + visual state
            function toggleModule(card) {
                const checkbox = card.querySelector('input[type="checkbox"]');
                if (!checkbox) return;
                checkbox.checked = !checkbox.checked;
                card.classList.toggle('selected', checkbox.checked);
            }

            // Keep cards visually in sync if user clicks directly on the checkbox/label
            function syncModuleCards() {
                document.querySelectorAll('.module-card').forEach(card => {
                    const cb = card.querySelector('input[type="checkbox"]');
                    card.classList.toggle('selected', !!(cb && cb.checked));
                });
            }

            // Simple required field validation + admin password match
            function validateForm(formId) {
                const form = document.getElementById(formId);
                if (!form) return true;
                const requiredFields = form.querySelectorAll('input[required], select[required]');
                let isValid = true;

                requiredFields.forEach(field => {
                    if (!String(field.value || '').trim()) {
                        field.classList.add('is-invalid'); isValid = false;
                    } else {
                        field.classList.remove('is-invalid');
                    }
                });

                if (formId === 'adminForm') {
                    const pass = form.querySelector('input[name="admin_password"]');
                    const conf = form.querySelector('input[name="admin_confirm"]');
                    if (pass && conf && pass.value !== conf.value) {
                        isValid = false;
                        conf.classList.add('is-invalid');
                    } else if (conf) {
                        conf.classList.remove('is-invalid');
                    }
                }
                return isValid;
            }

            // Password strength indicator
            function updatePasswordStrength(password) {
                const strengthBar = document.getElementById('passwordStrength');
                if (!strengthBar) return;
                let strength = 0;
                if (password.length >= 8) strength += 25;
                if (/[a-z]/.test(password)) strength += 25;
                if (/[A-Z]/.test(password)) strength += 25;
                if (/[0-9]/.test(password) || /[^a-zA-Z0-9]/.test(password)) strength += 25;

                strengthBar.style.width = strength + '%';
                strengthBar.className = 'progress-bar';
                if (strength < 50) strengthBar.classList.add('bg-danger');
                else if (strength < 75) strengthBar.classList.add('bg-warning');
                else strengthBar.classList.add('bg-success');
            }

            // Tooltips + autofocus + card click handlers
            document.addEventListener('DOMContentLoaded', function() {
                // Tooltips
                const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                tooltipTriggerList.map(function (el) { return new bootstrap.Tooltip(el); });

                // Autofocus first text input
                const firstInput = document.querySelector('input[type="text"], input[type="email"], input[type="password"]');
                if (firstInput) firstInput.focus();

                // Card click binding (ignore clicks on actual inputs to prevent double toggles)
                document.querySelectorAll('.module-card').forEach(card => {
                    card.addEventListener('click', (e) => {
                        if (e.target && (e.target.tagName === 'INPUT' || e.target.tagName === 'LABEL')) return;
                        toggleModule(card);
                    });
                    // Keep state synced when user checks/unchecks directly
                    const cb = card.querySelector('input[type="checkbox"]');
                    if (cb) cb.addEventListener('change', syncModuleCards);
                });
                syncModuleCards();
            });

            // Auto-advance for configuration step
            <?php if ($current_step == 5 && empty($errors)): ?>
            (function(){
                let countdown = 3;
                const countdownElement = document.getElementById('countdown');
                const autoSubmitForm = document.getElementById('autoSubmitForm');
                const timer = setInterval(() => {
                    countdown--;
                    if (countdownElement) countdownElement.textContent = countdown;
                    if (countdown <= 0) {
                        clearInterval(timer);
                        if (autoSubmitForm) autoSubmitForm.submit();
                    }
                }, 1000);
            })();
            <?php endif; ?>
        </script>
    </body>
    </html>
    <?php
}

function renderStepIndicator($steps, $current_step) {
    $totalSteps = max(1, count($steps));
    $progress = $totalSteps > 1 ? (($current_step - 1) / ($totalSteps - 1)) * 100 : 100;
    ?>
    <div class="step-indicator text-center">
        <div class="d-flex justify-content-center align-items-center flex-wrap">
            <?php foreach ($steps as $num => $title): ?>
                <div class="step-item d-flex align-items-center">
                    <span class="step <?php echo $num < $current_step ? 'completed' : ($num == $current_step ? 'active' : ''); ?>"
                          data-bs-toggle="tooltip" title="<?php echo htmlspecialchars($title); ?>">
                        <?php echo $num < $current_step ? '<i class="fas fa-check"></i>' : (int)$num; ?>
                    </span>
                    <?php if ($num < count($steps)): ?>
                        <i class="fas fa-arrow-right mx-3 text-muted"></i>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="mt-4">
            <h4 class="mb-1"><?php echo htmlspecialchars($steps[$current_step]); ?></h4>
            <div class="progress" style="height: 8px;">
                <div class="progress-bar bg-primary" style="width: <?php echo (float)$progress; ?>%"></div>
            </div>
            <small class="text-muted">Step <?php echo (int)$current_step; ?> of <?php echo (int)$totalSteps; ?></small>
        </div>
    </div>
    <?php
}

function renderMessages($errors, $success) {
    if (!empty($errors) || !empty($success)) {
        echo '<div class="messages-container mb-4">';
        if (!empty($errors)) { ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Please fix the following issues:</strong>
                <ul class="mb-0 mt-2">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php }
        if (!empty($success)) { ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <strong>Success!</strong>
                <ul class="mb-0 mt-2">
                    <?php foreach ($success as $msg): ?>
                        <li><?php echo htmlspecialchars($msg); ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php }
        echo '</div>';
    }
}

function renderStepContent($current_step, $steps) {
    switch ($current_step) {
        case 1: renderRequirementsStep(); break;
        case 2: renderDatabaseStep(); break;
        case 3: renderAdminStep(); break;
        case 4: renderModuleStep(); break;
        case 5: renderConfigurationStep(); break;
        case 6: renderCompletionStep(); break;
    }
}

function renderRequirementsStep() {
    $requirements    = checkSystemRequirements();
    $phpInfo         = getPhpInfo();
    $optional        = checkOptionalRequirements();
    $recommendations = getRecommendations();

    $all_passed = true;
    foreach ($requirements as $passed) { if (!$passed) { $all_passed = false; break; } }
    ?>
    <div class="requirements-step">
        <div class="row">
            <div class="col-lg-8">
                <div class="mb-4">
                    <h5><i class="fas fa-clipboard-check text-primary me-2"></i>System Requirements</h5>
                    <p class="text-muted">Checking if your server meets the minimum requirements for PhPstrap...</p>

                    <?php foreach ($requirements as $req => $passed): ?>
                        <div class="requirement <?php echo $passed ? 'pass' : 'fail'; ?>">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>
                                    <i class="fas <?php echo $passed ? 'fa-check text-success' : 'fa-times text-danger'; ?> me-2"></i>
                                    <?php echo htmlspecialchars($req); ?>
                                </span>
                                <?php if (!$passed): ?><small class="text-danger">Required</small><?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="mb-4">
                    <h6><i class="fas fa-plus-circle text-warning me-2"></i>Optional Features</h6>
                    <?php foreach ($optional as $feature => $available): ?>
                        <div class="requirement <?php echo $available ? 'pass' : 'optional'; ?>">
                            <i class="fas <?php echo $available ? 'fa-check text-success' : 'fa-info-circle text-warning'; ?> me-2"></i>
                            <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $feature))); ?>
                            <small class="text-muted ms-2">(Optional)</small>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if (!empty($recommendations)): ?>
                <div class="alert alert-info">
                    <h6><i class="fas fa-lightbulb me-2"></i>Recommendations</h6>
                    <ul class="mb-0">
                        <?php foreach ($recommendations as $rec): ?>
                            <li>
                                <strong><?php echo htmlspecialchars($rec['title']); ?>:</strong>
                                <?php echo htmlspecialchars($rec['message']); ?>
                                <?php if (($rec['priority'] ?? '') === 'high'): ?>
                                    <span class="badge bg-danger ms-2">High Priority</span>
                                <?php elseif (($rec['priority'] ?? '') === 'medium'): ?>
                                    <span class="badge bg-warning ms-2">Medium Priority</span>
                                <?php else: ?>
                                    <span class="badge bg-info ms-2">Low Priority</span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <?php if ($all_passed): ?>
                    <form method="post" class="mt-4">
                        <input type="hidden" name="step" value="2">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-arrow-right me-2"></i>Continue to Database Setup
                        </button>
                    </form>
                <?php else: ?>
                    <div class="alert alert-danger">
                        <strong><i class="fas fa-exclamation-triangle me-2"></i>Requirements Not Met</strong>
                        <p class="mb-0">Please resolve the failed requirements before continuing. Contact your hosting provider if you need assistance.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="col-lg-4">
                <div class="card bg-light">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Server Information</h6>
                    </div>
                    <div class="card-body">
                        <small>
                            <strong>PHP Version:</strong> <?php echo htmlspecialchars($phpInfo['version']); ?><br>
                            <strong>Server API:</strong> <?php echo htmlspecialchars($phpInfo['sapi']); ?><br>
                            <strong>Memory Limit:</strong> <?php echo htmlspecialchars($phpInfo['memory_limit']); ?><br>
                            <strong>Max Execution:</strong> <?php echo htmlspecialchars($phpInfo['max_execution_time']); ?>s<br>
                            <strong>Upload Limit:</strong> <?php echo htmlspecialchars($phpInfo['upload_max_filesize']); ?><br>
                            <strong>Post Limit:</strong> <?php echo htmlspecialchars($phpInfo['post_max_size']); ?><br>
                            <strong>Timezone:</strong> <?php echo htmlspecialchars($phpInfo['date_timezone']); ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

function renderDatabaseStep() { ?>
    <div class="database-step">
        <div class="alert alert-info">
            <i class="fas fa-database me-2"></i>
            <strong>Database Configuration:</strong> We'll test your connection and create the database if it doesn't exist.
        </div>

        <form method="post" id="databaseForm" onsubmit="return validateForm('databaseForm')">
            <input type="hidden" name="step" value="2">

            <div class="row">
                <div class="col-md-8">
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-server me-1"></i>Database Host</label>
                        <input type="text" name="db_host" class="form-control"
                               value="<?php echo isset($_POST['db_host']) ? htmlspecialchars($_POST['db_host']) : 'localhost'; ?>"
                               required placeholder="localhost">
                        <div class="form-text">Usually "localhost" for most hosting providers</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-plug me-1"></i>Port</label>
                        <input type="number" name="db_port" class="form-control"
                               value="<?php echo isset($_POST['db_port']) ? htmlspecialchars($_POST['db_port']) : '3306'; ?>"
                               required min="1" max="65535">
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label"><i class="fas fa-database me-1"></i>Database Name</label>
                <input type="text" name="db_name" class="form-control"
                       value="<?php echo isset($_POST['db_name']) ? htmlspecialchars($_POST['db_name']) : 'PhPstrap'; ?>"
                       required pattern="[a-zA-Z0-9_]+" placeholder="PhPstrap">
                <div class="form-text">Database will be created automatically if it doesn't exist</div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-user me-1"></i>Username</label>
                        <input type="text" name="db_user" class="form-control"
                               value="<?php echo isset($_POST['db_user']) ? htmlspecialchars($_POST['db_user']) : ''; ?>"
                               required autocomplete="off">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-lock me-1"></i>Password</label>
                        <input type="password" name="db_pass" class="form-control"
                               value="<?php echo isset($_POST['db_pass']) ? htmlspecialchars($_POST['db_pass']) : ''; ?>"
                               autocomplete="new-password">
                        <div class="form-text">Leave empty if no password is required</div>
                    </div>
                </div>
            </div>

            <div class="d-grid">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-plug me-2"></i>Test Database Connection
                </button>
            </div>
        </form>
    </div>
<?php }

function renderAdminStep() { ?>
    <div class="admin-step">
        <div class="alert alert-success">
            <i class="fas fa-check-circle me-2"></i>
            <strong>Database Connected!</strong> Now let's create your administrator account and configure your site.
        </div>

        <form method="post" id="adminForm" onsubmit="return validateForm('adminForm')">
            <input type="hidden" name="step" value="3">

            <div class="row">
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-globe me-2"></i>Site Configuration</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Site Name</label>
                                <input type="text" name="site_name" class="form-control"
                                       value="<?php echo isset($_POST['site_name']) ? htmlspecialchars($_POST['site_name']) : 'PhPstrap'; ?>"
                                       required maxlength="100">
                                <div class="form-text">This will appear in the browser title and throughout the site</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-user-shield me-2"></i>Administrator Account</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Full Name</label>
                                <input type="text" name="admin_name" class="form-control"
                                       value="<?php echo isset($_POST['admin_name']) ? htmlspecialchars($_POST['admin_name']) : ''; ?>"
                                       required maxlength="100">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Email Address</label>
                                <input type="email" name="admin_email" class="form-control"
                                       value="<?php echo isset($_POST['admin_email']) ? htmlspecialchars($_POST['admin_email']) : ''; ?>"
                                       required maxlength="100">
                                <div class="form-text">This will be used for system notifications</div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Password</label>
                                        <input type="password" name="admin_password" class="form-control"
                                               required minlength="8" onkeyup="updatePasswordStrength(this.value)">
                                        <div class="progress mt-2" style="height: 4px;">
                                            <div id="passwordStrength" class="progress-bar" style="width: 0%"></div>
                                        </div>
                                        <div class="form-text">Minimum 8 characters recommended</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Confirm Password</label>
                                        <input type="password" name="admin_confirm" class="form-control"
                                               required minlength="8">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-4 d-grid">
                <button type="submit" class="btn btn-success btn-lg">
                    <i class="fas fa-rocket me-2"></i>Install Database & Create Account
                </button>
            </div>
        </form>
    </div>
<?php }

function renderModuleStep() {
    // Preserve selection after validation errors
    $selected = isset($_POST['modules']) && is_array($_POST['modules']) ? array_map('strval', $_POST['modules']) : [];
    $isChecked = function($name) use ($selected) { return in_array($name, $selected, true) ? 'checked' : ''; };
    $isSelected = function($name) use ($selected) { return in_array($name, $selected, true) ? ' selected' : ''; };
    ?>
    <div class="module-step">
        <div class="alert alert-info">
            <i class="fas fa-puzzle-piece me-2"></i>
            <strong>Module Selection:</strong> Choose which modules to install. You can enable/disable these later in the admin panel.
        </div>

        <form method="post" id="moduleForm">
            <input type="hidden" name="step" value="4">

            <div class="row g-4">
                <div class="col-md-6 col-lg-4">
                    <div class="module-card card h-100<?php echo $isSelected('hcaptcha'); ?>">
                        <div class="card-body text-center p-4">
                            <div class="module-icon bg-primary text-white"><i class="fas fa-shield-alt"></i></div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="modules[]" value="hcaptcha" id="module_hcaptcha" <?php echo $isChecked('hcaptcha'); ?>>
                                <label class="form-check-label w-100" for="module_hcaptcha">
                                    <h6 class="mb-2">hCaptcha Protection</h6>
                                    <small class="text-muted">Add spam protection to registration and login forms using hCaptcha service</small>
                                </label>
                            </div>
                            <div class="mt-3">
                                <span class="badge bg-light text-dark">Security</span>
                                <span class="badge bg-light text-dark">Forms</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4">
                    <div class="module-card card h-100<?php echo $isSelected('smtp'); ?>">
                        <div class="card-body text-center p-4">
                            <div class="module-icon bg-success text-white"><i class="fas fa-envelope"></i></div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="modules[]" value="smtp" id="module_smtp" <?php echo $isChecked('smtp'); ?>>
                                <label class="form-check-label w-100" for="module_smtp">
                                    <h6 class="mb-2">SMTP Email</h6>
                                    <small class="text-muted">Send professional emails via SMTP instead of PHP mail() function</small>
                                </label>
                            </div>
                            <div class="mt-3">
                                <span class="badge bg-light text-dark">Email</span>
                                <span class="badge bg-light text-dark">Communication</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4">
                    <div class="module-card card h-100<?php echo $isSelected('analytics'); ?>">
                        <div class="card-body text-center p-4">
                            <div class="module-icon bg-warning text-white"><i class="fas fa-chart-line"></i></div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="modules[]" value="analytics" id="module_analytics" <?php echo $isChecked('analytics'); ?>>
                                <label class="form-check-label w-100" for="module_analytics">
                                    <h6 class="mb-2">Analytics Tracking</h6>
                                    <small class="text-muted">Google Analytics and Facebook Pixel integration for tracking</small>
                                </label>
                            </div>
                            <div class="mt-3">
                                <span class="badge bg-light text-dark">Analytics</span>
                                <span class="badge bg-light text-dark">Tracking</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Placeholder / future modules -->
                <div class="col-md-6 col-lg-4">
                    <div class="module-card card h-100 opacity-75">
                        <div class="card-body text-center p-4">
                            <div class="module-icon bg-secondary text-white"><i class="fas fa-plus"></i></div>
                            <h6 class="mb-2">More Modules</h6>
                            <small class="text-muted">Additional modules can be installed later through the admin panel</small>
                            <div class="mt-3"><span class="badge bg-light text-dark">Coming Soon</span></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-4">
                <div class="alert alert-light border">
                    <h6><i class="fas fa-info-circle text-info me-2"></i>Module Information</h6>
                    <ul class="mb-0 small">
                        <li>Modules can be enabled/disabled anytime from the admin panel</li>
                        <li>Each module has its own configuration settings</li>
                        <li>Modules are designed to work independently</li>
                        <li>You can install additional modules later</li>
                    </ul>
                </div>
            </div>

            <div class="d-grid">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-puzzle-piece me-2"></i>Continue with Selected Modules
                </button>
            </div>
        </form>
    </div>
    <?php
}

function renderConfigurationStep() { ?>
    <div class="configuration-step text-center">
        <div class="mb-4">
            <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <h5>Finalizing Installation...</h5>
            <p class="text-muted">Creating configuration files and setting up your PhPstrap installation</p>
        </div>

        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="list-group list-group-flush">
                    <div class="list-group-item border-0 d-flex align-items-center">
                        <i class="fas fa-check-circle text-success me-3"></i>
                        <span>Creating configuration files...</span>
                    </div>
                    <div class="list-group-item border-0 d-flex align-items-center">
                        <i class="fas fa-check-circle text-success me-3"></i>
                        <span>Setting up security files...</span>
                    </div>
                    <div class="list-group-item border-0 d-flex align-items-center">
                        <i class="fas fa-check-circle text-success me-3"></i>
                        <span>Installing selected modules...</span>
                    </div>
                    <div class="list-group-item border-0 d-flex align-items-center">
                        <i class="fas fa-spinner fa-spin text-primary me-3"></i>
                        <span>Optimizing system performance...</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-4">
            <p class="text-muted">
                Auto-continuing in <span id="countdown" class="fw-bold text-primary">3</span> seconds...
            </p>
            <form method="post" id="autoSubmitForm">
                <input type="hidden" name="step" value="5">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-cogs me-2"></i>Complete Setup Now
                </button>
            </form>
        </div>
    </div>
<?php }

function renderCompletionStep() { ?>
    <div class="install-complete">
        <div class="success-icon"><i class="fas fa-check-circle"></i></div>
        <h2 class="text-success mb-3">Installation Complete!</h2>
        <p class="lead mb-4">PhPstrap has been successfully installed and configured on your server.</p>

        <div class="row justify-content-center">
            <div class="col-lg-10">

                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <i class="fas fa-check-circle fa-2x mb-2"></i>
                                <h6>System Installed</h6>
                                <small>Database schema and core files ready</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <i class="fas fa-user-shield fa-2x mb-2"></i>
                                <h6>Admin Account Created</h6>
                                <small>You can now log in and manage your site</small>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (isset($_SESSION['admin_info'])): ?>
                <div class="alert alert-info text-start">
                    <h6><i class="fas fa-user-shield me-2"></i>Administrator Account Details</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>ID:</strong> <?php echo htmlspecialchars($_SESSION['admin_info']['id']); ?></p>
                            <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($_SESSION['admin_info']['name']); ?></p>
                            <p class="mb-0"><strong>Email:</strong> <?php echo htmlspecialchars($_SESSION['admin_info']['email']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1"><strong>API Token:</strong></p>
                            <code class="small d-block p-2 bg-light text-dark rounded" style="word-break: break-all;">
                                <?php echo htmlspecialchars($_SESSION['admin_info']['api_token']); ?>
                            </code>
                            <small class="text-muted">Save this token securely - you won't see it again!</small>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="alert alert-warning text-start">
                    <h6><i class="fas fa-shield-alt me-2"></i>Important Security Steps</h6>
                    <ol class="mb-0">
                        <li><strong>Remove installer files</strong> - Click the button below to clean up</li>
                        <li><strong>Set proper file permissions</strong> - 644 for files, 755 for directories</li>
                        <li><strong>Enable HTTPS</strong> - Use SSL certificate for secure connections</li>
                        <li><strong>Regular backups</strong> - Set up automated database backups</li>
                        <li><strong>Keep updated</strong> - Check for PhPstrap updates regularly</li>
                    </ol>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <form method="post" class="d-grid">
                            <input type="hidden" name="step" value="6">
                            <input type="hidden" name="remove_installer" value="1">
                            <button type="submit" class="btn btn-danger btn-lg"
                                    onclick="return confirm('This will remove all installer files and redirect to the login page. Continue?')">
                                <i class="fas fa-trash me-2"></i>Remove Installer & Go to Login
                            </button>
                        </form>
                    </div>
                    <div class="col-md-6">
                        <a href="admin/" class="btn btn-primary btn-lg d-block">
                            <i class="fas fa-sign-in-alt me-2"></i>Go to Dashboard
                        </a>
                    </div>
                </div>

                <div class="mt-4 pt-4 border-top">
                    <h6 class="text-muted">Next Steps:</h6>
                    <div class="row text-start">
                        <div class="col-md-6">
                            <ul class="list-unstyled">
                                <li><i class="fas fa-cog text-primary me-2"></i>Configure modules in admin panel</li>
                                <li><i class="fas fa-palette text-primary me-2"></i>Customize site appearance</li>
                                <li><i class="fas fa-users text-primary me-2"></i>Set up user registration settings</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <ul class="list-unstyled">
                                <li><i class="fas fa-envelope text-primary me-2"></i>Configure email settings</li>
                                <li><i class="fas fa-shield-alt text-primary me-2"></i>Set up hCaptcha protection</li>
                                <li><i class="fas fa-chart-line text-primary me-2"></i>Enable analytics tracking</li>
                            </ul>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
<?php }
?>