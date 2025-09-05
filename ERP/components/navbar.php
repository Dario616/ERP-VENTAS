<?php
requerirLogin();
if (!isset($breadcrumb_items)) {
    $breadcrumb_items = [];
}
?>

<nav class="navbar navbar-expand-lg navbar-dark fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?php echo $url_base; ?>index.php">
            <img src="<?php echo $url_base; ?>utils/logoa.png" alt="America TNT" height="30">
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?php echo (empty($breadcrumb_items)) ? 'active' : ''; ?>"
                        href="<?php echo $url_base; ?>index.php">
                        <i class="fas fa-home me-1"></i>Dashboard
                    </a>
                </li>
                <?php foreach ($breadcrumb_items as $index => $item): ?>
                    <li class="nav-item">
                        <?php if ($index == count($breadcrumb_items) - 1):
                        ?>
                            <span class="nav-link active">
                                <i class="fas fa-chevron-right me-2"></i>
                                <?php echo htmlspecialchars($item); ?>
                            </span>
                        <?php else:
                        ?>
                            <a class="nav-link" href="<?php echo $item_urls[$index]; ?>">
                                <i class="fas fa-chevron-right me-2"></i>
                                <?php echo htmlspecialchars($item); ?>
                            </a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="navbar-nav">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle me-1"></i><?php echo htmlspecialchars($_SESSION['nombre']); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="<?php echo $url_base; ?>auth/cerrar_sesion.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</nav>