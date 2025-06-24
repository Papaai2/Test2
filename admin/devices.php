<?php
require_once '../app/bootstrap.php';

// Authentication: Ensure the user is an admin.
if (!is_admin()) {
    header('Location: ../login.php');
    exit;
}

// The global $pdo object is available here from bootstrap.php
$errorMessage = '';
$successMessage = '';

// Handle POST requests for Add, Edit, and Delete actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add') {
            $stmt = $pdo->prepare("INSERT INTO devices (name, ip_address, port, device_brand, serial_number) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$_POST['name'], $_POST['ip_address'], $_POST['port'], $_POST['device_brand'], $_POST['serial_number']]);
            $successMessage = "Device added successfully!";
        } elseif ($action === 'edit') {
            $stmt = $pdo->prepare("UPDATE devices SET name = ?, ip_address = ?, port = ?, device_brand = ?, serial_number = ? WHERE id = ?");
            $stmt->execute([$_POST['name'], $_POST['ip_address'], $_POST['port'], $_POST['device_brand'], $_POST['serial_number'], $_POST['id']]);
            $successMessage = "Device updated successfully!";
        } elseif ($action === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM devices WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            $successMessage = "Device deleted successfully!";
        }
    } catch (PDOException $e) {
        $errorMessage = "Database error: " . $e->getMessage();
    }
}

// Fetch all devices to display in the table
$devices = $pdo->query("SELECT * FROM devices ORDER BY name ASC")->fetchAll();

include '../app/templates/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Device Management</h5>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDeviceModal">
                        <i class="fas fa-plus"></i> Add Device
                    </button>
                </div>
                <div class="card-body">
                    <div id="ajax-messages"></div> <?php if ($successMessage): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($successMessage); ?></div>
                    <?php endif; ?>
                    <?php if ($errorMessage): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($errorMessage); ?></div>
                    <?php endif; ?>

                    <div class="table-responsive">
                        <table class="table table-striped table-bordered">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>IP Address</th>
                                    <th>Port</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($devices)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center">No devices found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($devices as $device): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($device['id']); ?></td>
                                        <td><?php echo htmlspecialchars($device['name']); ?></td>
                                        <td><?php echo htmlspecialchars($device['ip_address']); ?></td>
                                        <td><?php echo htmlspecialchars($device['port']); ?></td>
                                        <td>
                                            <span class="badge bg-secondary" id="status-<?php echo $device['id']; ?>">
                                                Unknown
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-success" onclick="fetchLogs(this, <?php echo $device['id']; ?>)" title="Fetch Logs"><i class="fas fa-download"></i></button>
                                            <button class="btn btn-sm btn-info" onclick="testConnection(<?php echo $device['id']; ?>, '<?php echo htmlspecialchars($device['ip_address']); ?>', <?php echo $device['port']; ?>)" title="Test Connection"><i class="fas fa-plug"></i></button>
                                            <button class="btn btn-sm btn-warning" onclick='prepareEditModal(<?php echo json_encode($device); ?>)' title="Edit"><i class="fas fa-edit"></i></button>
                                            <button class="btn btn-sm btn-danger" onclick="deleteDevice(<?php echo $device['id']; ?>)" title="Delete"><i class="fas fa-trash"></i></button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addDeviceModal" tabindex="-1" aria-labelledby="addDeviceModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form action="devices.php" method="POST">
        <div class="modal-header">
          <h5 class="modal-title" id="addDeviceModalLabel">Add New Device</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <input type="hidden" name="action" value="add">
            <div class="mb-3">
                <label for="add_name" class="form-label">Device Name</label>
                <input type="text" class="form-control" id="add_name" name="name" required>
            </div>
            <div class="mb-3">
                <label for="add_ip_address" class="form-label">IP Address</label>
                <input type="text" class="form-control" id="add_ip_address" name="ip_address" required>
            </div>
            <div class="mb-3">
                <label for="add_port" class="form-label">Port</label>
                <input type="number" class="form-control" id="add_port" name="port" value="4370" required>
            </div>
            <div class="mb-3">
                <label for="add_device_brand" class="form-label">Brand</label>
                <input type="text" class="form-control" id="add_device_brand" name="device_brand">
            </div>
             <div class="mb-3">
                <label for="add_serial_number" class="form-label">Serial Number</label>
                <input type="text" class="form-control" id="add_serial_number" name="serial_number">
            </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary">Add Device</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="editDeviceModal" tabindex="-1" aria-labelledby="editDeviceModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form action="devices.php" method="POST">
        <div class="modal-header">
          <h5 class="modal-title" id="editDeviceModalLabel">Edit Device</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <div class="mb-3">
                <label for="edit_name" class="form-label">Device Name</label>
                <input type="text" class="form-control" id="edit_name" name="name" required>
            </div>
            <div class="mb-3">
                <label for="edit_ip_address" class="form-label">IP Address</label>
                <input type="text" class="form-control" id="edit_ip_address" name="ip_address" required>
            </div>
            <div class="mb-3">
                <label for="edit_port" class="form-label">Port</label>
                <input type="number" class="form-control" id="edit_port" name="port" required>
            </div>
            <div class="mb-3">
                <label for="edit_device_brand" class="form-label">Brand</label>
                <input type="text" class="form-control" id="edit_device_brand" name="device_brand">
            </div>
             <div class="mb-3">
                <label for="edit_serial_number" class="form-label">Serial Number</label>
                <input type="text" class="form-control" id="edit_serial_number" name="serial_number">
            </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<form id="deleteForm" action="devices.php" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="delete_id">
</form>

<?php include '../app/templates/footer.php'; ?>

<script>
function prepareEditModal(device) {
    document.getElementById('edit_id').value = device.id;
    document.getElementById('edit_name').value = device.name;
    document.getElementById('edit_ip_address').value = device.ip_address;
    document.getElementById('edit_port').value = device.port;
    document.getElementById('edit_device_brand').value = device.device_brand || '';
    document.getElementById('edit_serial_number').value = device.serial_number || '';

    var editModal = new bootstrap.Modal(document.getElementById('editDeviceModal'));
    editModal.show();
}

function deleteDevice(id) {
    if (confirm('Are you sure you want to delete this device?')) {
        document.getElementById('delete_id').value = id;
        document.getElementById('deleteForm').submit();
    }
}

function fetchLogs(button, deviceId) {
    const originalContent = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    document.getElementById('ajax-messages').innerHTML = '';

    fetch('../api/fetch_device_logs.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ device_id: deviceId })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        let msgClass = data.success ? 'alert-success' : 'alert-danger';
        let msgText = data.success ? data.message : `Error: ${data.message}`;
        document.getElementById('ajax-messages').innerHTML = `<div class="alert ${msgClass}">${msgText}</div>`;
    })
    .catch(error => {
        document.getElementById('ajax-messages').innerHTML = `<div class="alert alert-danger">Failed to fetch logs. The device might be offline or unreachable.</div>`;
        console.error('Fetch Logs Error:', error); // Keep detailed error in console for developers
    })
    .finally(() => {
        button.disabled = false;
        button.innerHTML = originalContent;
    });
}

function testConnection(deviceId, ip, port) {
    const statusBadge = document.getElementById(`status-${deviceId}`);
    statusBadge.classList.remove('bg-success', 'bg-danger');
    statusBadge.classList.add('bg-warning');
    statusBadge.innerText = 'Testing...';

    fetch('../api/test_device_connection.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ip_address: ip, port: port })
    })
    .then(response => response.json())
    .then(data => {
        statusBadge.classList.remove('bg-warning');
        if (data.status === 'online') {
            statusBadge.classList.add('bg-success');
            statusBadge.innerText = 'Online';
        } else {
            statusBadge.classList.add('bg-danger');
            statusBadge.innerText = 'Offline';
            console.error('Connection Test Error:', data.error);
        }
    })
    .catch(error => {
        statusBadge.classList.remove('bg-warning');
        statusBadge.classList.add('bg-danger');
        statusBadge.innerText = 'Error';
        console.error('Fetch Error:', error);
    });
}
</script>