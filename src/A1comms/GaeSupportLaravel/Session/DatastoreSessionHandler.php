<?php

declare(strict_types=1);

namespace A1comms\GaeSupportLaravel\Session;

use A1comms\GaeSupportLaravel\Integration\Datastore\DatastoreFactory;
use Carbon\Carbon;
use DateTimeInterface;
use Google\Cloud\Core\ExponentialBackoff;
use Google\Cloud\Datastore\DatastoreClient;
use Google\Cloud\Datastore\Key;
use Google\Cloud\Datastore\Transaction;
use LogicException;
use SessionHandlerInterface;

class DatastoreSessionHandler implements SessionHandlerInterface
{
    /** @const string[] */
    private const excludeFromIndexes = [
        'data',
    ];

    /** @var DatastoreClient */
    private $datastore;

    /** @var Transaction */
    private $transaction;

    /** @var string */
    private $namespaceId;

    /** @var string */
    private $kind;

    /** @var string */
    private $orig_data;

    /** @var string */
    private $orig_id;

    public function __construct($kind = 'sessions', $namespaceId = null)
    {
        $this->datastore   = new DatastoreClient();
        $this->kind        = $kind;
        $this->namespaceId = $namespaceId;
    }

    public function open(string $savePath, string $sessionName): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        try {
            $key    = $this->getKey($id);
            $entity = (new ExponentialBackoff(6, [DatastoreFactory::class, 'shouldRetry']))->execute([$this->getTransaction(), 'lookup'], [$key]);
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
                    ],
                    $this->getQueryOptions(),
                );
                (new ExponentialBackoff(6, [DatastoreFactory::class, 'shouldRetry']))->execute([$this->getTransaction(), 'upsert'], [$entity]);
                (new ExponentialBackoff(6, [DatastoreFactory::class, 'shouldRetry']))->execute([$this->getTransaction(), 'commit'], []);
                $this->clearTransaction();
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
            (new ExponentialBackoff(6, [DatastoreFactory::class, 'shouldRetry']))->execute([$this->getTransaction(), 'delete'], [$key]);
            (new ExponentialBackoff(6, [DatastoreFactory::class, 'shouldRetry']))->execute([$this->getTransaction(), 'commit'], []);
            $this->clearTransaction();
        } catch (Exception $e) {
            trigger_error(
                sprintf('Datastore delete failed: %s', $e->getMessage()),
                E_USER_WARNING
            );

            return false;
        }

        return true;
    }

    public function gc($maxlifetime): int|false
    {
        return false;
    }

    public function googlegc(): void
    {
        throw new LogicException('PHP based Session GC is deprecated, please use the Go app in Cloud Functions');
    }

    protected function getTransaction(): Transaction
    {
        if (null === $this->transaction) {
            $this->transaction = (new ExponentialBackoff(6, [DatastoreFactory::class, 'shouldRetry']))->execute([$this->datastore, 'transaction'], []);
        }

        return $this->transaction;
    }

    protected function clearTransaction(): void
    {
        $this->transaction = null;
    }

    protected function getKey($id): Key
    {
        return $this->datastore->key(
            $this->kind,
            $id,
            ['namespaceId' => $this->namespaceId],
        );
    }

    protected function getQueryOptions(): array
    {
        return [
            'excludeFromIndexes' => self::excludeFromIndexes,
        ];
    }

    protected function getTimeStamp(): DateTimeInterface
    {
        return Carbon::now();
    }
}
