<?php include 'login/script/login_function.php'; ?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - CVSU Naic</title>
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="login/script/css/login.css">
</head>

<body class="auth-page">
    <div class="login-container">
        <!-- Logo Section -->
        <div class="logo-section">
            <img src="login/img/cvsu-logo.png" alt="CVSU Naic Logo" class="logo">
        </div>

        <!-- Header Section -->
        <div class="header-section">
            <h1 class="main-title">Office of the Director</h1>
            <h2 class="main-title">for Curriculum and Instruction</h2>
            <p class="system-name">Document Management System</p>
        </div>

        <!-- Alert Messages -->
        <?php if (isset($error) && $error): ?>
            <div class="alert alert-error">
                <i class='bx bx-error-circle alert-icon'></i>
                <span class="alert-text"><?php echo htmlspecialchars($error); ?></span>
                <button type="button" class="alert-close">
                    <i class='bx bx-x'></i>
                </button>
            </div>
        <?php endif; ?>

        <?php if (isset($success) && $success): ?>
            <div class="alert alert-success">
                <i class='bx bx-check-circle alert-icon'></i>
                <span class="alert-text"><?php echo htmlspecialchars($success); ?></span>
                <button type="button" class="alert-close">
                    <i class='bx bx-x'></i>
                </button>
            </div>
        <?php endif; ?>

        <!-- Login Form -->
        <form method="POST" action="" class="login-form" id="loginForm">
            <div class="form-group">
                <label for="username" class="form-label">Username or Email</label>
                <div class="input-container">
                    <i class='bx bx-user input-icon'></i>
                    <input type="text" id="username" name="username" class="form-input"
                        placeholder="Enter your username or email"
                        value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required
                        autocomplete="username">
                </div>
                <div class="field-error" id="username-error">Please enter a valid username or email</div>
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <div class="input-container">
                    <i class='bx bx-lock-alt input-icon'></i>
                    <input type="password" id="password" name="password" class="form-input"
                        placeholder="Enter your password" required autocomplete="current-password">
                    <button type="button" class="password-toggle" onclick="togglePassword()">
                        <i class='bx bx-hide' id="password-icon"></i>
                    </button>
                </div>
                <div class="field-error" id="password-error">Password is required</div>
            </div>

            <!-- Form Options -->
            <div class="form-options">
                <label class="checkbox-container">
                    <input type="checkbox" id="remember" name="remember" <?php echo isset($_POST['remember']) ? 'checked' : ''; ?>>
                    <span class="checkmark"></span>
                    <span class="checkbox-text">Remember me</span>
                </label>
                <a href="login/forgot_password.php" class="forgot-link">Forgot Password?</a>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="submit-btn" id="loginBtn">
                <span class="btn-text">Sign In</span>
                <div class="btn-loader" style="display: none;">
                    <div class="spinner"></div>
                </div>
            </button>
        </form>

        <!-- Divider -->
        <div class="divider">
            <span class="divider-text">Don't have an account?</span>
        </div>

        <!-- Register Link -->
        <a href="login/register.php" class="register-link">
            <i class='bx bx-user-plus'></i>
            <span>Create New Account</span>
        </a>
    </div>

    <!-- JavaScript Files -->
    <script src="login/script/js/script1.js"></script>
</body>

</html>