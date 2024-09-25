<?php

declare(strict_types=1);

namespace A1comms\GaeSupportLaravel\Integration\Debugbar\Storage;

use DebugBar\Storage\StorageInterface;

class NullStorage implements StorageInterface
{
    /**
     * Saves collected data.
     *
     * @param string $id
     * @param string $data
     */
    public function save($id, $data): void {}

    /**
     * Returns collected data with the specified id.
     *
     * @param string $id
     */
    public function get($id): array
    {
        return [];
    }

    /**
     * Returns a metadata about collected data.
     *
     * @param int $max
     * @param int $offset
     */
    public function find(array $filters = [], $max = 20, $offset = 0): array
    {
        return [];
    }

    /**
     * Clears all the collected data.
     */
    public function clear(): void {}
}
