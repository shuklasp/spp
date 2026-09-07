<?php
namespace SPPMod\Parikshak;

/**
 * Class SPPTestResponse
 * Wraps an HTTP response (or mocked response) to provide fluent assertions.
 */
class SPPTestResponse
{
    private $content;
    private $statusCode;
    private $headers;

    public function __construct(string $content, int $statusCode, array $headers = [])
    {
        $this->content = $content;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    public function assertStatus(int $status): self
    {
        if ($this->statusCode !== $status) {
            throw new \Exception("Expected status {$status}, got {$this->statusCode}");
        }
        return $this;
    }

    public function assertOk(): self
    {
        return $this->assertStatus(200);
    }

    public function assertNotFound(): self
    {
        return $this->assertStatus(404);
    }

    public function assertJson(array $expected): self
    {
        $data = json_decode($this->content, true);
        if ($data === null) {
            throw new \Exception("Response is not valid JSON.");
        }
        
        // Basic recursive check (simplified for now)
        foreach ($expected as $key => $value) {
            if (!array_key_exists($key, $data) || $data[$key] !== $value) {
                throw new \Exception("JSON assertion failed for key '{$key}'");
            }
        }
        
        return $this;
    }

    public function assertSee(string $text): self
    {
        if (strpos($this->content, $text) === false) {
            throw new \Exception("Expected text '{$text}' not found in response.");
        }
        return $this;
    }
}
