<?php
$hash = '$2y$10$gl9h4HJHO/t.7pBpPafOuO.ROkMs29rzCoSRZ182zHlHl05VbWtXy';
echo "Verify 'admin123': " . (password_verify('admin123', $hash) ? 'YES' : 'NO') . "\n";
