<?php
session_start();
unset($_SESSION['admin_username'], $_SESSION['admin_number']);
header("Location: login_admin.html");
exit;
?>
