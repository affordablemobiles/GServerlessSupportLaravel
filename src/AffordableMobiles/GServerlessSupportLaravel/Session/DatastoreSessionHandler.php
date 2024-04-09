<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Session;

use AffordableMobiles\GServerlessSupportLaravel\Integration\Datastore\DatastoreFactory;
use Carbon\Carbon;
use Google\Cloud\Core\ExponentialBackoff;
use Google\Cloud\Datastore\DatastoreClient;
use Google\Cloud\Datastore\Key;

class DatastoreSessionHandler implements \SessionHandlerInterface
{
    /** @const string[] */
    private const excludeFromIndexes = [
        'data',
        'expireAt',
    ];

    /** @var DatastoreClient */
    private $datastore;

    /** @var string */
    private $kind;

    /** @var string */
    private $orig_data;

    /** @var string */
    private $orig_id;

    public function __construct($kind = 'sessions', $namespaceId = null, $databaseId = '')
    {
        $this->datastore   = new DatastoreClient([
            'namespaceId' => $namespaceId,
            'databaseId'  => $databaseId,
        ]);
        $this->kind        = $kind;
    }

    public function open(string $savePath, string $sessionName): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): false|string
    {
        try {
            $key    = $this->getKey($id);
            $entity = (new ExponentialBackoff(6, [DatastoreFactory::class, 'shouldRetry']))->execute([$this->datastore, 'lookup'], [$key]);
            if (null !== $entity && isset($entity['data'])) {
                $this->orig_id   = $id;
                $this->orig_data = $entity['data'];

                return $entity['data'];
            }
        } catch (Exception $e) {
            trigger_error(
                sprintf('Datastore lookup failed: %s', $e->getMessage()),
                E_USER_WARNING
            );
        }

        return '';
    }

    public function write(string $id, string $data): bool
    {
        if (($this->orig_id !== $id) || ($this->orig_data !== $data)) {
            try {
                $key    = $this->getKey($id);
                $entity = $this->datastore->entity(
                    $key,
                    [
                        'data'       => $data,
                        'lastaccess' => $this->getTimeStamp(),
                        'expireAt'   => $this->getExpiryTimeStamp(),
                    ],
                    $this->getQueryOptions(),
                );
                (new ExponentialBackoff(6, [DatastoreFactory::class, 'shouldRetry']))->execute([$this->datastore, 'upsert'], [$entity]);
            } catch (Exception $e) {
                trigger_error(
                    sprintf('Datastore upsert failed: %s', $e->getMessage()),
                    E_USER_WARNING
                );

                return false;
            }
        }

        return true;
    }

    public function destroy(string $id): bool
    {
        try {
            $key = $this->getKey($id);
            (new ExponentialBackoff(6, [DatastoreFactory::class, 'shouldRetry']))->execute([$this->datastore, 'delete'], [$key]);
        } catch (Exception $e) {
            trigger_error(
                sprintf('Datastore delete failed: %s', $e->getMessage()),
                E_USER_WARNING
            );

            return false;
        }

        return true;
    }

    public function gc($maxlifetime): false|int
    {
        return false;
    }

    public function googlegc(): void
    {
        throw new \LogicException('PHP based Session GC is deprecated, please use the Go app in Cloud Functions');
    }

    protected function getKey($id): Key
    {
        return $this->datastore->key(
            $this->kind,
            $id,
            [],
        );
    }

    protected function getQueryOptions(): array
    {
        return [
            'excludeFromIndexes' => self::excludeFromIndexes,
        ];
    }

    protected function getTimeStamp(): \DateTimeInterface
    {
        return Carbon::now();
    }

    protected function getExpiryTimeStamp(): \DateTimeInterface
    {
        return Carbon::now()->addMinutes(
            (int) config('session.lifetime'),
        );
    }
}
