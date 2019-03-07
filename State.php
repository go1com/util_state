<?php

namespace go1\util_state;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Type;
use PDO;
use UnexpectedValueException;

class State
{
    private $db;
    private $tableName;

    public function __construct(Connection $db, string $entityType)
    {
        $this->db = $db;
        $this->tableName = 'gc_state_' . strtolower($entityType);
    }

    public function install()
    {
        Helper::install(
            $this->db,
            function (Schema $schema) {
                if (!$schema->hasTable($this->tableName)) {
                    $table = $schema->createTable($this->tableName);
                    $table->addColumn('id', Type::INTEGER, ['unsigned' => true]);
                    $table->addColumn('val', Type::BLOB);
                    $table->setPrimaryKey(['id']);
                }
            }
        );
    }

    public function clear(int $id): int
    {
        return $this->db->delete($this->tableName, ['id' => $id]);
    }

    public function set(int $id, $val)
    {
        if (!is_array($val) && !is_object($val)) {
            throw new UnexpectedValueException('state value: expecting array or object for');
        }

        Helper::safeThread(
            $this->db,
            $this->tableName . '_' . $id,
            3,
            function () use ($id, &$val) {
                try {
                    $this->db->insert($this->tableName, [
                        'id'  => $id,
                        'val' => json_encode($val),
                    ]);
                } catch (UniqueConstraintViolationException $e) {
                    $this->db->update(
                        $this->tableName,
                        ['val' => json_encode($val)],
                        ['id' => $id]
                    );
                }
            }
        );
    }

    public function get(array $ids): array
    {
        $results = array_fill_keys($ids, null);

        $q = 'SELECT id, val FROM ' . $this->tableName . ' WHERE id IN (?)';
        $q = $this->db->executeQuery($q, [array_map('intval', $ids)], [Connection::PARAM_INT_ARRAY]);

        while ($_ = $q->fetch(PDO::FETCH_OBJ)) {
            $results[(int) $_->id] = json_decode($_->val);
        }

        return $results;
    }
}
