<?php

namespace SPPMod\SPPCrypto\Commands;

use SPP\CLI\Command;
use SPPMod\SPPCrypto\MpcKeySharder;

/**
 * GenerateCryptoShardCommand
 * CLI daemon to generate Shamir's Secret Sharing polynomial key shards across isolated vault nodes
 * and verify Zero-Knowledge Multi-Party Computation (MPC) signing proofs.
 */
class GenerateCryptoShardCommand extends Command
{
    protected string $name = 'crypto:shard:generate';
    protected string $description = 'Generate Shamir Secret key shards across vault nodes and verify Zero-Knowledge MPC signing';

    /**
     * Mandatory CLI SAPI Guarding to ensure zero-trust security execution.
     */
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        echo "\033[32mINFO:\033[0m Starting SPP Zero-Knowledge MPC Key Sharding & Vault Engine...\n\n";

        $shares = 5;
        $threshold = 3;
        $keyId = 'enterprise_master_secret';

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--shares=')) {
                $shares = (int)substr($arg, 9);
            } elseif (str_starts_with($arg, '--threshold=')) {
                $threshold = (int)substr($arg, 12);
            } elseif (str_starts_with($arg, '--key=')) {
                $keyId = substr($arg, 6);
            }
        }

        echo "Generating Shamir Polynomial Shards for Key ID: \033[36m{$keyId}\033[0m (Shares: {$shares}, Threshold: {$threshold})\n";
        echo "--------------------------------------------------------------------------------\n";

        $sharder = new MpcKeySharder($shares, $threshold);
        $vaultNodes = $sharder->generateShards($keyId);

        echo sprintf("%-15s | %-5s | %-40s | %-20s\n", "Vault Node ID", "Share", "Share Hash (Truncated)", "Storage Status");
        echo "--------------------------------------------------------------------------------\n";
        foreach ($vaultNodes as $nodeId => $meta) {
            echo sprintf("%-15s | %-5d | %-40s | \033[32m%-20s\033[0m\n", $nodeId, $meta['x'], substr($meta['share_hash'], 0, 38) . '..', $meta['status']);
        }
        echo "--------------------------------------------------------------------------------\n\n";

        echo "Simulating Zero-Knowledge Multi-Party Computation (MPC) Threshold Signing...\n";
        $testPayload = "SPP_ENTERPRISE_TRANSACTION_PAYLOAD_" . microtime(true);
        
        // Pick M participating nodes to meet threshold
        $participatingNodes = array_slice(array_keys($vaultNodes), 0, $threshold);
        echo "Participating Vault Nodes: \033[33m" . implode(', ', $participatingNodes) . "\033[0m\n";

        $signResult = $sharder->performMpcSigning($testPayload, $participatingNodes);

        echo "--------------------------------------------------------------------------------\n";
        echo sprintf("Payload Hash          : %s\n", $signResult['payload_hash']);
        echo sprintf("Master MPC Signature  : \033[36m%s\033[0m\n", $signResult['master_signature']);
        echo sprintf("Zero-Knowledge Secure : %s\n", $signResult['zero_knowledge_preserved'] ? "\033[32mTRUE (Master Key Never Assembled in Memory)\033[0m" : "\033[31mFALSE\033[0m");
        echo "--------------------------------------------------------------------------------\n";

        echo "\033[32mSUCCESS:\033[0m Cryptographic key sharding and MPC threshold signing complete.\n";
    }
}
