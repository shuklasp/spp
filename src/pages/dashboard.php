<?php
// User Dashboard Page

// 1. Enforce Authentication
if (session_status() === PHP_SESSION_NONE && php_sapi_name() !== 'cli') {
    @session_start();
}

if (!\SPPMod\SPPAuth\SPPAuth::check()) {
    header("Location: " . \SPP\App::getBaseUrl('default') . "/login");
    exit;
}

$user = \SPPMod\SPPAuth\SPPAuth::user();
$isAdmin = \SPPMod\SPPAuth\SPPAuth::can('administer site configuration') || $user->id === 'admin';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard</title>
    <!-- MDB5 CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.0/mdb.min.css" rel="stylesheet" />
    <style>
        body {
            background-color: #f8f9fa;
        }
        .dashboard-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 3rem 0;
            margin-bottom: 2rem;
            border-bottom-left-radius: 1rem;
            border-bottom-right-radius: 1rem;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light bg-light shadow-sm">
    <div class="container">
        <a class="navbar-brand" href="#">School App</a>
        <div class="d-flex align-items-center">
            <span class="me-3">Welcome, <strong><?php echo htmlspecialchars($user->username ?? 'User'); ?></strong></span>
            <a href="?logout=1" class="btn btn-outline-danger btn-sm">Logout</a>
        </div>
    </div>
</nav>

<div class="dashboard-header text-center shadow">
    <div class="container">
        <h1 class="display-4 fw-bold">Dashboard</h1>
        <p class="lead mb-0">Manage your profile and access applications.</p>
    </div>
</div>

<div class="container pb-5">
    <?php
    if (isset($_GET['logout'])) {
        \SPPMod\SPPAuth\SPPAuth::logout();
        header("Location: " . \SPP\App::getBaseUrl('default') . "/login");
        exit;
    }
    ?>

    <div class="row g-4">
        <div class="col-md-4">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body">
                    <h5 class="card-title text-primary"><i class="fas fa-user-circle me-2"></i> Profile</h5>
                    <ul class="list-unstyled mt-3 mb-0">
                        <li class="mb-2"><strong>ID:</strong> <?php echo htmlspecialchars($user->id ?? 'N/A'); ?></li>
                        <li class="mb-2"><strong>Username:</strong> <?php echo htmlspecialchars($user->username ?? 'N/A'); ?></li>
                        <li class="mb-2"><strong>Email:</strong> <?php echo htmlspecialchars($user->email ?? 'N/A'); ?></li>
                        <li class="mb-2">
                            <strong>Status:</strong> 
                            <span class="badge bg-success rounded-pill"><?php echo htmlspecialchars($user->status ?? 'active'); ?></span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body">
                    <h5 class="card-title text-secondary"><i class="fas fa-th-large me-2"></i> Applications</h5>
                    <div class="row mt-4 g-3">
                        
                        <?php if ($isAdmin): ?>
                        <div class="col-sm-6">
                            <div class="card bg-dark text-white h-100 border-0 shadow hover-shadow">
                                <div class="card-body text-center py-4">
                                    <h5 class="card-title mb-3">Lekhak CMS</h5>
                                    <p class="card-text small text-muted mb-4">Manage site content, configurations, and structural metadata.</p>
                                    <a href="<?php echo \SPP\App::getBaseUrl('lekhak'); ?>/admin" class="btn btn-light btn-rounded px-4">Open Admin</a>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="col-12 text-center py-5 text-muted">
                            <p>No extra applications assigned to your roles.</p>
                        </div>
                        <?php endif; ?>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MDB5 JS -->
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.0/mdb.min.js"></script>
</body>
</html>
