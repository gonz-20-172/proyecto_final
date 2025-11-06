<?php
require_once __DIR__ . '/vendor/autoload.php';

if (isAuthenticated()) {
    redirect('/pages/dashboard.php');
} else {
    redirect('/pages/login.php');
}