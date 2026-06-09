<?php
// accounts.php
require_once __DIR__ . '/includes/header.php';
$tenant_id = get_tenant_id();

// Handle Log Expense
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_expense') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $category = sanitize($_POST['category']);
        $amount = floatval($_POST['amount']);
        $exp_date = sanitize($_POST['expense_date']);
        $desc = sanitize($_POST['description']);
        
        db_insert($conn, "INSERT INTO expenses (tenant_id, category, amount, expense_date, description) VALUES (?, ?, ?, ?, ?)", [
            $tenant_id, $category, $amount, $exp_date, $desc
        ]);
        
        set_flash_message('success', 'Expense recorded successfully!');
        redirect('/accounts.php');
    }
}

// Fetch totals
$total_income = mysqli_fetch_assoc(db_query($conn, "SELECT SUM(amount_paid) as total FROM fee_payments WHERE tenant_id = ?", [$tenant_id]))['total'] ?? 0;
$total_expense = mysqli_fetch_assoc(db_query($conn, "SELECT SUM(amount) as total FROM expenses WHERE tenant_id = ?", [$tenant_id]))['total'] ?? 0;
$net_balance = $total_income - $total_expense;

// Cash Book Ledger Data
// Select all incomes and expenses, union them, sort by date desc
$ledger_query = "
    SELECT 'Income' as entry_type, receipt_number as ref_id, s.name as detail, amount_paid as amount, payment_date as entry_date 
    FROM fee_payments p 
    JOIN students s ON p.student_id = s.id 
    WHERE p.tenant_id = ?
    UNION ALL
    SELECT 'Expense' as entry_type, id as ref_id, CONCAT(category, ' - ', description) as detail, amount, expense_date as entry_date 
    FROM expenses 
    WHERE tenant_id = ?
    ORDER BY entry_date DESC, ref_id DESC LIMIT 40";
    
$ledger_res = db_query($conn, $ledger_query, [$tenant_id, $tenant_id]);

// Expense category breakdown for P&L
$expenses_by_cat = db_query($conn, "
    SELECT category, SUM(amount) as total 
    FROM expenses 
    WHERE tenant_id = ? 
    GROUP BY category", [$tenant_id]);
?>

<div class="page-header">
    <h2><i class="bi bi-bank" style="color:var(--primary);margin-right:8px;"></i>Accounts & Expense</h2>
    <button class="btn btn-primary" onclick="openModal('addExpenseModal')"><i class="bi bi-dash-circle"></i> Log Expense Entry</button>
</div>

<!-- Financial Summary Cards -->
<div class="metrics-grid">
    <div class="metric-card">
        <div class="metric-header">
            <span class="metric-label">Total Revenue (Inflow)</span>
            <div class="metric-icon success"><i class="bi bi-graph-up-arrow"></i></div>
        </div>
        <div class="metric-value">₹<?= number_format($total_income, 2) ?></div>
        <div class="metric-label">Fees & receipts logged</div>
    </div>
    
    <div class="metric-card">
        <div class="metric-header">
            <span class="metric-label">Total Expenses (Outflow)</span>
            <div class="metric-icon danger"><i class="bi bi-graph-down-arrow"></i></div>
        </div>
        <div class="metric-value">₹<?= number_format($total_expense, 2) ?></div>
        <div class="metric-label">Operational outflows</div>
    </div>

    <div class="metric-card">
        <div class="metric-header">
            <span class="metric-label">Net Balance (Profit / Loss)</span>
            <div class="metric-icon <?= $net_balance >= 0 ? 'primary' : 'warning' ?>"><i class="bi bi-bank"></i></div>
        </div>
        <div class="metric-value">₹<?= number_format($net_balance, 2) ?></div>
        <div class="metric-label">Cash-in-hand position</div>
    </div>
</div>

<div class="grid-2">
    <!-- Cash Book Ledger -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Cash Book Ledger (Chronological Flow)</h3>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Reference / Detail</th>
                            <th>In (Income)</th>
                            <th>Out (Expense)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($ledger_res) === 0): ?>
                            <tr><td colspan="4">
                                <div class="empty-state">
                                    <i class="bi bi-journal-x"></i>
                                    <p>No transactions logged in cash book.</p>
                                </div>
                            </td></tr>
                        <?php else: ?>
                            <?php while ($row = mysqli_fetch_assoc($ledger_res)): ?>
                                <tr>
                                    <td><?= date('d M Y', strtotime($row['entry_date'])) ?></td>
                                    <td>
                                        <strong style="color:var(--ink-700);"><?= $row['detail'] ?></strong>
                                        <div class="text-muted"><?= $row['ref_id'] ?></div>
                                    </td>
                                    <td>
                                        <?php if ($row['entry_type'] === 'Income'): ?>
                                            <span style="color:var(--success);font-weight:600;">+ ₹<?= number_format($row['amount'], 2) ?></span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($row['entry_type'] === 'Expense'): ?>
                                            <span style="color:var(--danger);font-weight:600;">- ₹<?= number_format($row['amount'], 2) ?></span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Profit & Loss Category Summary -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Profit & Loss Category Breakdown</h3>
        </div>
        <div class="card-body">
            <div style="margin-bottom: 24px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                    <strong>Revenue (Fees Collected):</strong>
                    <span style="color: var(--success); font-weight: 600;">₹<?= number_format($total_income, 2) ?></span>
                </div>
                <div style="height: 6px; background-color: var(--slate-200); border-radius: 4px; overflow: hidden;">
                    <div style="width: 100%; height: 100%; background-color: var(--success);"></div>
                </div>
            </div>

            <h4 style="font-size: 0.9rem; text-transform: uppercase; color: var(--slate-400); margin-bottom: 16px; letter-spacing: 0.05em;">Expenses by Category</h4>
            
            <?php if (mysqli_num_rows($expenses_by_cat) === 0): ?>
                <p style="color: var(--slate-400); text-align: center; padding: 20px;">No expense categories recorded.</p>
            <?php else: ?>
                <?php while ($cat = mysqli_fetch_assoc($expenses_by_cat)): ?>
                    <?php 
                    $percent = $total_income > 0 ? min(100, round(($cat['total'] / $total_income) * 100)) : 100;
                    ?>
                    <div style="margin-bottom: 16px;">
                        <div style="display: flex; justify-content: space-between; font-size: 0.9rem; margin-bottom: 6px;">
                            <span><?= $cat['category'] ?></span>
                            <strong>₹<?= number_format($cat['total'], 2) ?></strong>
                        </div>
                        <div style="height: 6px; background-color: var(--slate-200); border-radius: 4px; overflow: hidden;">
                            <div style="width: <?= $percent ?>%; height: 100%; background-color: var(--danger);"></div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal: Log Expense -->
<div id="addExpenseModal" class="modal-backdrop">
    <div class="modal">
        <form action="accounts.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="add_expense">
            <div class="modal-header">
                <h3><i class="bi bi-dash-circle" style="color:var(--primary);margin-right:6px;"></i>Log Outflow Expense</h3>
                <button type="button" class="modal-close" onclick="closeModal('addExpenseModal')"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="modal-body">
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <select name="category" class="form-control" required>
                            <option value="Rent">Rent</option>
                            <option value="Electricity">Electricity</option>
                            <option value="Salaries">Salaries</option>
                            <option value="Marketing">Marketing</option>
                            <option value="Printing & Stationery">Printing & Stationery</option>
                            <option value="Internet & Telephone">Internet & Telephone</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Expense Amount (INR)</label>
                        <input type="number" name="amount" class="form-control" value="500" min="1" step="0.01" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Expense Date</label>
                    <input type="date" name="expense_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Outflow Description / Notes</label>
                    <textarea name="description" class="form-control" placeholder="E.g., May Rent, Office broadband bills..." required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addExpenseModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Log Outflow</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
