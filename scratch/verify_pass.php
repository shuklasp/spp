<?php
$hash = '$2y$10$8vKh36Fyp1P1Fl1guauZt.EYSSjnSe0heWtl1gFIDVG0ypBoXcxCm';
$pass = 'admin123';
if (password_verify($pass, $hash)) {
    echo "Password matches!\n";
} else {
    echo "Password does NOT match.\n";
}
