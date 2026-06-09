<?php
// materials.php
require_once __DIR__ . '/includes/header.php';
$tenant_id = get_tenant_id();

// Handle Actions (Add Material, Issue Material, Return Material)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('danger', 'CSRF validation failed.');
        redirect('/materials.php');
    }

    $action = $_POST['action'] ?? '';

    // ADD MATERIAL
    if ($action === 'add_material') {
        $title = sanitize($_POST['title']);
        $isbn = sanitize($_POST['isbn_code']);
        $qty = intval($_POST['total_quantity']);
        
        db_insert($conn, "INSERT INTO materials (tenant_id, title, isbn_code, total_quantity, available_quantity) VALUES (?, ?, ?, ?, ?)", [
            $tenant_id, $title, $isbn, $qty, $qty
        ]);
        set_flash_message('success', 'Study material added to inventory!');
        redirect('/materials.php');
    }

    // ISSUE MATERIAL
    if ($action === 'issue_material') {
        $mat_id = intval($_POST['material_id']);
        $stud_id = intval($_POST['student_id']);
        $issue_date = sanitize($_POST['issue_date']);

        // Check if material is available
        $mat_res = db_query($conn, "SELECT available_quantity FROM materials WHERE id = ? AND tenant_id = ?", [$mat_id, $tenant_id]);
        $mat = mysqli_fetch_assoc($mat_res);

        if ($mat && $mat['available_quantity'] > 0) {
            mysqli_begin_transaction($conn);
            try {
                // Insert issue record
                db_insert($conn, "INSERT INTO material_issues (tenant_id, material_id, student_id, issue_date, status) VALUES (?, ?, ?, ?, 'Issued')", [
                    $tenant_id, $mat_id, $stud_id, $issue_date
                ]);
                
                // Deduct inventory
                db_query($conn, "UPDATE materials SET available_quantity = available_quantity - 1 WHERE id = ? AND tenant_id = ?", [$mat_id, $tenant_id]);
                
                mysqli_commit($conn);
                set_flash_message('success', 'Material issued successfully!');
            } catch (Exception $e) {
                mysqli_rollback($conn);
                set_flash_message('danger', 'Error issuing material.');
            }
        } else {
            set_flash_message('danger', 'Material is out of stock!');
        }
        redirect('/materials.php');
    }
}

// Handle Return Material (via GET)
if (isset($_GET['return_id'])) {
    $return_id = intval($_GET['return_id']);
    
    // Fetch issue details
    $issue_res = db_query($conn, "SELECT * FROM material_issues WHERE id = ? AND tenant_id = ? AND status = 'Issued'", [$return_id, $tenant_id]);
    $issue = mysqli_fetch_assoc($issue_res);

    if ($issue) {
        mysqli_begin_transaction($conn);
        try {
            // Update issue status
            db_query($conn, "UPDATE material_issues SET status = 'Returned', return_date = CURDATE() WHERE id = ? AND tenant_id = ?", [$return_id, $tenant_id]);
            
            // Add back to inventory
            db_query($conn, "UPDATE materials SET available_quantity = available_quantity + 1 WHERE id = ? AND tenant_id = ?", [$issue['material_id'], $tenant_id]);
            
            mysqli_commit($conn);
            set_flash_message('success', 'Material marked as returned and inventory updated.');
        } catch (Exception $e) {
            mysqli_rollback($conn);
            set_flash_message('danger', 'Error updating return state.');
        }
    }
    redirect('/materials.php');
}

// Fetch lists
$inventory = db_query($conn, "SELECT * FROM materials WHERE tenant_id = ? ORDER BY id DESC", [$tenant_id]);

$issues = db_query($conn, "
    SELECT mi.*, m.title as material_title, s.name as student_name, s.roll_number as student_roll 
    FROM material_issues mi 
    JOIN materials m ON mi.material_id = m.id 
    JOIN students s ON mi.student_id = s.id 
    WHERE mi.tenant_id = ? 
    ORDER BY mi.id DESC", [$tenant_id]);

// Fetch students for dropdown
$students_res = db_query($conn, "SELECT id, name, roll_number FROM students WHERE tenant_id = ? AND status = 'Active'", [$tenant_id]);
$students = [];
while ($row = mysqli_fetch_assoc($students_res)) { $students[] = $row; }

// Fetch available books for dropdown
$books_res = db_query($conn, "SELECT id, title, available_quantity FROM materials WHERE tenant_id = ? AND available_quantity > 0", [$tenant_id]);
$books = [];
while ($row = mysqli_fetch_assoc($books_res)) { $books[] = $row; }
?>

<div class="page-header">
    <h2><i class="bi bi-journals" style="color:var(--primary);margin-right:8px;"></i>Study Material & Inventory</h2>
    <div class="page-header-actions">
        <button class="btn btn-secondary" onclick="openModal('issueMaterialModal')"><i class="bi bi-journal-arrow-up"></i> Issue Material</button>
        <button class="btn btn-primary" onclick="openModal('addMaterialModal')"><i class="bi bi-plus"></i> Add Book/Material</button>
    </div>
</div>

<div class="grid-2">
    <!-- Stock Inventory Tracking -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Book & Material Inventory</h3>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Book Title</th>
                            <th>ISBN Code</th>
                            <th>In Stock</th>
                            <th>Available</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($inventory) === 0): ?>
                            <tr><td colspan="4">
                                <div class="empty-state">
                                    <i class="bi bi-collection"></i>
                                    <p>No materials in inventory.</p>
                                </div>
                            </td></tr>
                        <?php else: ?>
                            <?php while ($row = mysqli_fetch_assoc($inventory)): ?>
                                <tr>
                                    <td><strong><?= $row['title'] ?></strong></td>
                                    <td><?= $row['isbn_code'] ?: 'N/A' ?></td>
                                    <td><?= $row['total_quantity'] ?></td>
                                    <td>
                                        <span class="badge <?= $row['available_quantity'] > 0 ? 'success' : 'danger' ?>">
                                            <?= $row['available_quantity'] ?> available
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Student-wise Distribution Log -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Material Issue & Distribution Log</h3>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Book</th>
                            <th>Issue Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($issues) === 0): ?>
                            <tr><td colspan="4">
                                <div class="empty-state">
                                    <i class="bi bi-journal-x"></i>
                                    <p>No materials issued yet.</p>
                                </div>
                            </td></tr>
                        <?php else: ?>
                            <?php while ($row = mysqli_fetch_assoc($issues)): ?>
                                <tr>
                                    <td>
                                        <strong><?= $row['student_name'] ?></strong>
                                        <div class="text-muted">Roll: <?= $row['student_roll'] ?></div>
                                    </td>
                                    <td><?= $row['material_title'] ?></td>
                                    <td><?= date('d M Y', strtotime($row['issue_date'])) ?></td>
                                    <td>
                                        <?php if ($row['status'] === 'Issued'): ?>
                                            <div style="display:flex;flex-direction:column;gap:4px;">
                                                <span class="badge warning" style="width:fit-content;">Issued</span>
                                                <a href="<?= BASE_URL ?>/materials.php?return_id=<?= $row['id'] ?>" class="btn btn-secondary btn-sm" style="width:fit-content;"><i class="bi bi-arrow-counterclockwise"></i> Return</a>
                                            </div>
                                        <?php else: ?>
                                            <span class="badge success">Returned</span>
                                            <div class="text-muted" style="font-size:0.7rem;margin-top:4px;">Date: <?= date('d M Y', strtotime($row['return_date'])) ?></div>
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
</div>

<!-- Modal: Add Material -->
<div id="addMaterialModal" class="modal-backdrop">
    <div class="modal">
        <form action="materials.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="add_material">
            <div class="modal-header">
                <h3><i class="bi bi-journal-plus" style="color:var(--primary);margin-right:6px;"></i>Add Material to Inventory</h3>
                <button type="button" class="modal-close" onclick="closeModal('addMaterialModal')"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Material / Book Title</label>
                    <input type="text" name="title" class="form-control" placeholder="E.g., CSS Guide Volume 1" required>
                </div>
                <div class="form-group">
                    <label class="form-label">ISBN / Catalog Code</label>
                    <input type="text" name="isbn_code" class="form-control" placeholder="E.g., 978-3-16-148410-0">
                </div>
                <div class="form-group">
                    <label class="form-label">Total Stock Quantity</label>
                    <input type="number" name="total_quantity" class="form-control" value="10" min="1" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addMaterialModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Material</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Issue Material -->
<div id="issueMaterialModal" class="modal-backdrop">
    <div class="modal">
        <form action="materials.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="issue_material">
            <div class="modal-header">
                <h3>Issue Study Material</h3>
                <button type="button" class="btn btn-secondary" onclick="closeModal('issueMaterialModal')" style="padding: 4px 8px;">×</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Choose Student</label>
                    <select name="student_id" class="form-control" required>
                        <option value="">Select Student</option>
                        <?php foreach ($students as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= $s['name'] ?> (<?= $s['roll_number'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Select Book / Material</label>
                    <select name="material_id" class="form-control" required>
                        <option value="">Select Book</option>
                        <?php foreach ($books as $b): ?>
                            <option value="<?= $b['id'] ?>"><?= $b['title'] ?> (<?= $b['available_quantity'] ?> left)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Issue Date</label>
                    <input type="date" name="issue_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('issueMaterialModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Issue Material</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
