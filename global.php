<?php
/**
 * Global Handler for Form Submissions for SPP-Twitter and Base System
 */



function test_form_submitted() {
    // Collect specific form field
    $name = $_POST['test_name'] ?? 'Unknown User';
    
    // Perform redirection to success page
    if (method_exists('\SPPMod\SPPView\ViewPage', 'redirect')) {
        \SPPMod\SPPView\ViewPage::redirect('test_success', ['name' => $name]);
    } else {
        header('Location: ?q=test_success&name=' . urlencode((string)$name));
        exit;
    }
}
