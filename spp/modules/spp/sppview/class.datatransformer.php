<?php

namespace SPPMod\SPPView;

/**
 * Interface DataTransformer
 * Allows converting data between the Model (Entity) and the View (Form Element).
 */
interface DataTransformer
{
    public function transform(mixed $value): mixed; // Model to View
    public function reverseTransform(mixed $value): mixed; // View to Model
}

/**
 * Transforms a DateTime object to Y-m-d string and vice-versa.
 */
class DateTransformer implements DataTransformer
{
    public function transform(mixed $value): mixed
    {
        if ($value instanceof \DateTime) {
            return $value->format('Y-m-d');
        }
        return $value;
    }
    public function reverseTransform(mixed $value): mixed
    {
        if (empty($value)) {
            return null;
        }
        return new \DateTime($value);
    }
}

/**
 * Transforms an array to a JSON string and vice-versa.
 */
class JsonTransformer implements DataTransformer
{
    public function transform(mixed $value): mixed
    {
        if (is_array($value)) {
            return json_encode($value);
        }
        return $value;
    }
    public function reverseTransform(mixed $value): mixed
    {
        if (empty($value)) {
            return [];
        }
        return json_decode($value, true);
    }
}

/**
 * Transforms a comma-separated string to an array and vice-versa.
 */
class ArrayTransformer implements DataTransformer
{
    public function transform(mixed $value): mixed
    {
        if (is_array($value)) {
            return implode(', ', $value);
        }
        return $value;
    }
    public function reverseTransform(mixed $value): mixed
    {
        if (empty($value)) {
            return [];
        }
        return array_map('trim', explode(',', $value));
    }
}

/**
 * Transforms a boolean value to tinyint 0/1 and vice-versa.
 */
class BooleanTransformer implements DataTransformer
{
    public function transform(mixed $value): mixed
    {
        return (bool)$value ? 1 : 0;
    }
    public function reverseTransform(mixed $value): mixed
    {
        return (bool)$value;
    }
}

/**
 * Transforms sensitive data via OpenSSL AES-256-CBC encryption and decryption.
 */
class EncryptedTransformer implements DataTransformer
{
    protected string $cipher = 'AES-256-CBC';

    protected function getKey(): string
    {
        $key = class_exists('\\SPP\\SPPConfig') ? \SPP\SPPConfig::get('app_key') ?? \SPP\SPPConfig::get('sys:security.xdb_key') : null;
        if (empty($key)) {
            $key = getenv('APP_KEY') ?: 'SPPDefaultSecretEnterpriseKey32!!';
        }
        return hash('sha256', $key, true);
    }

    public function transform(mixed $value): mixed
    {
        if (empty($value)) {
            return $value;
        }
        $ivlen = openssl_cipher_iv_length($this->cipher);
        $iv = openssl_random_pseudo_bytes($ivlen);
        $ciphertext = openssl_encrypt((string)$value, $this->cipher, $this->getKey(), OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $ciphertext);
    }

    public function reverseTransform(mixed $value): mixed
    {
        if (empty($value)) {
            return $value;
        }
        $data = base64_decode((string)$value);
        $ivlen = openssl_cipher_iv_length($this->cipher);
        if (strlen($data) < $ivlen) {
            return $value;
        }
        $iv = substr($data, 0, $ivlen);
        $ciphertext = substr($data, $ivlen);
        $decrypted = openssl_decrypt($ciphertext, $this->cipher, $this->getKey(), OPENSSL_RAW_DATA, $iv);
        return $decrypted !== false ? $decrypted : $value;
    }
}

/**
 * Transforms a comma-separated ID string or ID array into instantiated entity collection and vice-versa.
 */
class CollectionTransformer implements DataTransformer
{
    protected string $entityClass;

    public function __construct(string $entityClass)
    {
        $this->entityClass = $entityClass;
    }

    public function transform(mixed $value): mixed
    {
        if (is_array($value)) {
            $ids = [];
            foreach ($value as $item) {
                if (is_object($item) && property_exists($item, 'id')) {
                    $ids[] = $item->id;
                } elseif (is_numeric($item)) {
                    $ids[] = $item;
                }
            }
            return implode(',', $ids);
        }
        return $value;
    }

    public function reverseTransform(mixed $value): mixed
    {
        if (empty($value)) {
            return [];
        }
        $ids = is_array($value) ? $value : array_map('trim', explode(',', $value));
        $collection = [];
        if (class_exists($this->entityClass)) {
            foreach ($ids as $id) {
                if (!empty($id)) {
                    $collection[] = new $this->entityClass($id);
                }
            }
        }
        return $collection;
    }
}
