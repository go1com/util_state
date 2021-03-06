<?php

namespace go1\util_state\testss;

use Doctrine\DBAL\DriverManager;
use go1\util_state\State;
use PHPUnit\Framework\TestCase;

class StateTest extends TestCase
{
    public function test()
    {
        $db = DriverManager::getConnection(['url' => 'sqlite://sqlite::memory:']);
        $state = new State($db, 'gc_tree');
        $state->install();

        // insert & fetch
        $state->set(1, ['hi']);
        $f1 = $state->get([1, 2]);
        $this->assertEquals(['hi'], $f1[1]);
        $this->assertEquals(null, $f1[2]);

        // insert, update & fetch
        $state->set(1, ['hello']);
        $state->set(2, ['hi']);
        $f2 = $state->get([1, 2]);
        $this->assertEquals(['hello'], $f2[1]);
        $this->assertEquals(['hi'], $f2[2]);

        // clear then fetch
        $state->clear(2);
        $f3 = $state->get([1, 2]);
        $this->assertEquals(['hello'], $f3[1]);
        $this->assertEquals(null, $f3[2]);
    }
}
