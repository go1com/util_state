<?php

namespace go1\util_state;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\TableExistsException;
use Doctrine\DBAL\Schema\Comparator;

class Helper
{
    public static function install(Connection $db, callable $callback)
    {
        $db->transactional(
            function (Connection $db) use (&$callback) {
                $compare = new Comparator;
                $schemaManager = $db->getSchemaManager();
                $schema = $schemaManager->createSchema();
                $originSchema = clone $schema;
                $callback($schema);

                $diff = $compare->compare($originSchema, $schema);
                foreach ($diff->toSql($db->getDatabasePlatform()) as $sql) {
                    try {
                        $db->executeQuery($sql);
                    } catch (TableExistsException $e) {
                        // table already there.
                    }
                }
            }
        );
    }

    public static function safeThread(Connection $db, string $threadName, int $timeout, callable $callback)
    {
        try {
            $sqlite = 'sqlite' === $db->getDatabasePlatform()->getName();
            !$sqlite && $db->executeQuery('DO GET_LOCK("' . $threadName . '", ' . $timeout . ')');

            return $callback($db);
        } finally {
            !$sqlite && $db->executeQuery('DO RELEASE_LOCK("' . $threadName . '")');
        }
    }
}
