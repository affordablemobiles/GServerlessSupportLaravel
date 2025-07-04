<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Auth\Token\Claims;

use ArrayAccess;
use Illuminate\Support\Arr;

/**
 * Represents the structured 'google' claim from a validated IAP JWT.
 *
 * This provides type-safe, convenient access to device and access level
 * information required for Endpoint Verification checks. It validates the
 * incoming claim structure upon instantiation and implements ArrayAccess
 * for full backward compatibility without redundant data storage.
 */
class Google implements \ArrayAccess
{
    /**
     * The unique identifier for the user's device.
     *
     * This is populated from the 'device_id' field in the JWT claim.
     */
    public readonly ?string $deviceId;

    /**
     * A list of access levels associated with the request.
     *
     * These correspond to the Endpoint Verification policies the
     * user's device has satisfied.
     *
     * @var string[]
     */
    public readonly array $accessLevels;

    /**
     * @param array $claim the 'google' claim array from the decoded JWT
     */
    public function __construct(array $claim)
    {
        // Extract and assign the device_id and access_levels to readonly properties.
        // The Arr::get helper provides a safe way to access array keys, with a
        // default value if the key does not exist.
        $this->deviceId     = Arr::get($claim, 'device_id');
        $this->accessLevels = Arr::get($claim, 'access_levels', []);
    }

    /**
     * Get the device ID from the claim.
     */
    public function getDeviceId(): ?string
    {
        return $this->deviceId;
    }

    /**
     * Get the access levels from the claim.
     */
    public function getAccessLevels(): array
    {
        return $this->accessLevels;
    }

    /**
     * Check if the claim contains a specific, required access level.
     *
     * @param string $requiredLevel the full name of the access level to check for
     */
    public function hasAccessLevel(string $requiredLevel): bool
    {
        return \in_array($requiredLevel, $this->accessLevels, true);
    }

    /**
     * Determine if a claim key exists.
     *
     * @param mixed $offset
     */
    public function offsetExists($offset): bool
    {
        return \in_array($offset, ['device_id', 'access_levels'], true);
    }

    /**
     * Get a claim value by key, emulating array access.
     *
     * @param mixed $offset
     */
    public function offsetGet($offset): mixed
    {
        return match ($offset) {
            'device_id'     => $this->deviceId,
            'access_levels' => $this->accessLevels,
            default         => null,
        };
    }

    /**
     * Set a claim value (not supported).
     *
     * @param mixed $offset
     * @param mixed $value
     *
     * @throws \LogicException
     */
    public function offsetSet($offset, $value): void
    {
        // This object is immutable; setting claims is not allowed.
        throw new \LogicException('Cannot modify a Google Claim object.');
    }

    /**
     * Unset a claim value (not supported).
     *
     * @param mixed $offset
     *
     * @throws \LogicException
     */
    public function offsetUnset($offset): void
    {
        // This object is immutable; unsetting claims is not allowed.
        throw new \LogicException('Cannot modify a Google Claim object.');
    }
}
