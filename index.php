<?php
$db_file = __DIR__ . '/database.json';

// Handle POST request to save data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = file_get_contents('php://input');
    $decoded = json_decode($input, true);
    if ($decoded !== null) {
        if (file_put_contents($db_file, json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
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

// Load data for initial page render
$initial_data = [
    'agentCounter' => 1,
    'agents' => [
        [
            'id' => 1,
            'name' => 'Tuku/Binodpur',
            'months' => [
                [
                    'id' => 'month-1-default',
                    'name' => 'April',
                    'rows' => [
                        [
                            'date' => '2026-04-09',
                            'product' => 'লাল ডিম এ গ্রেড',
                            'qty' => 1200,
                            'rate' => 7.9666,
                            'deposit' => 0
                        ]
                    ]
                ]
            ]
        ]
    ]
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

        .view-agent-btn {
            padding: 4px 10px;
            background: #2d2d4e;
            color: #fff;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 11px;
        }
        .view-agent-btn:hover { background: #1a1a2e; }
    </style>
</head>
<body>

<div class="main-header">
    <h1><i class="fa-solid fa-egg" style="color: #d97706; margin-right: 6px;"></i> Egg Report — Multi Agent</h1>
    <p>All data is saved automatically to the server database</p>
</div>

<div class="tabs-bar" id="tabs-bar">
    <button class="tab-btn summary-tab-btn active" id="tab-summary" onclick="switchTab('summary')"><i class="fa-solid fa-chart-line"></i> Grand Summary</button>
    <span id="save-indicator"><i class="fa-solid fa-circle-check"></i> Saved</span>
    <button class="btn-add-agent" onclick="promptAddAgent()"><i class="fa-solid fa-user-plus"></i> Add Agent</button>
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

    <!-- Agent panels injected by JS -->

</div>

<script>
// ═══════════════════════════════════════════════════════
//  DATA MODEL
//  data = { agentCounter: N, agents: [ { id, name, months: [ { id, name, rows: [ { date, product, qty, rate, deposit } ] } ] } ] }
// ═══════════════════════════════════════════════════════

let data = <?php echo json_encode($initial_data, JSON_UNESCAPED_UNICODE); ?>;
let activeTab = 'summary';

// ── STORAGE ──────────────────────────────────────────
function saveData() {
    // Before saving, sync DOM inputs back into data model
    syncDomToData();
    
    // Save to server
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
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
            const rows = monthEl.querySelectorAll('tbody tr');
            month.rows = [];
            rows.forEach(tr => {
                month.rows.push({
                    date:    tr.querySelector('.inp-date')?.value    || '',
                    product: tr.querySelector('.inp-product')?.value || '',
                    qty:     parseFloat(tr.querySelector('.inp-qty')?.value)     || 0,
                    rate:    parseFloat(tr.querySelector('.inp-rate')?.value)    || 0,
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
                    <th>Date</th><th>Product Name</th><th>Qty</th>
                    <th>Rate</th><th>Price</th><th>Deposit</th><th>Due</th><th>Del</th>
                </tr>
            </thead>
            <tbody></tbody>
            <tfoot>
                <tr class="totals">
                    <td colspan="2" style="text-align:right;">IN TOTAL</td>
                    <td class="total-qty">0</td><td></td>
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
    const price  = (rowData.qty || 0) * (rowData.rate || 0);
    const due    = (rowData.deposit || 0) - price;
    const tr     = document.createElement('tr');

    // Build product <select> options
    const productOptions = EGG_PRODUCTS.map(p =>
        `<option value="${escHtml(p)}" ${rowData.product === p ? 'selected' : ''}>${escHtml(p)}</option>`
    ).join('');

    const qtyVal = (rowData.qty === 0 || rowData.qty === undefined) ? '' : rowData.qty;
    const rateVal = (rowData.rate === 0 || rowData.rate === undefined) ? '' : rowData.rate;
    const depositVal = (rowData.deposit === 0 || rowData.deposit === undefined) ? '' : rowData.deposit;

    tr.innerHTML = `
        <td><input type="date" class="inp-date" value="${toIsoDate(rowData.date || '')}" onchange="onInputChange(this)"></td>
        <td>
            <select class="inp-product" onchange="onInputChange(this)">
                <option value="">-- পণ্য বেছে নিন --</option>
                ${productOptions}
            </select>
        </td>
        <td><input type="number" class="inp-qty"     value="${qtyVal}" step="any" oninput="calcRow(this)"></td>
        <td><input type="number" class="inp-rate"    value="${rateVal}" step="any" oninput="calcRow(this)"></td>
        <td class="cell-price">${price.toFixed(2)}</td>
        <td><input type="number" class="inp-deposit" value="${depositVal}" step="any" oninput="calcRow(this)"></td>
        <td class="cell-due">${due.toFixed(2)}</td>
        <td><button class="btn-del-row" onclick="deleteRow(this, ${agentId}, '${monthId}')"><i class="fa-solid fa-xmark"></i></button></td>
    `;
    tbody.appendChild(tr);
}

// ── CALCULATIONS ─────────────────────────────────────
function calcRow(input) {
    const tr      = input.closest('tr');
    const qty     = parseFloat(tr.querySelector('.inp-qty').value)     || 0;
    const rate    = parseFloat(tr.querySelector('.inp-rate').value)    || 0;
    const deposit = parseFloat(tr.querySelector('.inp-deposit').value) || 0;
    const price   = qty * rate;
    const due     = deposit - price;
    tr.querySelector('.cell-price').innerText = price.toFixed(2);
    tr.querySelector('.cell-due').innerText   = due.toFixed(2);
    recalcTable(input.closest('table'));
    saveData();
}

function onInputChange(input) {
    saveData();
}

function recalcTable(table) {
    let tQty = 0, tPrice = 0, tDeposit = 0, tDue = 0;
    table.querySelectorAll('tbody tr').forEach(tr => {
        tQty     += parseFloat(tr.querySelector('.inp-qty')?.value)     || 0;
        tPrice   += parseFloat(tr.querySelector('.cell-price')?.innerText) || 0;
        tDeposit += parseFloat(tr.querySelector('.inp-deposit')?.value) || 0;
        tDue     += parseFloat(tr.querySelector('.cell-due')?.innerText)   || 0;
    });
    table.querySelector('.total-qty').innerText     = tQty;
    table.querySelector('.total-price').innerText   = tPrice.toFixed(2);
    table.querySelector('.total-deposit').innerText = tDeposit.toFixed(2);
    table.querySelector('.total-due').innerText     = tDue.toFixed(2);
}

// ── ADD / DELETE AGENT ────────────────────────────────
function promptAddAgent() {
    const name = prompt('Enter agent name (e.g., Raju, Karim):');
    if (!name || !name.trim()) return;
    data.agentCounter++;
    const agent = { id: data.agentCounter, name: name.trim(), months: [] };
    data.agents.push(agent);
    renderAgentTabAndPanel(agent);
    switchTab(agent.id);
    saveData();
}

function deleteAgent(agentId) {
    if (!confirm('Delete this agent and ALL their data permanently?')) return;
    data.agents = data.agents.filter(a => a.id !== agentId);
    document.getElementById('tab-'   + agentId)?.remove();
    document.getElementById('panel-' + agentId)?.remove();
    switchTab('summary');
    saveData();
}

// ── ADD / DELETE MONTH ────────────────────────────────
function promptAddMonth(agentId) {
    const name = prompt('Enter month name (e.g., May, June):');
    if (!name || !name.trim()) return;
    const agent = data.agents.find(a => a.id === agentId);
    if (!agent) return;
    const monthId = 'month-' + agentId + '-' + Date.now();
    const month   = { id: monthId, name: name.trim(), rows: [] };
    agent.months.push(month);
    renderMonthBlock(agentId, month);
    saveData();
}

function deleteMonth(agentId, monthId) {
    if (!confirm('Remove this month and all its rows?')) return;
    const agent = data.agents.find(a => a.id === agentId);
    if (agent) agent.months = agent.months.filter(m => m.id !== monthId);
    document.getElementById('monthblock-' + monthId)?.remove();
    saveData();
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
    saveData();
}

function deleteRow(btn, agentId, monthId) {
    const tr    = btn.closest('tr');
    const table = btn.closest('table');
    tr.remove();
    recalcTable(table);
    saveData();
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
    } else {
        document.getElementById('tab-'   + tabId)?.classList.add('active');
        document.getElementById('panel-' + tabId)?.classList.add('active');
    }
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
                const qty     = parseFloat(row.qty)     || 0;
                const rate    = parseFloat(row.rate)    || 0;
                const deposit = parseFloat(row.deposit) || 0;
                const price   = qty * rate;
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
                const qty     = parseFloat(row.qty)     || 0;
                const rate    = parseFloat(row.rate)    || 0;
                const deposit = parseFloat(row.deposit) || 0;
                const price   = qty * rate;
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
                    <th>Agent Name</th><th>Total Qty</th>
                    <th>Total Price (৳)</th><th>Total Deposit (৳)</th>
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
function clearAllData() {
    if (!confirm('⚠️ This will DELETE ALL agents and ALL data permanently. Are you sure?')) return;
    data = { agentCounter: 0, agents: [] };
    saveData();
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