<?php declare(strict_types=1);

namespace PhallosanCustomizations\Migration;

use Doctrine\DBAL\Connection;
use PhallosanCustomizations\Migration\Trait\MigrationTrait;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class Migration1750769873ExtendDeliverystate extends MigrationStep
{
    use MigrationTrait;

    public function getCreationTimestamp(): int
    {
        return 1750769873;
    }

    public function update(Connection $connection): void
    {
        $statusId = Uuid::randomHex();
        $now = (new \DateTime())->format('Y-m-d H:i:s');

        $existing = $connection->fetchOne(
            'SELECT id FROM state_machine_state WHERE technical_name = "undeliverable" AND state_machine_id = (SELECT id FROM state_machine WHERE technical_name = "order_delivery.state")'
        );

        if ($existing) {
            return;
        }

        $stateMachineId = $connection->fetchOne('SELECT id FROM state_machine WHERE technical_name = "order_delivery.state"');

        $connection->insert('state_machine_state', [
            'id' => Uuid::fromHexToBytes($statusId),
            'technical_name' => 'undeliverable',
            'state_machine_id' => $stateMachineId,
            'created_at' => $now,
        ]);

        $languages = [
            'de-DE' => 'Unzustellbar',
            'en-GB' => 'Undeliverable',
        ];

        foreach ($languages as $locale => $name) {
            $langId = $connection->fetchOne('SELECT id FROM language WHERE locale_id = (SELECT id FROM locale WHERE code = ?)', [$locale]);
            if ($langId) {
                $connection->insert('state_machine_state_translation', [
                    'state_machine_state_id' => Uuid::fromHexToBytes($statusId),
                    'language_id' => $langId,
                    'name' => $name,
                    'created_at' => $now,
                ]);
            }
        }

        $this->createTransitions($connection, $stateMachineId, $statusId, $now);
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->executeStatement('DELETE FROM state_machine_transition WHERE action_name IN ("set_undeliverable", "revert_from_undeliverable")');

        $connection->executeStatement('DELETE FROM state_machine_state WHERE technical_name = "undeliverable" AND state_machine_id = (SELECT id FROM state_machine WHERE technical_name = "order_delivery")');
    }

    private function createTransitions(Connection $connection, string $stateMachineId, string $undeliverableStatusId, string $now): void
    {
        $existingStates = $connection->fetchAllAssociative(
            'SELECT id FROM state_machine_state WHERE state_machine_id = ?',
            [$stateMachineId]
        );

        foreach ($existingStates as $state) {
            $fromStateId = $state['id'];

            $existingTransition = $connection->fetchOne(
                'SELECT id FROM state_machine_transition WHERE from_state_id = ? AND to_state_id = ?',
                [$fromStateId, Uuid::fromHexToBytes($undeliverableStatusId)]
            );

            if (!$existingTransition) {
                $transitionId = Uuid::randomHex();
                $connection->insert('state_machine_transition', [
                    'id' => Uuid::fromHexToBytes($transitionId),
                    'action_name' => 'set_undeliverable',
                    'state_machine_id' => $stateMachineId,
                    'from_state_id' => $fromStateId,
                    'to_state_id' => Uuid::fromHexToBytes($undeliverableStatusId),
                    'created_at' => $now,
                ]);
            }
        }

        $undeliverableBytes = Uuid::fromHexToBytes($undeliverableStatusId);

        foreach ($existingStates as $state) {
            $toStateId = $state['id'];

            if ($toStateId === $undeliverableBytes) {
                continue;
            }

            $existingBackTransition = $connection->fetchOne(
                'SELECT id FROM state_machine_transition WHERE from_state_id = ? AND to_state_id = ?',
                [$undeliverableBytes, $toStateId]
            );

            if (!$existingBackTransition) {
                $backTransitionId = Uuid::randomHex();
                $connection->insert('state_machine_transition', [
                    'id' => Uuid::fromHexToBytes($backTransitionId),
                    'action_name' => 'revert_from_undeliverable',
                    'state_machine_id' => $stateMachineId,
                    'from_state_id' => $undeliverableBytes,
                    'to_state_id' => $toStateId,
                    'created_at' => $now,
                ]);
            }
        }
    }
}
