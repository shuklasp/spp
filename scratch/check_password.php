<?php
$hash = '$2y$10$8vKh36Fyp1P1Fl1guauZt.EYSSjnSe0heWtl1gFIDVG0ypBoXcxCm';
$password = 'admin123';
if (password_verify($password, $hash)) {
    echo "Match found!\n";
} else {
    echo "No match.\n";
}
