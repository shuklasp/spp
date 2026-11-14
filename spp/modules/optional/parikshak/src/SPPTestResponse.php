<?php
namespace SPPMod\Parikshak;

/**
 * Class SPPTestResponse
 * Wraps an HTTP response (or mocked response) to provide fluent assertions.
 */
class SPPTestResponse
{
    private string $content;
    private int $statusCode;
    private array $headers;

    public function __construct(string $content, int $statusCode, array $headers = [])
    {
        $this->content = $content;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    public function assertStatus(int $status): self
    {
        if ($this->statusCode !== $status) {
            throw new \Exception("Expected status {$status}, got {$this->statusCode}. Content: " . substr($this->content, 0, 200));
        }
        return $this;
    }

    public function assertOk(): self
    {
        return $this->assertStatus(200);
    }

    public function assertNoContent(): self
    {
        return $this->assertStatus(204);
    }

    public function assertNotFound(): self
    {
        return $this->assertStatus(404);
    }

    public function assertForbidden(): self
    {
        return $this->assertStatus(403);
    }

    public function assertUnauthorized(): self
    {
        return $this->assertStatus(401);
    }

    public function assertRedirect(?string $uri = null): self
    {
        $redirectStatuses = [301, 302, 303, 307, 308];
        if (!in_array($this->statusCode, $redirectStatuses, true)) {
            throw new \Exception("Expected redirect status code, got {$this->statusCode}");
        }

        if ($uri !== null) {
            $location = $this->getHeader('Location');
            if ($location === null || !str_contains($location, $uri)) {
                throw new \Exception("Expected redirect to '{$uri}', but got '{$location}'");
            }
        }

        return $this;
    }

    public function assertJson(array $expected): self
    {
        $data = $this->json();
        if ($data === null || !is_array($data)) {
            throw new \Exception("Response is not valid JSON.");
        }
        
        foreach ($expected as $key => $value) {
            if (!array_key_exists($key, $data) || $data[$key] !== $value) {
                $actual = array_key_exists($key, $data) ? var_export($data[$key], true) : 'missing';
                $exp = var_export($value, true);
                throw new \Exception("JSON assertion failed for key '{$key}': expected {$exp}, got {$actual}");
            }
        }
        
        return $this;
    }

    public function assertJsonFragment(array $fragment): self
    {
        $data = $this->json();
        if ($data === null || !is_array($data)) {
            throw new \Exception("Response is not valid JSON.");
        }

        $found = $this->containsFragment($data, $fragment);
        if (!$found) {
            throw new \Exception("Failed asserting that response JSON contains fragment: " . json_encode($fragment));
        }

        return $this;
    }

    private function containsFragment(array $haystack, array $needle): bool
    {
        $match = true;
        foreach ($needle as $k => $v) {
            if (!array_key_exists($k, $haystack)) {
                $match = false;
                break;
            }
            if (is_array($v) && is_array($haystack[$k])) {
                if (!$this->containsFragment($haystack[$k], $v)) {
                    $match = false;
                    break;
                }
            } elseif ($haystack[$k] !== $v) {
                $match = false;
                break;
            }
        }
        if ($match) {
            return true;
        }

        foreach ($haystack as $sub) {
            if (is_array($sub) && $this->containsFragment($sub, $needle)) {
                return true;
            }
        }

        return false;
    }

    public function assertJsonPath(string $path, mixed $expectedValue): self
    {
        $data = $this->json();
        if ($data === null) {
            throw new \Exception("Response is not valid JSON.");
        }

        $segments = explode('.', $path);
        $current = $data;

        foreach ($segments as $segment) {
            if (is_array($current) && array_key_exists($segment, $current)) {
                $current = $current[$segment];
            } else {
                throw new \Exception("JSON path '{$path}' not found in response.");
            }
        }

        if ($current !== $expectedValue) {
            $act = var_export($current, true);
            $exp = var_export($expectedValue, true);
            throw new \Exception("JSON path '{$path}' expected {$exp}, got {$act}");
        }

        return $this;
    }

    public function assertJsonCount(int $expectedCount, ?string $key = null): self
    {
        $data = $this->json($key);
        if (!is_array($data)) {
            throw new \Exception("Target data for assertJsonCount is not an array.");
        }

        $actualCount = count($data);
        if ($actualCount !== $expectedCount) {
            throw new \Exception("Expected JSON array count {$expectedCount}, found {$actualCount}");
        }

        return $this;
    }

    public function assertSee(string $text): self
    {
        if (!str_contains($this->content, $text)) {
            throw new \Exception("Expected text '{$text}' not found in response.");
        }
        return $this;
    }

    public function assertDontSee(string $text): self
    {
        if (str_contains($this->content, $text)) {
            throw new \Exception("Found unexpected text '{$text}' in response.");
        }
        return $this;
    }

    public function assertHeader(string $headerName, ?string $expectedValue = null): self
    {
        $val = $this->getHeader($headerName);
        if ($val === null) {
            throw new \Exception("Expected header '{$headerName}' was not found.");
        }
        if ($expectedValue !== null && $val !== $expectedValue) {
            throw new \Exception("Header '{$headerName}' expected '{$expectedValue}', got '{$val}'");
        }
        return $this;
    }

    public function getHeader(string $name): ?string
    {
        $lower = strtolower($name);
        foreach ($this->headers as $k => $v) {
            if (strtolower($k) === $lower) {
                return is_array($v) ? implode(', ', $v) : (string)$v;
            }
        }
        return null;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function json(?string $key = null): mixed
    {
        $decoded = json_decode($this->content, true);
        if ($key === null) {
            return $decoded;
        }

        if (!is_array($decoded)) {
            return null;
        }

        $segments = explode('.', $key);
        $curr = $decoded;
        foreach ($segments as $seg) {
            if (is_array($curr) && array_key_exists($seg, $curr)) {
                $curr = $curr[$seg];
            } else {
                return null;
            }
        }
        return $curr;
    }

    public function assertMatchesSnapshot(?string $snapshotName = null): self
    {
        SnapshotManager::assertMatches($this->content, $snapshotName);
        return $this;
    }

    /**
     * Record the current response into a Consumer-Driven Contract specification.
     */
    public function recordContract(string $contractName, array $metadata = []): self
    {
        require_once __DIR__ . '/Contracts/ContractManager.php';
        $body = $this->json() ?: $this->content;
        Contracts\ContractManager::record($contractName, $this->statusCode, $this->headers, $body, $metadata);
        return $this;
    }

    /**
     * Assert that the current response conforms to a previously recorded API contract.
     */
    public function assertMatchesContract(string $contractName): self
    {
        require_once __DIR__ . '/Contracts/ContractManager.php';
        $body = $this->json() ?: $this->content;
        Contracts\ContractManager::assertMatches($contractName, $this->statusCode, $body);
        return $this;
    }
}
