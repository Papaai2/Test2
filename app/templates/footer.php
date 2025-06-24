<?php
// in file: app/templates/footer.php
// This file assumes it's included within the <body> and after the <main> content.
?>
            </main>
            <footer class="footer mt-auto py-3">
                <div class="container text-center">
                    <span class="text-muted">&copy; <?= date('Y') ?> <?= htmlspecialchars(SITE_NAME ?? 'HR Portal') ?>. All rights reserved.</span>
                </div>
            </footer>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="/js/form-validation.js"></script>

<?php
// Conditionally load page-specific JavaScript to keep the site fast
$requestUri = $_SERVER['REQUEST_URI'];

// Load user management script ONLY on the admin/users.php page
if (strpos($requestUri, '/admin/users.php') !== false) {
    echo '<script src="/admin/js/user-management.js"></script>';
}

// Load leave type management script ONLY on the admin/leave_management.php page
if (strpos($requestUri, '/admin/leave_management.php') !== false) {
    echo '<script src="/admin/js/leave-management-crud.js"></script>';
}
?>

</body>
</html>