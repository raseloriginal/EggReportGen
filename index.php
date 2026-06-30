<?php
session_start();
$db_file = __DIR__ . '/database.json';
$correct_pin = '13579';

// Handle Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . strtok($_SERVER["REQUEST_URI"], '?'));
    exit;
}

// Handle PIN submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pin'])) {
    if ($_POST['pin'] === $correct_pin) {
        $_SESSION['authenticated'] = true;
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    } else {
        $error = 'Invalid PIN code. Please try again.';
    }
}

// Handle GET request to retrieve logs
if (isset($_GET['action']) && $_GET['action'] === 'get_logs') {
    header('Content-Type: application/json');
    if (empty($_SESSION['authenticated'])) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }
    $log_file = __DIR__ . '/logs.json';
    if (file_exists($log_file)) {
        echo file_get_contents($log_file);
    } else {
        echo json_encode([]);
    }
    exit;
}

// Handle API POST request (saving data) - must be authenticated
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['pin'])) {
    header('Content-Type: application/json');
    if (empty($_SESSION['authenticated'])) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }
    
    $input = file_get_contents('php://input');
    $decoded = json_decode($input, true);
    if ($decoded !== null) {
        // Handle Undo Action
        if (isset($decoded['action']) && $decoded['action'] === 'undo') {
            $timestamp = isset($decoded['timestamp']) ? $decoded['timestamp'] : '';
            $log_file = __DIR__ . '/logs.json';
            if (file_exists($log_file)) {
                $logs = json_decode(file_get_contents($log_file), true) ?: [];
                $found_idx = -1;
                foreach ($logs as $idx => $log) {
                    if (isset($log['timestamp']) && $log['timestamp'] === $timestamp) {
                        $found_idx = $idx;
                        break;
                    }
                }
                if ($found_idx !== -1 && isset($logs[$found_idx]['previous_state'])) {
                    $previous_state = $logs[$found_idx]['previous_state'];
                    file_put_contents($db_file, json_encode($previous_state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    
                    $undo_log = [
                        'action' => 'Undo',
                        'details' => 'Undid action: ' . $logs[$found_idx]['details'],
                        'timestamp' => date('Y-m-d H:i:s')
                    ];
                    array_unshift($logs, $undo_log);
                    file_put_contents($log_file, json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    
                    echo json_encode(['status' => 'success', 'message' => 'Undo successful', 'data' => $previous_state]);
                    exit;
                }
            }
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Log entry or previous state not found']);
            exit;
        }

        $data_to_save = isset($decoded['data']) ? $decoded['data'] : $decoded;
        $log_entry = isset($decoded['log']) ? $decoded['log'] : null;
        
        // Capture previous state before saving
        $previous_state = null;
        if (file_exists($db_file)) {
            $previous_state = json_decode(file_get_contents($db_file), true);
        }
        
        if (file_put_contents($db_file, json_encode($data_to_save, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            if ($log_entry) {
                $log_entry['previous_state'] = $previous_state;
                $log_file = __DIR__ . '/logs.json';
                $logs = [];
                if (file_exists($log_file)) {
                    $logs = json_decode(file_get_contents($log_file), true) ?: [];
                }
                $log_entry['timestamp'] = date('Y-m-d H:i:s');
                array_unshift($logs, $log_entry);
                $logs = array_slice($logs, 0, 500);
                file_put_contents($log_file, json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
            echo json_encode(['status' => 'success', 'message' => 'Data saved successfully']);
            exit;
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to write to database file']);
            exit;
        }
    } else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid JSON data']);
        exit;
    }
}

// If not authenticated, show PIN entry screen
if (empty($_SESSION['authenticated'])) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Secure Access — Egg Report</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: radial-gradient(circle at top, #1e293b, #0f172a);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #f3f4f6;
            }
            .pin-container {
                background: rgba(30, 41, 59, 0.7);
                backdrop-filter: blur(16px);
                border: 1px solid rgba(255, 255, 255, 0.1);
                padding: 40px;
                border-radius: 24px;
                width: 100%;
                max-width: 400px;
                box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5), 0 10px 10px -5px rgba(0, 0, 0, 0.5);
                text-align: center;
            }
            .lock-icon {
                font-size: 48px;
                color: #fbbf24;
                margin-bottom: 20px;
                animation: pulse 2s infinite;
            }
            @keyframes pulse {
                0%, 100% { transform: scale(1); }
                50% { transform: scale(1.05); }
            }
            h2 {
                font-size: 24px;
                margin-bottom: 8px;
                font-weight: 600;
                letter-spacing: -0.5px;
            }
            p {
                font-size: 14px;
                color: #94a3b8;
                margin-bottom: 24px;
            }
            .pin-input-group {
                position: relative;
                margin-bottom: 20px;
            }
            .pin-input {
                width: 100%;
                padding: 16px 20px;
                font-size: 24px;
                letter-spacing: 8px;
                text-align: center;
                border-radius: 12px;
                border: 1px solid rgba(255, 255, 255, 0.2);
                background: rgba(15, 23, 42, 0.6);
                color: #fff;
                outline: none;
                transition: border-color 0.2s, box-shadow 0.2s;
            }
            .pin-input:focus {
                border-color: #3b82f6;
                box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3);
            }
            .btn-submit {
                width: 100%;
                padding: 14px;
                border: none;
                border-radius: 12px;
                background: linear-gradient(135deg, #3b82f6, #2563eb);
                color: #fff;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                transition: transform 0.1s, filter 0.2s;
            }
            .btn-submit:hover {
                filter: brightness(1.1);
            }
            .btn-submit:active {
                transform: scale(0.98);
            }
            .error-msg {
                color: #ef4444;
                font-size: 13px;
                margin-top: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 6px;
            }
        </style>
    </head>
    <body>
        <div class="pin-container">
            <div class="lock-icon"><i class="fa-solid fa-lock"></i></div>
            <h2>Security Required</h2>
            <p>Enter the PIN code to access the Egg Report system</p>
            <form method="POST">
                <div class="pin-input-group">
                    <input type="password" name="pin" class="pin-input" placeholder="•••••" maxlength="10" autofocus required autocomplete="off">
                </div>
                <button type="submit" class="btn-submit">Unlock System</button>
                <?php if (isset($error)): ?>
                    <div class="error-msg"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Load data for initial page render
$initial_data = [
    'agentCounter' => 0,
    'agents' => []
];

if (file_exists($db_file)) {
    $file_content = file_get_contents($db_file);
    $decoded_file = json_decode($file_content, true);
    if ($decoded_file !== null) {
        $initial_data = $decoded_file;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Egg Report — Multi Agent</title>
    <!-- FontAwesome for Premium Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: Arial, sans-serif;
            background-color: #f0f2f5;
            padding: 20px;
        }

        /* ── HEADER ── */
        .main-header { text-align: center; margin-bottom: 18px; }
        .main-header h1 { font-size: 22px; color: #1a1a2e; }
        .main-header p { color: #666; font-size: 12px; margin-top: 4px; }

        /* ── TABS BAR ── */
        .tabs-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 2px;
            max-width: 1200px;
            margin: 0 auto 0 auto;
            padding: 8px 10px 0 10px;
            background: #111827;
            align-items: center;
            border-bottom: 3px solid #374151;
        }
        .tab-btn {
            padding: 8px 16px;
            border: none;
            cursor: pointer;
            font-size: 13px;
            font-weight: bold;
            background: #1f2937;
            color: #9ca3af;
            transition: background 0.15s, color 0.15s;
            white-space: nowrap;
        }
        .tab-btn:hover { background: #374151; color: #f3f4f6; }
        .tab-btn.active { background: #f3f4f6; color: #111827; }
        .summary-tab-btn { background: #0f172a; color: #38bdf8; border-bottom: 2px solid #38bdf8; }
        .summary-tab-btn.active { background: #f3f4f6; color: #0f172a; border-bottom: none; }
        .btn-add-agent {
            padding: 8px 16px;
            background: #059669;
            color: white;
            border: none;
            cursor: pointer;
            font-size: 13px;
            font-weight: bold;
            margin-left: auto;
            margin-bottom: 0;
        }
        .btn-add-agent:hover { background: #047857; }

        /* ── SAVE INDICATOR ── */
        #save-indicator {
            font-size: 11px;
            color: #34d399;
            margin-left: 10px;
            opacity: 0;
            transition: opacity 0.4s;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        /* ── PAGE WRAPPER ── */
        .page-wrapper {
            max-width: 1200px;
            margin: 0 auto;
            background: #f3f4f6;
            box-shadow: none;
            padding: 20px;
            min-height: 400px;
            border: 1px solid #d1d5db;
            border-top: none;
        }

        /* ── PANELS ── */
        .agent-panel, .summary-panel { display: none; }
        .agent-panel.active, .summary-panel.active { display: block; }

        /* ── AGENT HEADER ── */
        .agent-panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 2px solid #d1d5db;
        }
        .agent-panel-header h2 {
            font-size: 18px;
            color: #111827;
        }
        .btn-delete-agent {
            background: #dc2626;
            color: white;
            border: none;
            padding: 6px 14px;
            cursor: pointer;
            font-size: 12px;
        }
        .btn-delete-agent:hover { background: #b91c1c; }

        /* ── MONTH BLOCK ── */
        .month-block {
            margin-bottom: 22px;
            border: 1px solid #d1d5db;
            background: #fff;
        }
        .month-title {
            background: #e5e7eb;
            padding: 9px 14px;
            font-size: 14px;
            font-weight: bold;
            border-bottom: 1px solid #d1d5db;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: #1f2937;
        }
        .btn-delete-month {
            background: #dc2626;
            color: white;
            border: none;
            padding: 4px 10px;
            cursor: pointer;
            font-size: 11px;
        }
        .btn-delete-month:hover { background: #b91c1c; }

        /* ── TABLE ── */
        table { width: 100%; border-collapse: collapse; }
        th, td {
            border: 1px solid #d1d5db;
            padding: 6px 8px;
            text-align: right;
            font-size: 13px;
        }
        th {
            background: #f3f4f6;
            text-align: center;
            font-size: 12px;
            color: #374151;
            font-weight: bold;
        }
        td:first-child, td:nth-child(2) { text-align: left; }
        .totals { font-weight: bold; background: #e5e7eb; }

        input[type="text"], input[type="number"], input[type="date"] {
            width: 95%;
            padding: 3px 5px;
            border: 1px solid #d1d5db;
            font-size: 12px;
            text-align: right;
            background: #fff;
        }
        input[type="text"].left-align { text-align: left; }

        /* date picker full-width in cell */
        input[type="date"].inp-date {
            width: 100%;
            min-width: 110px;
            padding: 3px 4px;
            font-size: 12px;
            cursor: pointer;
            text-align: left;
        }

        /* product dropdown */
        select.inp-product {
            width: 100%;
            padding: 3px 5px;
            border: 1px solid #d1d5db;
            font-size: 12px;
            background: #fff;
            cursor: pointer;
            color: #374151;
        }

        /* ── BUTTONS ── */
        .btn-add-row {
            margin: 8px 12px 10px;
            padding: 6px 12px;
            background: #2563eb;
            color: white;
            border: none;
            cursor: pointer;
            font-size: 12px;
        }
        .btn-add-row:hover { background: #1d4ed8; }

        .btn-add-month {
            display: block;
            margin: 10px 0 20px 0;
            padding: 10px;
            background: #059669;
            color: white;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            width: 100%;
        }
        .btn-add-month:hover { background: #047857; }

        .btn-del-row {
            background: #dc2626;
            color: white;
            border: none;
            padding: 3px 8px;
            cursor: pointer;
            font-size: 11px;
        }
        .btn-del-row:hover { background: #b91c1c; }

        /* ── SUMMARY ── */
        .summary-panel h2 { font-size: 20px; color: #111827; margin-bottom: 15px; display: flex; align-items: center; gap: 8px; }
        .refresh-btn {
            display: inline-block;
            margin-bottom: 14px;
            padding: 8px 20px;
            background: #4b5563;
            color: white;
            border: none;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: background 0.15s;
        }
        .refresh-btn:hover { background: #374151; }

        .clear-all-btn {
            display: inline-block;
            margin-bottom: 14px;
            margin-left: 10px;
            padding: 8px 20px;
            background: #991b1b;
            color: white;
            border: none;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: background 0.15s;
        }
        .clear-all-btn:hover { background: #7f1d1d; }

        /* KPI Dashboard Cards */
        .kpi-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .kpi-card {
            background: #fff;
            border: 1px solid #d1d5db;
            padding: 18px;
            box-shadow: none;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .kpi-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 4px; height: 100%;
        }
        .kpi-card.kpi-qty::before { background: #2563eb; }
        .kpi-card.kpi-price::before { background: #4b5563; }
        .kpi-card.kpi-deposit::before { background: #059669; }
        .kpi-card.kpi-due::before { background: #d97706; }
        .kpi-card.kpi-due.neg::before { background: #dc2626; }

        .kpi-label {
            font-size: 11px;
            color: #6b7280;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        .kpi-value {
            font-size: 22px;
            font-weight: bold;
            color: #111827;
        }

        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 28px;
            border: 1px solid #d1d5db;
            box-shadow: none;
        }
        .summary-table th {
            background: #1f2937;
            color: #fff;
            padding: 12px;
            text-align: center;
            font-size: 13px;
            font-weight: 600;
            border: 1px solid #4b5563;
        }
        .summary-table td {
            border: 1px solid #d1d5db;
            padding: 12px 15px;
            text-align: right;
            font-size: 13px;
            color: #374151;
        }
        .summary-table td:first-child { text-align: left; font-weight: 600; color: #111827; }
        .summary-table tr:nth-child(even) { background: #f9fafb; }
        .summary-grand { background: #1f2937 !important; }
        .summary-grand td { color: #fff !important; border: 1px solid #4b5563; font-weight: bold; }

        .due-neg { color: #b91c1c; font-weight: bold; }
        .due-pos { color: #047857; font-weight: bold; }

        .section-title {
            font-size: 14px;
            font-weight: bold;
            margin: 24px 0 12px;
            color: #111827;
            border-left: 4px solid #4b5563;
            padding-left: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .no-data-msg {
            text-align: center;
            color: #999;
            padding: 40px;
            font-size: 14px;
        }

        .view-agent-btn:hover { background: #1a1a2e; }

        /* ── LOGS ── */
        .logs-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .logs-table th {
            background: #374151;
            color: #fff;
            padding: 10px;
            font-size: 13px;
            text-align: left;
        }
        .logs-table td {
            padding: 10px;
            border-bottom: 1px solid #d1d5db;
            font-size: 13px;
            color: #374151;
            text-align: left;
        }
        .logs-table tr:nth-child(even) { background: #f9fafb; }
        .log-badge {
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge-edit { background: #d97706; color: #fff; }
        .badge-delete { background: #dc2626; color: #fff; }
        .badge-add { background: #059669; color: #fff; }

        /* ── CUSTOM POPUP / MODAL ── */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(8px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s ease-in-out;
        }
        .modal-overlay.active {
            opacity: 1;
            pointer-events: auto;
        }
        .modal-box {
            background: #fff;
            padding: 24px;
            border-radius: 16px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            transform: scale(0.9);
            transition: transform 0.2s ease-in-out;
            border: 1px solid #e2e8f0;
            color: #1e293b;
            text-align: left;
        }
        .modal-overlay.active .modal-box {
            transform: scale(1);
        }
        .modal-header {
            font-size: 18px;
            font-weight: bold;
            color: #1e293b;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .modal-body {
            font-size: 14px;
            color: #475569;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        .modal-input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 14px;
            margin-top: 10px;
            outline: none;
            box-sizing: border-box;
            background: #fff;
            color: #1e293b;
            text-align: left;
        }
        .modal-input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        }
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }
        .modal-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.15s;
        }
        .modal-btn-confirm {
            background: #dc2626;
            color: #fff;
        }
        .modal-btn-confirm:hover {
            background: #b91c1c;
        }
        .modal-btn-primary {
            background: #2563eb;
            color: #fff;
        }
        .modal-btn-primary:hover {
            background: #1d4ed8;
        }
        .modal-btn-cancel {
            background: #f3f4f6;
            color: #4b5563;
            border: 1px solid #d1d5db;
        }
        .modal-btn-cancel:hover {
            background: #e5e7eb;
        }

        /* ── FILTER TOGGLE BUTTONS ── */
        .filter-toggle-btn {
            padding: 6px 14px;
            font-size: 12px;
            font-weight: 600;
            border: none;
            background: transparent;
            color: #475569;
            cursor: pointer;
            border-radius: 6px;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            white-space: nowrap;
        }
        .filter-toggle-btn:hover {
            color: #0f172a;
        }
        .filter-toggle-btn.active {
            background: #ffffff;
            color: #2563eb;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1), 0 1px 2px rgba(0, 0, 0, 0.06);
        }
    </style>
</head>
<body>

<div class="main-header">
    <h1><i class="fa-solid fa-egg" style="color: #d97706; margin-right: 6px;"></i> Egg Report — Multi Agent</h1>
    <p>All data is saved automatically to the server database</p>
</div>

<div class="tabs-bar" id="tabs-bar">
    <button class="tab-btn summary-tab-btn active" id="tab-summary" onclick="switchTab('summary')"><i class="fa-solid fa-chart-line"></i> Grand Summary</button>
    <button class="tab-btn" id="tab-logs" onclick="switchTab('logs')"><i class="fa-solid fa-clock-rotate-left"></i> Data Logs</button>
    <span id="save-indicator"><i class="fa-solid fa-circle-check"></i> Saved</span>
    <button class="btn-add-agent" onclick="promptAddAgent()"><i class="fa-solid fa-user-plus"></i> Add Agent</button>
    <a href="?logout=1" style="text-decoration: none; margin-left: 10px;"><button class="btn-add-agent" style="background: #4b5563; margin-left: 0;"><i class="fa-solid fa-right-from-bracket"></i> Logout</button></a>
</div>

<div class="page-wrapper" id="page-wrapper">

    <!-- GRAND SUMMARY PANEL -->
    <div class="summary-panel active" id="panel-summary">
        <h2><i class="fa-solid fa-chart-simple"></i> Grand Summary — All Agents</h2>
        <button class="refresh-btn" onclick="renderSummary()"><i class="fa-solid fa-rotate"></i> Refresh</button>
        <button class="clear-all-btn" onclick="clearAllData()"><i class="fa-solid fa-trash-can"></i> Clear All Data</button>
        <div id="summary-content">
            <p class="no-data-msg">No agents yet. Click <strong><i class="fa-solid fa-user-plus"></i> Add Agent</strong> to begin.</p>
        </div>
    </div>

    <!-- DATA LOGS PANEL -->
    <div class="summary-panel" id="panel-logs">
        <h2><i class="fa-solid fa-clock-rotate-left"></i> System Activity Logs</h2>
        <div style="display: flex; gap: 12px; align-items: center; margin-bottom: 16px; flex-wrap: wrap;">
            <button class="refresh-btn" onclick="loadAndRenderLogs()" style="margin-bottom: 0; border-radius: 8px;"><i class="fa-solid fa-rotate"></i> Refresh Logs</button>
            <div style="display: inline-flex; background: #e2e8f0; padding: 4px; border-radius: 8px; border: 1px solid #cbd5e1;">
                <button id="btn-log-filter-all" class="filter-toggle-btn active" onclick="setLogFilter('all')">
                    <i class="fa-solid fa-list-ul" style="margin-right: 6px;"></i>All Logs
                </button>
                <button id="btn-log-filter-delete" class="filter-toggle-btn" onclick="setLogFilter('delete')">
                    <i class="fa-solid fa-trash-can" style="margin-right: 6px;"></i>Only Deletes
                </button>
            </div>
        </div>
        <div id="logs-content">
            <p class="no-data-msg">Loading logs...</p>
        </div>
    </div>

    <!-- Agent panels injected by JS -->

</div>

<script>
// ═══════════════════════════════════════════════════════
//  DATA MODEL
//  data = { agentCounter: N, agents: [ { id, name, months: [ { id, name, rows: [ { date, product, qty, rate, deposit } ] } ] } ] }
// ═══════════════════════════════════════════════════════

let data = <?php echo json_encode($initial_data, JSON_UNESCAPED_UNICODE); ?>;
let activeTab = 'summary';

// ── CUSTOM MODAL POPUPS ───────────────────────────────
function showCustomAlert(title, message) {
    return new Promise((resolve) => {
        const overlay = document.createElement('div');
        overlay.className = 'modal-overlay active';
        overlay.innerHTML = `
            <div class="modal-box">
                <div class="modal-header"><i class="fa-solid fa-circle-info" style="color: #3b82f6;"></i> ${title}</div>
                <div class="modal-body">${message}</div>
                <div class="modal-footer">
                    <button class="modal-btn modal-btn-primary" id="custom-alert-ok">OK</button>
                </div>
            </div>
        `;
        document.body.appendChild(overlay);
        document.getElementById('custom-alert-ok').onclick = () => {
            overlay.remove();
            resolve();
        };
    });
}

function showCustomConfirm(title, message, isDanger = false) {
    return new Promise((resolve) => {
        const overlay = document.createElement('div');
        overlay.className = 'modal-overlay active';
        const confirmBtnClass = isDanger ? 'modal-btn-confirm' : 'modal-btn-primary';
        overlay.innerHTML = `
            <div class="modal-box">
                <div class="modal-header">
                    <i class="fa-solid ${isDanger ? 'fa-triangle-exclamation' : 'fa-circle-question'}" style="color: ${isDanger ? '#ef4444' : '#3b82f6'};"></i>
                    ${title}
                </div>
                <div class="modal-body">${message}</div>
                <div class="modal-footer">
                    <button class="modal-btn modal-btn-cancel" id="custom-confirm-cancel">Cancel</button>
                    <button class="modal-btn ${confirmBtnClass}" id="custom-confirm-yes">Confirm</button>
                </div>
            </div>
        `;
        document.body.appendChild(overlay);
        document.getElementById('custom-confirm-cancel').onclick = () => {
            overlay.remove();
            resolve(false);
        };
        document.getElementById('custom-confirm-yes').onclick = () => {
            overlay.remove();
            resolve(true);
        };
    });
}

function showCustomPrompt(title, message, placeholder = '', isPassword = false) {
    return new Promise((resolve) => {
        const overlay = document.createElement('div');
        overlay.className = 'modal-overlay active';
        const inputType = isPassword ? 'password' : 'text';
        overlay.innerHTML = `
            <div class="modal-box">
                <div class="modal-header"><i class="fa-solid fa-pen-to-square" style="color: #2563eb;"></i> ${title}</div>
                <div class="modal-body">
                    <p style="margin-bottom: 8px;">${message}</p>
                    <input type="${inputType}" class="modal-input" id="custom-prompt-input" placeholder="${placeholder}" autofocus autocomplete="off">
                </div>
                <div class="modal-footer">
                    <button class="modal-btn modal-btn-cancel" id="custom-prompt-cancel">Cancel</button>
                    <button class="modal-btn modal-btn-primary" id="custom-prompt-submit">Submit</button>
                </div>
            </div>
        `;
        document.body.appendChild(overlay);
        const input = document.getElementById('custom-prompt-input');
        input.focus();
        
        input.onkeydown = (e) => {
            if (e.key === 'Enter') {
                const val = input.value;
                overlay.remove();
                resolve(val);
            }
        };
        
        document.getElementById('custom-prompt-cancel').onclick = () => {
            overlay.remove();
            resolve(null);
        };
        document.getElementById('custom-prompt-submit').onclick = () => {
            const val = input.value;
            overlay.remove();
            resolve(val);
        };
    });
}

// ── STORAGE ──────────────────────────────────────────
function saveData(logEntry = null) {
    // Before saving, sync DOM inputs back into data model
    syncDomToData();
    
    const payload = {
        data: data,
        log: logEntry
    };
    
    // Save to server
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(payload)
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(res => {
        if (res.status === 'success') {
            flashSaved();
        } else {
            console.error('Failed to save:', res.message);
        }
    })
    .catch(error => {
        console.error('Error saving data:', error);
    });
}

function loadData() {
    // Already loaded via PHP injection into the `data` variable
}

function syncDomToData() {
    data.agents.forEach(agent => {
        agent.months.forEach(month => {
            const monthEl = document.getElementById('monthblock-' + month.id);
            if (!monthEl) return;
            const rows = monthEl.querySelectorAll('tbody tr.date-row');
            month.rows = [];
            rows.forEach(tr => {
                const items = [];
                tr.querySelectorAll('.product-item').forEach(item => {
                    items.push({
                        product: item.querySelector('.inp-product')?.value || '',
                        qty:     parseFloat(item.querySelector('.inp-qty')?.value) || 0,
                        rate:    parseFloat(item.querySelector('.inp-rate')?.value) || 0,
                    });
                });
                month.rows.push({
                    date:    tr.querySelector('.inp-date')?.value    || '',
                    items:   items,
                    deposit: parseFloat(tr.querySelector('.inp-deposit')?.value) || 0,
                });
            });
        });
    });
}

function flashSaved() {
    const el = document.getElementById('save-indicator');
    el.style.opacity = '1';
    clearTimeout(el._t);
    el._t = setTimeout(() => { el.style.opacity = '0'; }, 1500);
}

// ── RENDER ALL ────────────────────────────────────────
function renderAll() {
    // Remove old agent panels and tabs (keep summary)
    document.querySelectorAll('.agent-panel').forEach(p => p.remove());
    document.querySelectorAll('.agent-tab-btn').forEach(t => t.remove());

    data.agents.forEach(agent => renderAgentTabAndPanel(agent));

    switchTab(activeTab);
}

function renderAgentTabAndPanel(agent) {
    // Tab button
    const tabsBar = document.getElementById('tabs-bar');
    const addBtn  = tabsBar.querySelector('.btn-add-agent');
    const tabBtn  = document.createElement('button');
    tabBtn.className  = 'tab-btn agent-tab-btn';
    tabBtn.id         = 'tab-' + agent.id;
    tabBtn.textContent = agent.name;
    tabBtn.onclick    = () => switchTab(agent.id);
    tabsBar.insertBefore(tabBtn, addBtn);

    // Panel
    const wrapper = document.getElementById('page-wrapper');
    const panel   = document.createElement('div');
    panel.className = 'agent-panel';
    panel.id        = 'panel-' + agent.id;
    panel.innerHTML = `
        <div class="agent-panel-header">
            <h2><i class="fa-solid fa-user"></i> ${agent.name}</h2>
            <button class="btn-delete-agent" onclick="deleteAgent(${agent.id})"><i class="fa-solid fa-user-xmark"></i> Delete Agent</button>
        </div>
        <div class="months-container" id="months-${agent.id}"></div>
        <button class="btn-add-month" onclick="promptAddMonth(${agent.id})"><i class="fa-solid fa-calendar-plus"></i> ADD NEW MONTH</button>
    `;
    wrapper.appendChild(panel);

    // Render months
    agent.months.forEach(month => renderMonthBlock(agent.id, month));
}

function renderMonthBlock(agentId, month) {
    const container = document.getElementById('months-' + agentId);
    const monthDiv  = document.createElement('div');
    monthDiv.className = 'month-block';
    monthDiv.id        = 'monthblock-' + month.id;
    monthDiv.innerHTML = `
        <div class="month-title">
            <span><i class="fa-regular fa-calendar-days"></i> ${month.name}</span>
            <button class="btn-delete-month" onclick="deleteMonth(${agentId}, '${month.id}')"><i class="fa-solid fa-trash-can"></i> Remove Month</button>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Date</th><th>Products, Qty & Prices</th><th>Total Price</th><th>Deposit</th><th>Due</th><th>Del</th>
                </tr>
            </thead>
            <tbody></tbody>
            <tfoot>
                <tr class="totals">
                    <td colspan="2" style="text-align:right;">IN TOTAL (Qty: <span class="total-qty">0</span>)</td>
                    <td class="total-price">0.00</td>
                    <td class="total-deposit">0.00</td>
                    <td class="total-due">0.00</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
        <button class="btn-add-row" onclick="addRow(${agentId}, '${month.id}')"><i class="fa-solid fa-plus"></i> Add Row</button>
    `;
    container.appendChild(monthDiv);

    // Render rows
    if (month.rows && month.rows.length > 0) {
        month.rows.forEach(row => renderRow(agentId, month.id, row));
    } else {
        renderRow(agentId, month.id, { date: '', product: '', qty: 0, rate: 0, deposit: 0 });
    }
    recalcTable(monthDiv.querySelector('table'));
}

const EGG_PRODUCTS = [
    'সাদা ডিম এ গ্রেড',
    'লাল ডিম এ গ্রেড',
    'সাদা ডিম বি গ্রেড',
    'লাল ডিম বি গ্রেড',
    'হাসের ডিম'
];

// Convert old DD.MM.YY → YYYY-MM-DD for native date input
function toIsoDate(raw) {
    if (!raw) return '';
    // Already YYYY-MM-DD
    if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) return raw;
    // DD.MM.YY  e.g. 09.04.26
    const m = raw.match(/^(\d{2})\.(\d{2})\.(\d{2})$/);
    if (m) return `20${m[3]}-${m[2]}-${m[1]}`;
    // DD.MM.YYYY
    const m2 = raw.match(/^(\d{2})\.(\d{2})\.(\d{4})$/);
    if (m2) return `${m2[3]}-${m2[2]}-${m2[1]}`;
    return raw;
}

function renderRow(agentId, monthId, rowData) {
    const monthEl = document.getElementById('monthblock-' + monthId);
    if (!monthEl) return;
    const tbody  = monthEl.querySelector('tbody');
    const tr     = document.createElement('tr');
    tr.className = 'date-row';

    // Backward compatibility: Convert old structures
    if (!rowData.items) {
        if (rowData.product !== undefined) {
            rowData.items = [{
                product: rowData.product || '',
                qty: rowData.qty !== undefined ? rowData.qty : 0,
                rate: rowData.rate !== undefined ? rowData.rate : 0
            }];
        } else {
            rowData.items = [];
        }
    } else {
        rowData.items.forEach(item => {
            if (item.qty === undefined) item.qty = 0;
            if (item.rate === undefined) {
                item.rate = item.price !== undefined && item.qty ? (item.price / item.qty) : 0;
            }
        });
    }

    // Build the products container HTML
    let itemsHtml = '';
    rowData.items.forEach((item, idx) => {
        const productOptions = EGG_PRODUCTS.map(p =>
            `<option value="${escHtml(p)}" ${item.product === p ? 'selected' : ''}>${escHtml(p)}</option>`
        ).join('');

        const qtyVal = (item.qty === 0 || item.qty === undefined) ? '' : item.qty;
        const rateVal = (item.rate === 0 || item.rate === undefined) ? '' : item.rate;
        const price = (item.qty || 0) * (item.rate || 0);

        itemsHtml += `
            <div class="product-item" style="display: flex; gap: 6px; align-items: center; margin-bottom: 6px;">
                <select class="inp-product" style="width: 180px;" onfocus="this.setAttribute('data-old', this.value)" onchange="logFieldChange(this, ${agentId}, '${monthId}')">
                    <option value="">-- পণ্য --</option>
                    ${productOptions}
                </select>
                <input type="number" class="inp-qty" style="width: 80px;" placeholder="Qty" value="${qtyVal}" step="any" oninput="calcRow(this)" onfocus="this.setAttribute('data-old', this.value)" onchange="logFieldChange(this, ${agentId}, '${monthId}')">
                <span style="color: #6b7280; font-size: 11px;">x</span>
                <input type="number" class="inp-rate" style="width: 80px;" placeholder="Rate" value="${rateVal}" step="any" oninput="calcRow(this)" onfocus="this.setAttribute('data-old', this.value)" onchange="logFieldChange(this, ${agentId}, '${monthId}')">
                <span style="color: #6b7280; font-size: 11px;">=</span>
                <span class="item-price" style="min-width: 60px; text-align: right; font-size: 12px; font-weight: 600;">${price.toFixed(2)}</span>
                ${rowData.items.length > 1 ? `<button type="button" class="btn-del-item" onclick="deleteProductItem(this)" style="background:none; border:none; color:#ef4444; cursor:pointer; font-size: 12px;"><i class="fa-solid fa-trash-can"></i></button>` : ''}
            </div>
        `;
    });

    const depositVal = (rowData.deposit === 0 || rowData.deposit === undefined) ? '' : rowData.deposit;

    tr.innerHTML = `
        <td style="vertical-align: top;"><input type="date" class="inp-date" value="${toIsoDate(rowData.date || '')}" onfocus="this.setAttribute('data-old', this.value)" onchange="logFieldChange(this, ${agentId}, '${monthId}')"></td>
        <td style="text-align: left; vertical-align: top;">
            <div class="items-container">${itemsHtml}</div>
            <button type="button" class="btn-add-item" onclick="addProductItem(this, ${agentId}, '${monthId}')" style="background: #10b981; color: white; border: none; padding: 2px 8px; font-size: 11px; cursor: pointer; margin-top: 4px;"><i class="fa-solid fa-plus"></i> Add Product</button>
        </td>
        <td class="cell-price" style="vertical-align: top; font-weight: bold; font-size: 13px;">0.00</td>
        <td style="vertical-align: top;"><input type="number" class="inp-deposit" value="${depositVal}" step="any" oninput="calcRow(this)" onfocus="this.setAttribute('data-old', this.value)" onchange="logFieldChange(this, ${agentId}, '${monthId}')"></td>
        <td class="cell-due" style="vertical-align: top; font-weight: bold; font-size: 13px;">0.00</td>
        <td style="vertical-align: top;"><button class="btn-del-row" onclick="deleteRow(this, ${agentId}, '${monthId}')"><i class="fa-solid fa-xmark"></i></button></td>
    `;
    tbody.appendChild(tr);
    
    // Initial calculation for this row
    const dummyInput = tr.querySelector('.inp-deposit');
    calcRow(dummyInput);
}

// ── CALCULATIONS ─────────────────────────────────────
function calcRow(input, shouldSave = true) {
    if (!input) return;
    const tr = input.closest('tr');
    if (!tr) return;

    let totalPrice = 0;
    tr.querySelectorAll('.product-item').forEach(item => {
        const qty = parseFloat(item.querySelector('.inp-qty').value) || 0;
        const rate = parseFloat(item.querySelector('.inp-rate').value) || 0;
        const price = qty * rate;
        item.querySelector('.item-price').innerText = price.toFixed(2);
        totalPrice += price;
    });

    const deposit = parseFloat(tr.querySelector('.inp-deposit').value) || 0;
    const due = deposit - totalPrice;

    tr.querySelector('.cell-price').innerText = totalPrice.toFixed(2);
    tr.querySelector('.cell-due').innerText = due.toFixed(2);

    recalcTable(tr.closest('table'));
    if (shouldSave) saveData();
}

function recalcTable(table) {
    if (!table) return;
    let tQty = 0, tPrice = 0, tDeposit = 0, tDue = 0;
    table.querySelectorAll('tbody tr.date-row').forEach(tr => {
        tr.querySelectorAll('.product-item').forEach(item => {
            tQty += parseFloat(item.querySelector('.inp-qty')?.value) || 0;
        });
        tPrice   += parseFloat(tr.querySelector('.cell-price')?.innerText) || 0;
        tDeposit += parseFloat(tr.querySelector('.inp-deposit')?.value) || 0;
        tDue     += parseFloat(tr.querySelector('.cell-due')?.innerText)   || 0;
    });
    table.querySelector('.total-qty').innerText     = tQty;
    table.querySelector('.total-price').innerText   = tPrice.toFixed(2);
    table.querySelector('.total-deposit').innerText = tDeposit.toFixed(2);
    table.querySelector('.total-due').innerText     = tDue.toFixed(2);
}

function addProductItem(btn, agentId, monthId) {
    const container = btn.previousElementSibling;
    const div = document.createElement('div');
    div.className = 'product-item';
    div.style.display = 'flex';
    div.style.gap = '6px';
    div.style.alignItems = 'center';
    div.style.marginBottom = '6px';
    
    const productOptions = EGG_PRODUCTS.map(p =>
        `<option value="${escHtml(p)}">${escHtml(p)}</option>`
    ).join('');

    div.innerHTML = `
        <select class="inp-product" style="width: 180px;" onfocus="this.setAttribute('data-old', this.value)" onchange="logFieldChange(this, ${agentId}, '${monthId}')">
            <option value="">-- পণ্য --</option>
            ${productOptions}
        </select>
        <input type="number" class="inp-qty" style="width: 80px;" placeholder="Qty" value="" step="any" oninput="calcRow(this)" onfocus="this.setAttribute('data-old', this.value)" onchange="logFieldChange(this, ${agentId}, '${monthId}')">
        <span style="color: #6b7280; font-size: 11px;">x</span>
        <input type="number" class="inp-rate" style="width: 80px;" placeholder="Rate" value="" step="any" oninput="calcRow(this)" onfocus="this.setAttribute('data-old', this.value)" onchange="logFieldChange(this, ${agentId}, '${monthId}')">
        <span style="color: #6b7280; font-size: 11px;">=</span>
        <span class="item-price" style="min-width: 60px; text-align: right; font-size: 12px; font-weight: 600;">0.00</span>
        <button type="button" class="btn-del-item" onclick="deleteProductItem(this)" style="background:none; border:none; color:#ef4444; cursor:pointer; font-size: 12px;"><i class="fa-solid fa-trash-can"></i></button>
    `;
    container.appendChild(div);
    updateItemDeleteButtons(container);
    saveData();
}

function deleteProductItem(btn) {
    const container = btn.closest('.items-container');
    const item = btn.closest('.product-item');
    
    // Get product details before removing
    const productName = item.querySelector('.inp-product')?.value || 'Unnamed Product';
    const qty = item.querySelector('.inp-qty')?.value || '0';
    const rate = item.querySelector('.inp-rate')?.value || '0';
    
    const panel = btn.closest('.agent-panel');
    const agentId = panel ? parseInt(panel.id.replace('panel-', '')) : null;
    const monthBlock = btn.closest('.month-block');
    const monthId = monthBlock ? monthBlock.id.replace('monthblock-', '') : null;
    
    const agent = data.agents.find(a => a.id === agentId);
    const month = agent?.months.find(m => m.id === monthId);
    
    item.remove();
    updateItemDeleteButtons(container);
    
    const tr = container.closest('tr');
    const dummyInput = tr.querySelector('.inp-deposit');
    calcRow(dummyInput, false);
    
    saveData({
        action: 'Delete',
        details: `Deleted Product: "${productName}" (Qty: ${qty}, Rate: ${rate}) in Month: "${month?.name}" for Agent: "${agent?.name}"`
    });
}

function updateItemDeleteButtons(container) {
    const items = container.querySelectorAll('.product-item');
    items.forEach(item => {
        let delBtn = item.querySelector('.btn-del-item');
        if (items.length > 1) {
            if (!delBtn) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn-del-item';
                btn.onclick = function() { deleteProductItem(this); };
                btn.style.background = 'none';
                btn.style.border = 'none';
                btn.style.color = '#ef4444';
                btn.style.cursor = 'pointer';
                btn.style.fontSize = '12px';
                btn.innerHTML = '<i class="fa-solid fa-trash-can"></i>';
                item.appendChild(btn);
            }
        } else {
            if (delBtn) {
                delBtn.remove();
            }
        }
    });
}

// ── ADD / DELETE AGENT ────────────────────────────────
async function promptAddAgent() {
    const name = await showCustomPrompt('Add Agent', 'Enter agent name (e.g., Raju, Karim):');
    if (!name || !name.trim()) return;
    data.agentCounter++;
    const agent = { id: data.agentCounter, name: name.trim(), months: [] };
    data.agents.push(agent);
    renderAgentTabAndPanel(agent);
    switchTab(agent.id);
    saveData({ action: 'Add', details: `Added Agent: "${name.trim()}"` });
}

async function deleteAgent(agentId) {
    const confirmed = await showCustomConfirm('Delete Agent', 'Delete this agent and ALL their data permanently?', true);
    if (!confirmed) return;
    const agent = data.agents.find(a => a.id === agentId);
    data.agents = data.agents.filter(a => a.id !== agentId);
    document.getElementById('tab-'   + agentId)?.remove();
    document.getElementById('panel-' + agentId)?.remove();
    switchTab('summary');
    saveData({ action: 'Delete', details: `Deleted Agent: "${agent?.name}"` });
}

// ── ADD / DELETE MONTH ────────────────────────────────
async function promptAddMonth(agentId) {
    const name = await showCustomPrompt('Add Month', 'Enter month name (e.g., May, June):');
    if (!name || !name.trim()) return;
    const agent = data.agents.find(a => a.id === agentId);
    if (!agent) return;
    const monthId = 'month-' + agentId + '-' + Date.now();
    const month   = { id: monthId, name: name.trim(), rows: [] };
    agent.months.push(month);
    renderMonthBlock(agentId, month);
    saveData({ action: 'Add', details: `Added Month: "${name.trim()}" for Agent: "${agent.name}"` });
}

async function deleteMonth(agentId, monthId) {
    const pin = await showCustomPrompt('Delete Month', 'Enter PIN code to delete this month:', 'PIN code', true);
    if (pin !== '13579') {
        await showCustomAlert('Security Alert', '❌ Incorrect PIN! Delete cancelled.');
        return;
    }
    const agent = data.agents.find(a => a.id === agentId);
    const month = agent?.months.find(m => m.id === monthId);
    if (agent) agent.months = agent.months.filter(m => m.id !== monthId);
    document.getElementById('monthblock-' + monthId)?.remove();
    saveData({ action: 'Delete', details: `Deleted Month: "${month?.name}" for Agent: "${agent?.name}"` });
}

// ── ADD / DELETE ROW ──────────────────────────────────
function addRow(agentId, monthId) {
    const agent = data.agents.find(a => a.id === agentId);
    const month = agent?.months.find(m => m.id === monthId);
    const newRow = { date: '', product: '', qty: 0, rate: 0, deposit: 0 };
    if (month) month.rows.push(newRow);
    renderRow(agentId, monthId, newRow);
    const monthEl = document.getElementById('monthblock-' + monthId);
    if (monthEl) recalcTable(monthEl.querySelector('table'));
    saveData({ action: 'Add', details: `Added row in Month: "${month?.name}" for Agent: "${agent?.name}"` });
}

async function deleteRow(btn, agentId, monthId) {
    const confirmed = await showCustomConfirm('Delete Row', 'Are you sure you want to delete this row?', true);
    if (!confirmed) return;
    const tr    = btn.closest('tr');
    const table = btn.closest('table');
    
    const dateVal = tr.querySelector('.inp-date')?.value || 'No Date';
    const agent = data.agents.find(a => a.id === agentId);
    const month = agent?.months.find(m => m.id === monthId);
    
    const productsInfo = [];
    tr.querySelectorAll('.product-item').forEach(item => {
        const pName = item.querySelector('.inp-product')?.value || '';
        const qty = item.querySelector('.inp-qty')?.value || '0';
        const rate = item.querySelector('.inp-rate')?.value || '0';
        if (pName) {
            productsInfo.push(`${pName} (${qty} x ${rate})`);
        }
    });
    const productsStr = productsInfo.length > 0 ? productsInfo.join(', ') : 'No products';
    
    tr.remove();
    recalcTable(table);
    
    saveData({
        action: 'Delete',
        details: `Deleted Row for Date: "${dateVal}" (${productsStr}) in Month: "${month?.name}" for Agent: "${agent?.name}"`
    });
}

// ── TAB SWITCHING ─────────────────────────────────────
function switchTab(tabId) {
    activeTab = tabId;
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.agent-panel, .summary-panel').forEach(p => p.classList.remove('active'));

    if (tabId === 'summary') {
        document.getElementById('tab-summary')?.classList.add('active');
        document.getElementById('panel-summary')?.classList.add('active');
        renderSummary();
    } else if (tabId === 'logs') {
        document.getElementById('tab-logs')?.classList.add('active');
        document.getElementById('panel-logs')?.classList.add('active');
        loadAndRenderLogs();
    } else {
        document.getElementById('tab-'   + tabId)?.classList.add('active');
        document.getElementById('panel-' + tabId)?.classList.add('active');
    }
}

// ── LOGS FUNCTIONS ─────────────────────────────────────
let currentLogFilter = 'all';

function setLogFilter(filter) {
    currentLogFilter = filter;
    document.getElementById('btn-log-filter-all').classList.toggle('active', filter === 'all');
    document.getElementById('btn-log-filter-delete').classList.toggle('active', filter === 'delete');
    loadAndRenderLogs();
}

function logFieldChange(el, agentId, monthId) {
    const oldVal = el.getAttribute('data-old') || '';
    const newVal = el.value;
    if (oldVal === newVal) return;
    el.setAttribute('data-old', newVal);
    const agent = data.agents.find(a => a.id === agentId);
    const month = agent?.months.find(m => m.id === monthId);
    
    let fieldName = 'value';
    if (el.classList.contains('inp-date')) fieldName = 'Date';
    else if (el.classList.contains('inp-product')) fieldName = 'Product';
    else if (el.classList.contains('inp-qty')) fieldName = 'Quantity';
    else if (el.classList.contains('inp-rate')) fieldName = 'Rate';
    else if (el.classList.contains('inp-deposit')) fieldName = 'Deposit';
    
    saveData({ 
        action: 'Edit', 
        details: `Changed ${fieldName} in Month: "${month?.name}" for Agent: "${agent?.name}" from "${oldVal}" to "${newVal}"` 
    });
}

function loadAndRenderLogs() {
    const container = document.getElementById('logs-content');
    container.innerHTML = '<p class="no-data-msg">Loading logs...</p>';
    
    fetch('?action=get_logs')
        .then(response => response.json())
        .then(logs => {
            if (!logs || logs.length === 0) {
                container.innerHTML = '<p class="no-data-msg">No activity logs found.</p>';
                return;
            }
            
            let filteredLogs = logs;
            if (currentLogFilter === 'delete') {
                filteredLogs = logs.filter(log => log.action && log.action.toLowerCase() === 'delete');
            }
            
            if (filteredLogs.length === 0) {
                container.innerHTML = '<p class="no-data-msg">No matching logs found.</p>';
                return;
            }
            
            let html = `
                <table class="logs-table">
                    <thead>
                        <tr>
                            <th style="width: 160px;">Timestamp</th>
                            <th style="width: 100px;">Action</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            filteredLogs.forEach(log => {
                let badgeClass = 'badge-edit';
                if (log.action.toLowerCase() === 'delete') badgeClass = 'badge-delete';
                else if (log.action.toLowerCase() === 'add') badgeClass = 'badge-add';
                else if (log.action.toLowerCase() === 'undo') badgeClass = 'badge-add';
                
                const hasUndo = log.previous_state ? true : false;
                const undoButton = hasUndo ? 
                    `<button class="tab-btn" style="padding: 3px 8px; font-size: 11px; background: #4f46e5; color: white; border-radius: 4px; display: inline-flex; align-items: center; gap: 4px;" onclick="triggerUndo('${escHtml(log.timestamp)}')"><i class="fa-solid fa-rotate-left"></i> Undo</button>` : '';

                html += `
                    <tr>
                        <td style="white-space: nowrap; color: #6b7280; font-family: monospace;">${escHtml(log.timestamp)}</td>
                        <td><span class="log-badge ${badgeClass}">${escHtml(log.action)}</span></td>
                        <td>
                            <div style="display: flex; justify-content: space-between; align-items: center; gap: 10px;">
                                <span>${escHtml(log.details)}</span>
                                ${undoButton}
                            </div>
                        </td>
                    </tr>
                `;
            });
            
            html += '</tbody></table>';
            container.innerHTML = html;
        })
        .catch(err => {
            container.innerHTML = '<p class="no-data-msg" style="color: #dc2626;">Error loading logs.</p>';
            console.error(err);
        });
}

async function triggerUndo(timestamp) {
    const confirmed = await showCustomConfirm('Undo Action', 'Are you sure you want to undo this action? This will restore the database to its state prior to this action.');
    if (!confirmed) return;
    
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            action: 'undo',
            timestamp: timestamp
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(async res => {
        if (res.status === 'success') {
            data = res.data;
            renderAll();
            renderSummary();
            loadAndRenderLogs();
            await showCustomAlert('Undo Successful', '🔄 Action undone successfully!');
        } else {
            await showCustomAlert('Undo Failed', '❌ Undo failed: ' + res.message);
        }
    })
    .catch(async error => {
        console.error('Error performing undo:', error);
        await showCustomAlert('Error', '❌ Error performing undo');
    });
}

// ── GRAND SUMMARY ─────────────────────────────────────
function renderSummary() {
    syncDomToData();
    const container = document.getElementById('summary-content');

    if (data.agents.length === 0) {
        container.innerHTML = '<p class="no-data-msg">No agents yet. Click <strong>+ Add Agent</strong> to begin.</p>';
        return;
    }

    let grandQty = 0, grandPrice = 0, grandDeposit = 0, grandDue = 0;
    let agentRows = '';

    data.agents.forEach(agent => {
        let aQty = 0, aPrice = 0, aDeposit = 0, aDue = 0;
        agent.months.forEach(month => {
            month.rows.forEach(row => {
                let qty = 0;
                let price = 0;
                if (row.items) {
                    row.items.forEach(item => {
                        qty += parseFloat(item.qty) || 0;
                        price += parseFloat(item.price) || 0;
                    });
                } else {
                    qty = parseFloat(row.qty) || 0;
                    price = (parseFloat(row.qty) || 0) * (parseFloat(row.rate) || 0);
                }
                const deposit = parseFloat(row.deposit) || 0;
                const due     = deposit - price;
                
                aQty     += qty;
                aPrice   += price;
                aDeposit += deposit;
                aDue     += due;
            });
        });
        grandQty     += aQty;
        grandPrice   += aPrice;
        grandDeposit += aDeposit;
        grandDue     += aDue;

        const dueClass = aDue < 0 ? 'due-neg' : 'due-pos';
        agentRows += `
            <tr>
                <td>${escHtml(agent.name)}</td>
                <td>${aQty.toFixed(0)}</td>
                <td>${aPrice.toFixed(2)}</td>
                <td>${aDeposit.toFixed(2)}</td>
                <td class="${dueClass}">${aDue.toFixed(2)}</td>
                <td><button class="view-agent-btn" onclick="switchTab(${agent.id})">View <i class="fa-solid fa-arrow-right"></i></button></td>
            </tr>`;
    });

    const grandDueClass = grandDue < 0 ? 'due-neg' : 'due-pos';
    const kpiDueClass = grandDue < 0 ? 'neg' : '';

    // Monthly breakdown per agent
    let monthBreakdown = '';
    data.agents.forEach(agent => {
        if (!agent.months.length) return;
        let monthRows = '';
        agent.months.forEach(month => {
            let mQty = 0, mPrice = 0, mDeposit = 0, mDue = 0;
            month.rows.forEach(row => {
                let qty = 0;
                let price = 0;
                if (row.items) {
                    row.items.forEach(item => {
                        qty += parseFloat(item.qty) || 0;
                        price += parseFloat(item.price) || 0;
                    });
                } else {
                    qty = parseFloat(row.qty) || 0;
                    price = (parseFloat(row.qty) || 0) * (parseFloat(row.rate) || 0);
                }
                const deposit = parseFloat(row.deposit) || 0;
                
                mQty     += qty;
                mPrice   += price;
                mDeposit += deposit;
                mDue     += deposit - price;
            });
            const dc = mDue < 0 ? 'due-neg' : 'due-pos';
            monthRows += `<tr>
                <td>${escHtml(month.name)}</td>
                <td>${mQty.toFixed(0)}</td>
                <td>${mPrice.toFixed(2)}</td>
                <td>${mDeposit.toFixed(2)}</td>
                <td class="${dc}">${mDue.toFixed(2)}</td>
            </tr>`;
        });
        monthBreakdown += `
            <div style="margin-bottom:20px;">
                <strong style="font-size:13px;color:#1a1a2e;"><i class="fa-solid fa-user"></i> ${escHtml(agent.name)}</strong>
                <table class="summary-table" style="margin-top:7px;">
                    <thead><tr><th>Month</th><th>Qty</th><th>Price (৳)</th><th>Deposit (৳)</th><th>Due (৳)</th></tr></thead>
                    <tbody>${monthRows}</tbody>
                </table>
            </div>`;
    });

    container.innerHTML = `
        <div class="kpi-cards">
            <div class="kpi-card kpi-qty">
                <span class="kpi-label">Total Egg Quantity</span>
                <span class="kpi-value">${grandQty.toFixed(0)} pcs</span>
            </div>
            <div class="kpi-card kpi-price">
                <span class="kpi-label">Total Price</span>
                <span class="kpi-value">৳ ${grandPrice.toFixed(2)}</span>
            </div>
            <div class="kpi-card kpi-deposit">
                <span class="kpi-label">Total Deposit</span>
                <span class="kpi-value">৳ ${grandDeposit.toFixed(2)}</span>
            </div>
            <div class="kpi-card kpi-due ${kpiDueClass}">
                <span class="kpi-label">Total Due</span>
                <span class="kpi-value">৳ ${grandDue.toFixed(2)}</span>
            </div>
        </div>

        <div class="section-title"><i class="fa-solid fa-table-list"></i> Agent Comparison Table</div>
        <table class="summary-table">
            <thead>
                <tr>
                    <th>Agent Name</th><th>Total Qty</th><th>Total Price (৳)</th><th>Total Deposit (৳)</th>
                    <th>Total Due (৳)</th><th>Detail</th>
                </tr>
            </thead>
            <tbody>${agentRows}</tbody>
            <tfoot>
                <tr class="summary-grand">
                    <td>🏆 GRAND TOTAL (${data.agents.length} agents)</td>
                    <td>${grandQty.toFixed(0)}</td>
                    <td>${grandPrice.toFixed(2)}</td>
                    <td>${grandDeposit.toFixed(2)}</td>
                    <td class="${grandDueClass}">${grandDue.toFixed(2)}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
        <div class="section-title"><i class="fa-solid fa-network-wired"></i> Month-by-Month Breakdown Per Agent</div>
        ${monthBreakdown}
    `;
}

// ── CLEAR ALL ─────────────────────────────────────────
async function clearAllData() {
    const confirmed = await showCustomConfirm('Clear All Data', '⚠️ This will DELETE ALL agents and ALL data permanently. Are you sure?', true);
    if (!confirmed) return;
    data = { agentCounter: 0, agents: [] };
    saveData({
        action: 'Delete',
        details: 'Cleared all system data permanently'
    });
    renderAll();
    renderSummary();
}

// ── UTILS ─────────────────────────────────────────────
function escHtml(str) {
    return String(str)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── INIT ─────────────────────────────────────────────
loadData();
renderAll();

</script>

</body>
</html>