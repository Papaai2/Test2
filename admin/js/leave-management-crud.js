document.addEventListener('DOMContentLoaded', function() {
    // --- Leave Type CRUD Modal Logic ---
    const leaveTypeModalElement = document.getElementById('leaveTypeModal');
    if (leaveTypeModalElement) {
        const leaveTypeModal = new bootstrap.Modal(leaveTypeModalElement);
        const modalForm = document.getElementById('leaveTypeForm');
        const modalTitle = document.getElementById('leaveTypeModalLabel');
        const formAction = document.getElementById('formAction');
        const typeId = document.getElementById('typeId');
        const typeName = document.getElementById('typeName');
        const accrualDays = document.getElementById('accrualDays');
        const isActive = document.getElementById('isActive');

        const addLeaveTypeButton = document.getElementById('addLeaveTypeBtn');
        if (addLeaveTypeButton) {
            addLeaveTypeButton.addEventListener('click', function() {
                modalForm.reset();
                modalTitle.textContent = 'Add New Leave Type';
                formAction.value = 'add_leave_type';
                typeId.value = '';
                isActive.checked = true;
                leaveTypeModal.show();
            });
        }

        const tableBody = document.querySelector('.table-leave-types tbody');
        if (tableBody) {
            tableBody.addEventListener('click', function(event) {
                const editButton = event.target.closest('.edit-leave-type-btn');
                if (editButton) {
                    const type = JSON.parse(editButton.getAttribute('data-type'));
                    modalForm.reset();
                    modalTitle.textContent = 'Edit Leave Type';
                    formAction.value = 'edit_leave_type';
                    typeId.value = type.id;
                    typeName.value = type.name;
                    accrualDays.value = type.accrual_days_per_year;
                    isActive.checked = type.is_active == 1;
                    leaveTypeModal.show();
                }
            });
        }
    }

    // --- Bulk Operations API Call Logic ---
    const feedbackDiv = document.getElementById('ajax-feedback');

    async function handleBulkAction(action, extraData = {}) {
        if (!confirm('Are you sure you want to perform this bulk action? This may affect multiple users and cannot be undone.')) {
            return;
        }

        feedbackDiv.innerHTML = `<div class="alert alert-info">Processing...</div>`;

        const formData = new FormData();
        formData.append('action', action);
        
        for (const key in extraData) {
            if (Array.isArray(extraData[key])) {
                extraData[key].forEach(value => formData.append(key + '[]', value));
            } else {
                formData.append(key, extraData[key]);
            }
        }

        try {
            const response = await fetch('/api/bulk_operations.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                feedbackDiv.innerHTML = `<div class="alert alert-success">${result.message || 'Action completed successfully.'}</div>`;
                setTimeout(() => window.location.reload(), 2000);
            } else {
                throw new Error(result.message || 'An unknown server error occurred.');
            }
        } catch (error) {
            feedbackDiv.innerHTML = `<div class="alert alert-danger">An error occurred: ${error.message}</div>`;
        }
    }
    
    // Attach listeners to bulk action buttons
    const btnAnnualAccrual = document.getElementById('btn-annual-accrual');
    if(btnAnnualAccrual) {
        btnAnnualAccrual.addEventListener('click', () => {
            handleBulkAction('perform_annual_accrual');
        });
    }

    const btnResetBalances = document.getElementById('btn-reset-balances');
    if(btnResetBalances) {
        btnResetBalances.addEventListener('click', () => {
            handleBulkAction('reset_all_balances');
        });
    }

    const btnAdjustBalances = document.getElementById('btn-adjust-balances');
    if(btnAdjustBalances) {
        btnAdjustBalances.addEventListener('click', () => {
            const selectedUsers = Array.from(document.getElementById('bulk-users').selectedOptions).map(opt => opt.value);
            const leaveTypeId = document.getElementById('bulk-leave-type').value;
            const newBalance = document.getElementById('bulk-new-balance').value;

            if (selectedUsers.length === 0 || !leaveTypeId || newBalance === '') {
                feedbackDiv.innerHTML = `<div class="alert alert-warning">Please select at least one user, a leave type, and enter a new balance.</div>`;
                return;
            }

            handleBulkAction('adjust_selected_balances', {
                user_ids: selectedUsers,
                leave_type_id: leaveTypeId,
                new_balance: newBalance
            });
        });
    }
});