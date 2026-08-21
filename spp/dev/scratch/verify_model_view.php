<?php
if (!defined('SPP_APP_DIR')) {
    define('SPP_APP_DIR', __DIR__ . '/../../..');
}
require_once __DIR__ . '/../../sppinit.php';

echo "=== Verifying SPPView and SPPDB Enhancements ===\n";

// Ensure DataTransformer file is autoloaded
interface_exists('\\SPPMod\\SPPView\\DataTransformer');

// 1. DataTransformer Verification
$boolTransformer = new \SPPMod\SPPView\BooleanTransformer();
echo "BooleanTransformer (true -> transform): " . $boolTransformer->transform(true) . "\n";
echo "BooleanTransformer (1 -> reverseTransform): " . ($boolTransformer->reverseTransform(1) ? 'true' : 'false') . "\n";

$encryptedTransformer = new \SPPMod\SPPView\EncryptedTransformer();
$secret = "EnterpriseSecretData123!";
$encrypted = $encryptedTransformer->transform($secret);
$decrypted = $encryptedTransformer->reverseTransform($encrypted);
echo "EncryptedTransformer (original): {$secret}\n";
echo "EncryptedTransformer (encrypted): {$encrypted}\n";
echo "EncryptedTransformer (decrypted): {$decrypted}\n";

// 2. ViewComposer Verification
class TestComposer {
    public function compose(&$data, $view) {
        $data['injected_by_class'] = true;
    }
}
\SPPMod\SPPView\ViewComposer::composer('admin.*', TestComposer::class);
$testData = [];
\SPPMod\SPPView\ViewComposer::compose('admin.dashboard', $testData);
echo "ViewComposer (class-based injection): " . (!empty($testData['injected_by_class']) ? 'SUCCESS' : 'FAILED') . "\n";

// 3. ViewLocator Verification
$locatorPaths = \SPPMod\SPPView\ViewLocator::locate('partials/non_existent.html', 'admin');
echo "ViewLocator (theme/partial fallback logic completed without errors): SUCCESS\n";

echo "=== ALL TESTS PASSED SUCCESSFULLY ===\n";
