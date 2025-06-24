document.addEventListener('DOMContentLoaded', function() {
    const userModalElement = document.getElementById('userModal');
    // Exit if the modal element doesn't exist on the current page
    if (!userModalElement) {
        return;
    }

    const userModal = new bootstrap.Modal(userModalElement);
    const userForm = document.getElementById('userForm');
    const userModalLabel = document.getElementById('userModalLabel');
    const formAction = document.getElementById('formAction');
    const userId = document.getElementById('userId');
    const password = document.getElementById('password');
    const passwordHelp = document.getElementById('passwordHelp');

    // Attach click event listener to the "Add New User" button
    const addUserButton = document.querySelector('button[data-bs-target="#userModal"]');
    if(addUserButton) {
        addUserButton.addEventListener('click', function() {
            userForm.reset();
            userModalLabel.textContent = 'Add New User';
            formAction.value = 'add';
            userId.value = '';
            password.placeholder = 'Required';
            password.setAttribute('required', 'required');
            passwordHelp.style.display = 'none';
            userModal.show();
        });
    }

    // Use event delegation for the "Edit" buttons since they are in a loop
    document.querySelector('.user-cards-container').addEventListener('click', function(event) {
        const editButton = event.target.closest('.edit-user-btn');
        if (editButton) {
            const user = JSON.parse(editButton.getAttribute('data-user'));
            
            userForm.reset();
            userModalLabel.textContent = 'Edit User: ' + user.full_name;
            formAction.value = 'edit';
            
            // Populate all form fields with the user's data
            document.getElementById('userId').value = user.id;
            document.getElementById('fullName').value = user.full_name;
            document.getElementById('email').value = user.email;
            document.getElementById('employeeCode').value = user.employee_code || '';
            document.getElementById('role').value = user.role;
            document.getElementById('departmentId').value = user.department_id || '';
            document.getElementById('shiftId').value = user.shift_id || '';
            document.getElementById('directManagerId').value = user.direct_manager_id || '';
            document.getElementById('isActive').checked = user.is_active == 1;

            password.placeholder = 'Leave blank to keep current';
            password.removeAttribute('required');
            passwordHelp.style.display = 'block';

            userModal.show();
        }
    });
});